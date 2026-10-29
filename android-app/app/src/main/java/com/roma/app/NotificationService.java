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
import android.os.Build;
import android.os.IBinder;
import android.os.PowerManager;
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
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;

/**
 * Servicio en primer plano que mantiene las notificaciones en tiempo real aunque la app esté cerrada.
 * Es la ÚNICA fuente de notificaciones nativas (evita duplicados).
 */
public class NotificationService extends Service {

    public static final String PREFS_NAME = "roma_app_prefs";
    public static final String KEY_SERVER_URL = "custom_server_url";
    public static final String KEY_SITE_NAME = "cached_site_name";
    public static final String KEY_LAST_NOTIF_ID = "last_native_notif_id";
    public static final String FAVICON_FILE = "cached_system_favicon.png";

    private static final String ALERT_CHANNEL = "roma_realtime_alerts_v3";
    private static final String SERVICE_CHANNEL = "roma_service_silent";
    private static final int FOREGROUND_ID = 1;
    private static final long POLL_SECONDS = 8;

    private ScheduledExecutorService scheduler;
    private PowerManager.WakeLock wakeLock;
    private volatile boolean firstPoll = true;

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

        PowerManager pm = (PowerManager) getSystemService(Context.POWER_SERVICE);
        if (pm != null) {
            wakeLock = pm.newWakeLock(PowerManager.PARTIAL_WAKE_LOCK, "roma:notif");
            wakeLock.setReferenceCounted(false);
            wakeLock.acquire();
        }

        scheduler = Executors.newSingleThreadScheduledExecutor();
        scheduler.scheduleWithFixedDelay(this::poll, 2, POLL_SECONDS, TimeUnit.SECONDS);
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        return START_STICKY;
    }

    @Override
    public void onTaskRemoved(Intent rootIntent) {
        // Reiniciar el servicio si el usuario cierra la app deslizando
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
        if (scheduler != null) scheduler.shutdownNow();
        if (wakeLock != null && wakeLock.isHeld()) wakeLock.release();
        super.onDestroy();
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    private void poll() {
        HttpURLConnection conn = null;
        try {
            SharedPreferences prefs = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
            String server = prefs.getString(KEY_SERVER_URL, getString(R.string.server_url));
            String cookies = CookieManager.getInstance().getCookie(server);
            if (cookies == null || cookies.isEmpty()) return;

            String base = server.endsWith("/") ? server : server + "/";
            conn = (HttpURLConnection) new URL(base + "ajax/notifications.php?action=get_notifications&limit=10").openConnection();
            conn.setConnectTimeout(6000);
            conn.setReadTimeout(6000);
            conn.setRequestProperty("Cookie", cookies);
            conn.setRequestProperty("Accept", "application/json");
            if (conn.getResponseCode() != 200) return;

            StringBuilder sb = new StringBuilder();
            try (BufferedReader r = new BufferedReader(new InputStreamReader(conn.getInputStream()))) {
                String line;
                while ((line = r.readLine()) != null) sb.append(line);
            }
            JSONObject json = new JSONObject(sb.toString());
            if (!json.optBoolean("success", false)) return;
            JSONArray arr = json.optJSONArray("notifications");
            if (arr == null) return;

            int maxId = 0;
            for (int i = 0; i < arr.length(); i++) {
                maxId = Math.max(maxId, arr.getJSONObject(i).optInt("id", 0));
            }
            if (firstPoll) {
                firstPoll = false;
                if (prefs.getInt(KEY_LAST_NOTIF_ID, -1) < 0) {
                    prefs.edit().putInt(KEY_LAST_NOTIF_ID, maxId).apply();
                    return;
                }
            }

            // De la más antigua a la más reciente
            for (int i = arr.length() - 1; i >= 0; i--) {
                JSONObject n = arr.getJSONObject(i);
                int id = n.optInt("id", 0);
                if (id > 0 && n.optInt("is_read", 1) == 0 && claim(prefs, id)) {
                    post(this, id,
                            decode(n.optString("title", "")),
                            decode(n.optString("message", "")),
                            n.optString("link", ""));
                }
            }
        } catch (Exception ignored) {
        } finally {
            if (conn != null) conn.disconnect();
        }
    }

    private static synchronized boolean claim(SharedPreferences prefs, int id) {
        if (id <= prefs.getInt(KEY_LAST_NOTIF_ID, -1)) return false;
        prefs.edit().putInt(KEY_LAST_NOTIF_ID, id).apply();
        return true;
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
