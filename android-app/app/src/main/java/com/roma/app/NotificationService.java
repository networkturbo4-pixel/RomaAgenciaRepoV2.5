package com.roma.app;

import android.Manifest;
import android.app.AlarmManager;
import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.net.ConnectivityManager;
import android.net.Network;
import android.os.Build;
import android.os.IBinder;
import android.os.SystemClock;
import android.text.Html;
import android.webkit.CookieManager;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;
import androidx.core.content.ContextCompat;
import androidx.core.graphics.drawable.IconCompat;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.File;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.net.URLEncoder;
import java.util.Calendar;
import java.util.zip.GZIPInputStream;

/**
 * Servicio en primer plano con LONG-POLLING: una sola petición abierta (~25 s) que el servidor
 * responde al instante cuando hay una notificación. Sin WakeLock, sin consultas fijas.
 * Es la ÚNICA fuente de notificaciones nativas (evita duplicados).
 */
public class NotificationService extends Service {

    public static final String PREFS_NAME = "roma_app_prefs";
    public static final String KEY_SERVER_URL = "custom_server_url";
    public static final String KEY_SITE_NAME = "cached_site_name";
    public static final String KEY_LAST_NOTIF_ID = "last_native_notif_id";
    public static final String KEY_QUIET_START = "quiet_start_hour"; // -1 = desactivado
    public static final String KEY_QUIET_END = "quiet_end_hour";
    public static final String KEY_TYPES = "notif_types";            // csv, vacío = todos
    public static final String FAVICON_FILE = "cached_system_favicon.png";

    private static final String ALERT_CHANNEL = "roma_realtime_alerts_v3";
    private static final String SERVICE_CHANNEL = "roma_service_silent";
    private static final int FOREGROUND_ID = 1;

    private volatile boolean running = false;
    private Thread worker;
    private final Object netLock = new Object();
    private volatile boolean online = true;
    private ConnectivityManager cm;
    private ConnectivityManager.NetworkCallback netCallback;

    public static void start(Context ctx) {
        Intent i = new Intent(ctx, NotificationService.class);
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                ContextCompat.startForegroundService(ctx, i);
            } else {
                ctx.startService(i);
            }
        } catch (Exception ignored) {}
    }

    @Override
    public void onCreate() {
        super.onCreate();
        createChannels(this);
        Notification fg = new NotificationCompat.Builder(this, SERVICE_CHANNEL)
                .setSmallIcon(R.drawable.ic_launcher_roma)
                .setContentTitle(getString(R.string.app_name))
                .setContentText("Conectado · recibiendo avisos")
                .setPriority(NotificationCompat.PRIORITY_MIN)
                .setOngoing(true)
                .setSilent(true)
                .setContentIntent(openAppIntent(this, null, 0))
                .build();
        if (Build.VERSION.SDK_INT >= 34) {
            startForeground(FOREGROUND_ID, fg, android.content.pm.ServiceInfo.FOREGROUND_SERVICE_TYPE_SPECIAL_USE);
        } else {
            startForeground(FOREGROUND_ID, fg);
        }

        registerNetworkCallback();
        running = true;
        worker = new Thread(this::loop, "roma-longpoll");
        worker.start();
    }

    /** Pausa el sondeo mientras no haya internet y lo reanuda al volver la conexión. */
    private void registerNetworkCallback() {
        try {
            cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
            if (cm == null) return;
            netCallback = new ConnectivityManager.NetworkCallback() {
                @Override
                public void onAvailable(Network network) {
                    synchronized (netLock) {
                        online = true;
                        netLock.notifyAll();
                    }
                }

                @Override
                public void onLost(Network network) {
                    online = cm.getActiveNetwork() != null;
                }
            };
            cm.registerDefaultNetworkCallback(netCallback);
            online = cm.getActiveNetwork() != null;
        } catch (Exception ignored) {}
    }

    private boolean sleepInterruptible(long ms) {
        long end = SystemClock.elapsedRealtime() + ms;
        synchronized (netLock) {
            while (running) {
                long left = end - SystemClock.elapsedRealtime();
                if (left <= 0) return true;
                try {
                    netLock.wait(left);
                } catch (InterruptedException e) {
                    return false;
                }
            }
        }
        return false;
    }

    private void loop() {
        SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        int backoffSec = 5;
        while (running) {
            try {
                if (!online) {
                    synchronized (netLock) {
                        while (running && !online) netLock.wait(60000);
                    }
                    continue;
                }
                if (inQuietHours(prefs)) {
                    if (!sleepInterruptible(5 * 60 * 1000L)) return;
                    continue;
                }

                String server = prefs.getString(KEY_SERVER_URL, getString(R.string.server_url));
                String cookies = CookieManager.getInstance().getCookie(server);
                if (cookies == null || cookies.isEmpty()) {
                    if (!sleepInterruptible(30000)) return;
                    continue;
                }

                int since = prefs.getInt(KEY_LAST_NOTIF_ID, -1);
                String types = prefs.getString(KEY_TYPES, "");
                String base = server.endsWith("/") ? server : server + "/";
                String url = base + "ajax/notifications_poll.php?since=" + since + "&timeout=25"
                        + (types.isEmpty() ? "" : "&types=" + URLEncoder.encode(types, "UTF-8"));

                HttpURLConnection conn = (HttpURLConnection) new URL(url).openConnection();
                try {
                    conn.setConnectTimeout(8000);
                    conn.setReadTimeout(35000);
                    conn.setRequestProperty("Cookie", cookies);
                    conn.setRequestProperty("Accept", "application/json");
                    conn.setRequestProperty("Accept-Encoding", "gzip");
                    int code = conn.getResponseCode();
                    if (code == 401 || code == 403) {
                        if (!sleepInterruptible(60000)) return;
                        continue;
                    }
                    if (code != 200) throw new Exception("HTTP " + code);

                    InputStream in = conn.getInputStream();
                    if ("gzip".equalsIgnoreCase(conn.getContentEncoding())) in = new GZIPInputStream(in);
                    StringBuilder sb = new StringBuilder();
                    try (BufferedReader r = new BufferedReader(new InputStreamReader(in))) {
                        String line;
                        while ((line = r.readLine()) != null) sb.append(line);
                    }
                    JSONObject json = new JSONObject(sb.toString());
                    if (!json.optBoolean("success", false)) throw new Exception("api");

                    int lastId = json.optInt("last_id", since);
                    JSONArray arr = json.optJSONArray("notifications");
                    if (since < 0) {
                        // Primera sincronización: solo guardar el cursor, sin notificar el historial
                        prefs.edit().putInt(KEY_LAST_NOTIF_ID, lastId).apply();
                    } else {
                        if (arr != null) {
                            for (int i = 0; i < arr.length(); i++) {
                                JSONObject n = arr.getJSONObject(i);
                                int id = n.optInt("id", 0);
                                if (id > prefs.getInt(KEY_LAST_NOTIF_ID, -1)) {
                                    post(this, id,
                                            decode(n.optString("title", "")),
                                            decode(n.optString("message", "")),
                                            n.optString("link", ""));
                                }
                            }
                        }
                        if (lastId > prefs.getInt(KEY_LAST_NOTIF_ID, -1)) {
                            prefs.edit().putInt(KEY_LAST_NOTIF_ID, lastId).apply();
                        }
                    }
                    backoffSec = 5;
                } finally {
                    conn.disconnect();
                }
            } catch (InterruptedException ie) {
                return;
            } catch (Exception e) {
                // Reintento con espera creciente (5 a 60 s) para no gastar batería si el servidor no responde
                if (!sleepInterruptible(backoffSec * 1000L)) return;
                backoffSec = Math.min(60, backoffSec * 2);
            }
        }
    }

    /** Horario silencioso: si start == end o -1, está desactivado. Soporta rangos que cruzan medianoche. */
    private boolean inQuietHours(SharedPreferences prefs) {
        int s = prefs.getInt(KEY_QUIET_START, -1);
        int e = prefs.getInt(KEY_QUIET_END, -1);
        if (s < 0 || e < 0 || s == e) return false;
        int h = Calendar.getInstance().get(Calendar.HOUR_OF_DAY);
        return s < e ? (h >= s && h < e) : (h >= s || h < e);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        return START_STICKY;
    }

    @Override
    public void onTaskRemoved(Intent rootIntent) {
        Intent restart = new Intent(getApplicationContext(), NotificationService.class);
        int f = PendingIntent.FLAG_ONE_SHOT | (Build.VERSION.SDK_INT >= 23 ? PendingIntent.FLAG_IMMUTABLE : 0);
        PendingIntent pi = PendingIntent.getService(getApplicationContext(), 7, restart, f);
        AlarmManager am = (AlarmManager) getSystemService(Context.ALARM_SERVICE);
        if (am != null) {
            am.set(AlarmManager.ELAPSED_REALTIME, SystemClock.elapsedRealtime() + 1500, pi);
        }
        super.onTaskRemoved(rootIntent);
    }

    @Override
    public void onDestroy() {
        running = false;
        synchronized (netLock) {
            netLock.notifyAll();
        }
        if (worker != null) worker.interrupt();
        try {
            if (cm != null && netCallback != null) cm.unregisterNetworkCallback(netCallback);
        } catch (Exception ignored) {}
        super.onDestroy();
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    private static String decode(String s) {
        if (s == null) return "";
        return (Build.VERSION.SDK_INT >= 24
                ? Html.fromHtml(s, Html.FROM_HTML_MODE_LEGACY) : Html.fromHtml(s)).toString();
    }

    public static void createChannels(Context ctx) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return;
        NotificationManager nm = ctx.getSystemService(NotificationManager.class);
        if (nm == null) return;
        NotificationChannel alerts = new NotificationChannel(ALERT_CHANNEL, "Notificaciones", NotificationManager.IMPORTANCE_HIGH);
        alerts.enableVibration(true);
        nm.createNotificationChannel(alerts);
        NotificationChannel svc = new NotificationChannel(SERVICE_CHANNEL, "Conexión en segundo plano", NotificationManager.IMPORTANCE_MIN);
        svc.setShowBadge(false);
        nm.createNotificationChannel(svc);
    }

    private static PendingIntent openAppIntent(Context ctx, String target, int reqCode) {
        Intent intent = new Intent(ctx, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        if (target != null && !target.isEmpty() && !target.equals("#")) intent.putExtra("target_url", target);
        int f = PendingIntent.FLAG_UPDATE_CURRENT | (Build.VERSION.SDK_INT >= 23 ? PendingIntent.FLAG_IMMUTABLE : 0);
        return PendingIntent.getActivity(ctx, reqCode, intent, f);
    }

    /** Publica UNA notificación nativa con el favicon y nombre del sistema. */
    public static void post(Context ctx, int id, String title, String body, String link) {
        if (Build.VERSION.SDK_INT >= 33
                && ContextCompat.checkSelfPermission(ctx, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            return;
        }
        createChannels(ctx);
        SharedPreferences prefs = ctx.getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        String site = prefs.getString(KEY_SITE_NAME, ctx.getString(R.string.app_name));

        Bitmap icon = null;
        File f = new File(ctx.getFilesDir(), FAVICON_FILE);
        if (f.exists()) icon = BitmapFactory.decodeFile(f.getAbsolutePath());
        if (icon == null) icon = BitmapFactory.decodeResource(ctx.getResources(), R.drawable.ic_launcher_roma);
        Bitmap large = icon != null ? Bitmap.createScaledBitmap(icon, 128, 128, true) : null;
        Bitmap small = icon != null ? Bitmap.createScaledBitmap(icon, 64, 64, true) : null;

        String text = (body == null || body.isEmpty()) ? site : body;
        int nid = id > 0 ? id : (int) (System.currentTimeMillis() & 0x7fffffff);

        NotificationCompat.Builder b = new NotificationCompat.Builder(ctx, ALERT_CHANNEL)
                .setContentTitle(title == null || title.isEmpty() ? site : title)
                .setContentText(text)
                .setSubText(site)
                .setStyle(new NotificationCompat.BigTextStyle().bigText(text))
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setCategory(NotificationCompat.CATEGORY_MESSAGE)
                .setDefaults(NotificationCompat.DEFAULT_ALL)
                .setAutoCancel(true)
                .setContentIntent(openAppIntent(ctx, link, nid));
        if (large != null) b.setLargeIcon(large);
        try {
            if (small != null) b.setSmallIcon(IconCompat.createWithBitmap(small));
            else b.setSmallIcon(R.drawable.ic_launcher_roma);
        } catch (Exception e) {
            b.setSmallIcon(R.drawable.ic_launcher_roma);
        }
        NotificationManager nm = (NotificationManager) ctx.getSystemService(Context.NOTIFICATION_SERVICE);
        if (nm != null) nm.notify(nid, b.build());
    }
}
