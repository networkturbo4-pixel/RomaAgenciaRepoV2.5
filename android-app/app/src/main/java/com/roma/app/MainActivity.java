package com.roma.app;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.ActivityManager;
import android.app.DownloadManager;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.Color;
import android.net.Uri;
import android.net.http.SslError;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.provider.MediaStore;
import android.text.Html;
import android.text.InputType;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.GeolocationPermissions;
import android.webkit.JavascriptInterface;
import android.webkit.SslErrorHandler;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.OnBackPressedCallback;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.app.NotificationCompat;
import androidx.core.content.ContextCompat;
import androidx.core.content.FileProvider;
import androidx.core.graphics.drawable.IconCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import org.json.JSONArray;
import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.File;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.text.SimpleDateFormat;
import java.util.Date;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.ScheduledExecutorService;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.atomic.AtomicInteger;

public class MainActivity extends AppCompatActivity {

    private static final int FILE_CHOOSER_RESULT_CODE = 1001;
    private static final int PERMISSION_REQUEST_CODE = 2002;
    private static final String PREFS_NAME = "roma_app_prefs";
    private static final String KEY_SERVER_URL = "custom_server_url";
    private static final String KEY_SITE_NAME = "cached_site_name";
    private static final String KEY_FAVICON_URL = "cached_favicon_url";
    private static final String KEY_LAST_NOTIF_ID = "last_native_notif_id";
    private static final String NOTIF_CHANNEL_ID = "roma_realtime_alerts_v2";
    private static final String CACHED_FAVICON_FILENAME = "cached_system_favicon.png";

    private WebView webView;
    private SwipeRefreshLayout swipeRefreshLayout;
    private ProgressBar progressBar;
    private LinearLayout layoutOffline;
    private ImageView imgOfflineLogo;
    private Button btnRetry;
    private Button btnChangeUrl;
    private TextView txtCurrentUrl;

    private ValueCallback<Uri[]> mFilePathCallback;
    private Uri mCameraPhotoUri;
    private String serverUrl;
    private String currentSiteName = "Roma Agencia";
    private Bitmap cachedFaviconBitmap = null;
    private SharedPreferences preferences;
    private final ExecutorService bgExecutor = Executors.newCachedThreadPool();
    private final ScheduledExecutorService pollerScheduler = Executors.newSingleThreadScheduledExecutor();
    private final AtomicInteger notifIdCounter = new AtomicInteger(1000);

    private volatile int lastNotifiedId = -1;
    private volatile boolean isFirstPoll = true;
    private PendingNotifData pendingNotification = null;

    private static class PendingNotifData {
        final int id;
        final String title;
        final String body;
        final String targetUrl;
        final String appName;

        PendingNotifData(int id, String title, String body, String targetUrl, String appName) {
            this.id = id;
            this.title = title;
            this.body = body;
            this.targetUrl = targetUrl;
            this.appName = appName;
        }
    }

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        createNotificationChannel();

        preferences = getSharedPreferences(PREFS_NAME, MODE_PRIVATE);
        serverUrl = preferences.getString(KEY_SERVER_URL, getString(R.string.server_url));
        if (serverUrl == null || serverUrl.contains("crm.romaagencia.com")) {
            serverUrl = getString(R.string.server_url);
            preferences.edit().putString(KEY_SERVER_URL, serverUrl).apply();
        }
        currentSiteName = preferences.getString(KEY_SITE_NAME, getString(R.string.app_name));
        lastNotifiedId = preferences.getInt(KEY_LAST_NOTIF_ID, -1);

        webView = findViewById(R.id.webView);
        swipeRefreshLayout = findViewById(R.id.swipeRefreshLayout);
        progressBar = findViewById(R.id.progressBar);
        layoutOffline = findViewById(R.id.layoutOffline);
        imgOfflineLogo = findViewById(R.id.imgOfflineLogo);
        btnRetry = findViewById(R.id.btnRetry);
        btnChangeUrl = findViewById(R.id.btnChangeUrl);
        txtCurrentUrl = findViewById(R.id.txtCurrentUrl);

        loadCachedBranding();
        updateCurrentUrlDisplay();

        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);

        webView.setLayerType(View.LAYER_TYPE_HARDWARE, null);

        WebSettings webSettings = webView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);
        webSettings.setAllowFileAccess(true);
        webSettings.setAllowContentAccess(true);
        webSettings.setLoadWithOverviewMode(true);
        webSettings.setUseWideViewPort(true);
        webSettings.setSupportZoom(true);
        webSettings.setBuiltInZoomControls(true);
        webSettings.setDisplayZoomControls(false);
        webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            webSettings.setOffscreenPreRaster(true);
        }
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
            webSettings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        }

        String defaultUA = webSettings.getUserAgentString();
        webSettings.setUserAgentString(defaultUA + " RomaAndroidApp/2.1");

        webView.addJavascriptInterface(new RomaJsBridge(), "RomaNativeApp");

        swipeRefreshLayout.setColorSchemeResources(R.color.primary, R.color.accent);
        swipeRefreshLayout.setOnRefreshListener(() -> webView.reload());

        setupWebViewClient();
        setupWebChromeClient();
        setupDownloadListener();

        btnRetry.setOnClickListener(v -> {
            hideOfflineScreen();
            progressBar.setVisibility(View.VISIBLE);
            webView.loadUrl(serverUrl);
        });

        btnChangeUrl.setOnClickListener(v -> showChangeUrlDialog());

        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                if (layoutOffline.getVisibility() == View.VISIBLE) {
                    finish();
                } else if (webView.canGoBack()) {
                    webView.goBack();
                } else {
                    finish();
                }
            }
        });

        requestAppPermissions();
        syncBrandingFromApi();
        startNativeNotificationPoller();

        String notifTargetUrl = getIntent() != null ? getIntent().getStringExtra("target_url") : null;
        hideOfflineScreen();
        if (notifTargetUrl != null && !notifTargetUrl.isEmpty()) {
            webView.loadUrl(resolveFullUrl(notifTargetUrl));
        } else {
            webView.loadUrl(serverUrl);
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        if (intent != null) {
            String targetUrl = intent.getStringExtra("target_url");
            if (targetUrl != null && !targetUrl.isEmpty() && webView != null) {
                hideOfflineScreen();
                webView.loadUrl(resolveFullUrl(targetUrl));
            }
        }
    }

    private String resolveFullUrl(String pathOrUrl) {
        if (pathOrUrl == null || pathOrUrl.isEmpty() || pathOrUrl.equals("#")) {
            return serverUrl;
        }
        if (pathOrUrl.startsWith("http://") || pathOrUrl.startsWith("https://")) {
            return pathOrUrl;
        }
        String base = serverUrl.endsWith("/") ? serverUrl.substring(0, serverUrl.length() - 1) : serverUrl;
        String rel = pathOrUrl.startsWith("/") ? pathOrUrl.substring(1) : pathOrUrl;
        return base + "/" + rel;
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    NOTIF_CHANNEL_ID,
                    "Notificaciones en Tiempo Real",
                    NotificationManager.IMPORTANCE_HIGH
            );
            channel.setDescription("Alertas instantáneas de tareas, reuniones y avisos de " + currentSiteName);
            channel.enableVibration(true);
            channel.setVibrationPattern(new long[]{0, 250, 120, 250});
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }

    private Bitmap scaleBitmapSafe(Bitmap src, int targetSize) {
        if (src == null) return null;
        try {
            if (src.getWidth() <= targetSize && src.getHeight() <= targetSize) {
                return src;
            }
            return Bitmap.createScaledBitmap(src, targetSize, targetSize, true);
        } catch (Exception e) {
            return src;
        }
    }

    private void loadCachedBranding() {
        try {
            File iconFile = new File(getFilesDir(), CACHED_FAVICON_FILENAME);
            if (iconFile.exists()) {
                Bitmap decoded = BitmapFactory.decodeFile(iconFile.getAbsolutePath());
                if (decoded != null) {
                    cachedFaviconBitmap = scaleBitmapSafe(decoded, 192);
                }
            }
            if (cachedFaviconBitmap == null) {
                Bitmap fallback = BitmapFactory.decodeResource(getResources(), R.drawable.ic_launcher_roma);
                cachedFaviconBitmap = scaleBitmapSafe(fallback, 192);
            }
            applyBrandingToUi();
        } catch (Exception ignored) {}
    }

    private void applyBrandingToUi() {
        runOnUiThread(() -> {
            setTitle(currentSiteName);
            if (cachedFaviconBitmap != null) {
                if (imgOfflineLogo != null) {
                    imgOfflineLogo.setImageBitmap(cachedFaviconBitmap);
                }
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.LOLLIPOP) {
                    try {
                        ActivityManager.TaskDescription taskDesc = new ActivityManager.TaskDescription(
                                currentSiteName,
                                scaleBitmapSafe(cachedFaviconBitmap, 128),
                                Color.parseColor("#4F46E5")
                        );
                        setTaskDescription(taskDesc);
                    } catch (Exception ignored) {}
                }
            }
        });
    }

    private void syncBrandingFromApi() {
        bgExecutor.execute(() -> {
            HttpURLConnection conn = null;
            try {
                String metaUrl = resolveFullUrl("api/app_metadata.php");
                URL url = new URL(metaUrl);
                conn = (HttpURLConnection) url.openConnection();
                conn.setConnectTimeout(6000);
                conn.setReadTimeout(6000);
                conn.setRequestMethod("GET");
                if (conn.getResponseCode() == 200) {
                    BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                    StringBuilder sb = new StringBuilder();
                    String line;
                    while ((line = reader.readLine()) != null) {
                        sb.append(line);
                    }
                    reader.close();

                    JSONObject json = new JSONObject(sb.toString());
                    String siteName = json.optString("site_name", currentSiteName);
                    String faviconUrl = json.optString("favicon_url", "");
                    updateBrandingData(siteName, faviconUrl);
                }
            } catch (Exception ignored) {
            } finally {
                if (conn != null) conn.disconnect();
            }
        });
    }

    private void updateBrandingData(String siteName, String faviconUrl) {
        boolean changed = false;
        if (siteName != null && !siteName.trim().isEmpty() && !siteName.trim().equals(currentSiteName)) {
            currentSiteName = siteName.trim();
            preferences.edit().putString(KEY_SITE_NAME, currentSiteName).apply();
            changed = true;
        }

        if (faviconUrl != null && !faviconUrl.trim().isEmpty()) {
            String fullFaviconUrl = resolveFullUrl(faviconUrl.trim());
            String lastFaviconUrl = preferences.getString(KEY_FAVICON_URL, "");
            File iconFile = new File(getFilesDir(), CACHED_FAVICON_FILENAME);

            if (!fullFaviconUrl.equals(lastFaviconUrl) || !iconFile.exists()) {
                Bitmap downloaded = downloadBitmap(fullFaviconUrl);
                if (downloaded != null) {
                    Bitmap scaled = scaleBitmapSafe(downloaded, 192);
                    cachedFaviconBitmap = scaled;
                    try (FileOutputStream fos = new FileOutputStream(iconFile)) {
                        scaled.compress(Bitmap.CompressFormat.PNG, 100, fos);
                        preferences.edit().putString(KEY_FAVICON_URL, fullFaviconUrl).apply();
                        changed = true;
                    } catch (Exception ignored) {}
                }
            }
        }

        if (changed) {
            applyBrandingToUi();
        }
    }

    private Bitmap downloadBitmap(String urlString) {
        HttpURLConnection conn = null;
        try {
            URL url = new URL(urlString);
            conn = (HttpURLConnection) url.openConnection();
            conn.setConnectTimeout(6000);
            conn.setReadTimeout(6000);
            conn.setDoInput(true);
            conn.connect();
            if (conn.getResponseCode() == 200) {
                InputStream input = conn.getInputStream();
                return BitmapFactory.decodeStream(input);
            }
        } catch (Exception ignored) {
        } finally {
            if (conn != null) conn.disconnect();
        }
        return null;
    }

    /**
     * Poller nativo en segundo plano que verifica notificaciones nuevas cada 6 segundos
     * usando las cookies de sesión del usuario autenticado en el WebView.
     */
    private void startNativeNotificationPoller() {
        pollerScheduler.scheduleWithFixedDelay(() -> {
            HttpURLConnection conn = null;
            try {
                String cookies = CookieManager.getInstance().getCookie(serverUrl);
                if (cookies == null || cookies.isEmpty()) {
                    return;
                }

                String checkUrl = resolveFullUrl("ajax/notifications.php?action=get_notifications&limit=1");
                URL url = new URL(checkUrl);
                conn = (HttpURLConnection) url.openConnection();
                conn.setConnectTimeout(5000);
                conn.setReadTimeout(5000);
                conn.setRequestMethod("GET");
                conn.setRequestProperty("Cookie", cookies);
                conn.setRequestProperty("Accept", "application/json");

                if (conn.getResponseCode() == 200) {
                    BufferedReader reader = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                    StringBuilder sb = new StringBuilder();
                    String line;
                    while ((line = reader.readLine()) != null) {
                        sb.append(line);
                    }
                    reader.close();

                    JSONObject json = new JSONObject(sb.toString());
                    if (json.optBoolean("success", false)) {
                        JSONArray arr = json.optJSONArray("notifications");
                        if (arr != null && arr.length() > 0) {
                            JSONObject latest = arr.getJSONObject(0);
                            int id = latest.optInt("id", 0);
                            int isRead = latest.optInt("is_read", 1);

                            if (isFirstPoll) {
                                isFirstPoll = false;
                                if (lastNotifiedId < 0 && id > 0) {
                                    saveLastNotifiedId(id);
                                }
                                return;
                            }

                            if (id > 0 && isRead == 0 && id > lastNotifiedId) {
                                saveLastNotifiedId(id);
                                String rawTitle = decodeHtmlEntities(latest.optString("title", "Nueva Notificación"));
                                String rawMsg = decodeHtmlEntities(latest.optString("message", ""));
                                String link = latest.optString("link", "index.php");
                                postNativeNotification(id, rawTitle, rawMsg, link, currentSiteName);
                            }
                        } else if (isFirstPoll) {
                            isFirstPoll = false;
                            if (lastNotifiedId < 0) {
                                saveLastNotifiedId(0);
                            }
                        }
                    }
                }
            } catch (Exception ignored) {
            } finally {
                if (conn != null) conn.disconnect();
            }
        }, 4, 6, TimeUnit.SECONDS);
    }

    private String decodeHtmlEntities(String text) {
        if (text == null || text.isEmpty()) return "";
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
            return Html.fromHtml(text, Html.FROM_HTML_MODE_LEGACY).toString();
        } else {
            return Html.fromHtml(text).toString();
        }
    }

    private synchronized void saveLastNotifiedId(int id) {
        if (id > lastNotifiedId) {
            lastNotifiedId = id;
            preferences.edit().putInt(KEY_LAST_NOTIF_ID, id).apply();
        }
    }

    private void postNativeNotification(int notifId, String title, String body, String targetUrl, String appName) {
        String sysName = (appName != null && !appName.trim().isEmpty()) ? appName.trim() : currentSiteName;

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(MainActivity.this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                pendingNotification = new PendingNotifData(notifId, title, body, targetUrl, sysName);
                runOnUiThread(() -> ActivityCompat.requestPermissions(
                        MainActivity.this,
                        new String[]{Manifest.permission.POST_NOTIFICATIONS},
                        PERMISSION_REQUEST_CODE
                ));
                return;
            }
        }

        Bitmap baseBitmap = cachedFaviconBitmap;
        if (baseBitmap == null) {
            baseBitmap = BitmapFactory.decodeResource(getResources(), R.drawable.ic_launcher_roma);
        }
        Bitmap largeIconBmp = scaleBitmapSafe(baseBitmap, 128);
        Bitmap smallIconBmp = scaleBitmapSafe(baseBitmap, 64);

        int uniqueIntId = (notifId > 0) ? notifId : notifIdCounter.incrementAndGet();

        Intent intent = new Intent(MainActivity.this, MainActivity.class);
        intent.setFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP | Intent.FLAG_ACTIVITY_CLEAR_TOP);
        if (targetUrl != null && !targetUrl.isEmpty() && !targetUrl.equals("#")) {
            intent.putExtra("target_url", targetUrl);
        }

        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }
        PendingIntent pendingIntent = PendingIntent.getActivity(
                MainActivity.this,
                uniqueIntId,
                intent,
                flags
        );

        String cleanTitle = (title != null && !title.isEmpty()) ? title : sysName;
        String cleanBody = (body != null && !body.isEmpty()) ? body : "Tienes una nueva notificación en " + sysName;

        NotificationCompat.Builder builder = new NotificationCompat.Builder(MainActivity.this, NOTIF_CHANNEL_ID)
                .setContentTitle(cleanTitle)
                .setContentText(cleanBody)
                .setSubText(sysName)
                .setStyle(new NotificationCompat.BigTextStyle().bigText(cleanBody).setSummaryText(sysName))
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setCategory(NotificationCompat.CATEGORY_MESSAGE)
                .setDefaults(NotificationCompat.DEFAULT_ALL)
                .setAutoCancel(true)
                .setContentIntent(pendingIntent);

        if (largeIconBmp != null) {
            builder.setLargeIcon(largeIconBmp);
        }
        if (smallIconBmp != null) {
            try {
                builder.setSmallIcon(IconCompat.createWithBitmap(smallIconBmp));
            } catch (Exception e) {
                builder.setSmallIcon(R.drawable.ic_launcher_roma);
            }
        } else {
            builder.setSmallIcon(R.drawable.ic_launcher_roma);
        }

        NotificationManager manager = (NotificationManager) getSystemService(Context.NOTIFICATION_SERVICE);
        if (manager != null) {
            try {
                manager.notify(uniqueIntId, builder.build());
            } catch (Exception e) {
                // Fallback seguro con icono de recurso en caso de restricción del fabricante
                builder.setSmallIcon(R.drawable.ic_launcher_roma);
                manager.notify(uniqueIntId, builder.build());
            }
        }
    }

    public class RomaJsBridge {
        @JavascriptInterface
        public void syncBranding(String siteName, String faviconUrl) {
            bgExecutor.execute(() -> updateBrandingData(siteName, faviconUrl));
        }

        @JavascriptInterface
        public void showNotificationWithId(int id, String title, String body, String targetUrl, String iconUrl, String appName) {
            bgExecutor.execute(() -> {
                if (id > 0) {
                    if (id <= lastNotifiedId) return;
                    saveLastNotifiedId(id);
                }
                String sysName = (appName != null && !appName.trim().isEmpty()) ? appName.trim() : currentSiteName;
                if (iconUrl != null && !iconUrl.trim().isEmpty()) {
                    updateBrandingData(sysName, iconUrl);
                }
                postNativeNotification(id, title, body, targetUrl, sysName);
            });
        }

        @JavascriptInterface
        public void showNotification(String title, String body, String targetUrl, String iconUrl, String appName) {
            bgExecutor.execute(() -> {
                String sysName = (appName != null && !appName.trim().isEmpty()) ? appName.trim() : currentSiteName;
                if (iconUrl != null && !iconUrl.trim().isEmpty()) {
                    updateBrandingData(sysName, iconUrl);
                }
                postNativeNotification(notifIdCounter.incrementAndGet(), title, body, targetUrl, sysName);
            });
        }
    }

    private void injectRealtimeNotificationBridge(WebView view) {
        String js = "(function(){" +
                "if(window.__romaNativeHooked) return;" +
                "window.__romaNativeHooked = true;" +
                "var sysTitle = document.title ? document.title.split('|')[0].trim() : 'Roma Agencia';" +
                "var favEl = document.querySelector('link[rel*=\"icon\"]');" +
                "var favHref = favEl ? favEl.href : '';" +
                "if(window.RomaNativeApp && window.RomaNativeApp.syncBranding){" +
                "  window.RomaNativeApp.syncBranding(window.SYSTEM_SITE_NAME || sysTitle, window.SYSTEM_FAVICON || favHref);" +
                "}" +
                "var origFetch = window.fetch;" +
                "if(origFetch){" +
                "  window.fetch = function(u, c){" +
                "    var p = origFetch.apply(this, arguments);" +
                "    try {" +
                "      var uStr = typeof u === 'string' ? u : (u && u.url ? u.url : '');" +
                "      if(uStr.indexOf('action=send_test') !== -1){" +
                "        p.then(function(r){ return r.clone().json(); }).then(function(d){" +
                "          if(d && d.success && window.RomaNativeApp){" +
                "            var n = d.notification || {};" +
                "            window.RomaNativeApp.showNotification(" +
                "              n.title || 'Notificación de Prueba 🔔'," +
                "              n.message || '¡Excelente! Las notificaciones en tiempo real están activas.'," +
                "              n.link || 'index.php'," +
                "              window.SYSTEM_FAVICON || favHref," +
                "              window.SYSTEM_SITE_NAME || sysTitle" +
                "            );" +
                "          }" +
                "        }).catch(function(){});" +
                "      }" +
                "    } catch(e){}" +
                "    return p;" +
                "  };" +
                "}" +
                "})();";
        view.evaluateJavascript(js, null);
    }

    private void updateCurrentUrlDisplay() {
        if (txtCurrentUrl != null) {
            txtCurrentUrl.setText("URL: " + serverUrl);
        }
    }

    private void showChangeUrlDialog() {
        AlertDialog.Builder builder = new AlertDialog.Builder(this);
        builder.setTitle("Configurar URL de " + currentSiteName);
        builder.setMessage("Escribe la dirección web donde está alojado tu sistema (con https:// o http://):");

        final EditText input = new EditText(this);
        input.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI);
        input.setText(serverUrl);
        input.setSelection(serverUrl.length());
        builder.setView(input);

        builder.setPositiveButton("Guardar y Conectar", (dialog, which) -> {
            String newUrl = input.getText().toString().trim();
            if (!newUrl.isEmpty()) {
                if (!newUrl.startsWith("http://") && !newUrl.startsWith("https://")) {
                    newUrl = "https://" + newUrl;
                }
                serverUrl = newUrl;
                preferences.edit().putString(KEY_SERVER_URL, serverUrl).apply();
                updateCurrentUrlDisplay();
                hideOfflineScreen();
                progressBar.setVisibility(View.VISIBLE);
                syncBrandingFromApi();
                webView.loadUrl(serverUrl);
                Toast.makeText(this, "Conectando a: " + serverUrl, Toast.LENGTH_SHORT).show();
            }
        });

        builder.setNegativeButton("Cancelar", (dialog, which) -> dialog.cancel());
        builder.show();
    }

    private void setupWebViewClient() {
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                super.onPageStarted(view, url, favicon);
                progressBar.setVisibility(View.VISIBLE);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                progressBar.setVisibility(View.GONE);
                swipeRefreshLayout.setRefreshing(false);
                hideOfflineScreen();
                CookieManager.getInstance().flush();
                injectRealtimeNotificationBridge(view);
            }

            @Override
            public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                handler.proceed();
            }

            @Override
            public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
                if (request != null && request.isForMainFrame()) {
                    progressBar.setVisibility(View.GONE);
                    swipeRefreshLayout.setRefreshing(false);
                    showOfflineScreen();
                }
            }

            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                String url = request.getUrl().toString();
                return handleExternalUrls(url);
            }

            @SuppressWarnings("deprecation")
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                return handleExternalUrls(url);
            }
        });
    }

    private boolean handleExternalUrls(String url) {
        if (url == null) return false;

        if (url.startsWith("tel:") || url.startsWith("mailto:") || url.startsWith("sms:") ||
            url.startsWith("whatsapp:") || url.startsWith("https://wa.me/")) {
            try {
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                startActivity(intent);
                return true;
            } catch (Exception e) {
                Toast.makeText(this, "No se encontró aplicación para abrir este enlace", Toast.LENGTH_SHORT).show();
                return true;
            }
        }
        return false;
    }

    private void setupWebChromeClient() {
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onReceivedIcon(WebView view, Bitmap icon) {
                super.onReceivedIcon(view, icon);
                if (icon != null && icon.getWidth() >= 32) {
                    cachedFaviconBitmap = scaleBitmapSafe(icon, 192);
                    applyBrandingToUi();
                }
            }

            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                if (newProgress < 100) {
                    progressBar.setVisibility(View.VISIBLE);
                    progressBar.setProgress(newProgress);
                } else {
                    progressBar.setVisibility(View.GONE);
                }
            }

            @Override
            public void onGeolocationPermissionsShowPrompt(String origin, GeolocationPermissions.Callback callback) {
                callback.invoke(origin, true, false);
            }

            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> filePathCallback, FileChooserParams fileChooserParams) {
                if (mFilePathCallback != null) {
                    mFilePathCallback.onReceiveValue(null);
                }
                mFilePathCallback = filePathCallback;

                Intent takePictureIntent = new Intent(MediaStore.ACTION_IMAGE_CAPTURE);
                if (takePictureIntent.resolveActivity(getPackageManager()) != null) {
                    File photoFile = null;
                    try {
                        photoFile = createImageFile();
                    } catch (IOException ex) {
                        ex.printStackTrace();
                    }
                    if (photoFile != null) {
                        mCameraPhotoUri = FileProvider.getUriForFile(
                                MainActivity.this,
                                getPackageName() + ".fileprovider",
                                photoFile
                        );
                        takePictureIntent.putExtra(MediaStore.EXTRA_OUTPUT, mCameraPhotoUri);
                    } else {
                        takePictureIntent = null;
                    }
                }

                Intent contentSelectionIntent = new Intent(Intent.ACTION_GET_CONTENT);
                contentSelectionIntent.addCategory(Intent.CATEGORY_OPENABLE);
                contentSelectionIntent.setType("*/*");

                Intent[] intentArray;
                if (takePictureIntent != null) {
                    intentArray = new Intent[]{takePictureIntent};
                } else {
                    intentArray = new Intent[0];
                }

                Intent chooserIntent = new Intent(Intent.ACTION_CHOOSER);
                chooserIntent.putExtra(Intent.EXTRA_INTENT, contentSelectionIntent);
                chooserIntent.putExtra(Intent.EXTRA_TITLE, "Seleccionar archivo o foto");
                chooserIntent.putExtra(Intent.EXTRA_INITIAL_INTENTS, intentArray);

                startActivityForResult(chooserIntent, FILE_CHOOSER_RESULT_CODE);
                return true;
            }
        });
    }

    private File createImageFile() throws IOException {
        String timeStamp = new SimpleDateFormat("yyyyMMdd_HHmmss", Locale.getDefault()).format(new Date());
        String imageFileName = "JPEG_" + timeStamp + "_";
        File storageDir = getExternalFilesDir(Environment.DIRECTORY_PICTURES);
        return File.createTempFile(imageFileName, ".jpg", storageDir);
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        if (requestCode == FILE_CHOOSER_RESULT_CODE) {
            if (mFilePathCallback != null) {
                Uri[] results = null;
                if (resultCode == RESULT_OK) {
                    if (data == null || data.getData() == null) {
                        if (mCameraPhotoUri != null) {
                            results = new Uri[]{mCameraPhotoUri};
                        }
                    } else {
                        String dataString = data.getDataString();
                        if (dataString != null) {
                            results = new Uri[]{Uri.parse(dataString)};
                        }
                    }
                }
                mFilePathCallback.onReceiveValue(results);
                mFilePathCallback = null;
            }
        }
        super.onActivityResult(requestCode, resultCode, data);
    }

    private void setupDownloadListener() {
        webView.setDownloadListener(new DownloadListener() {
            @Override
            public void onDownloadStart(String url, String userAgent, String contentDisposition, String mimeType, long contentLength) {
                try {
                    DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
                    request.setMimeType(mimeType);
                    String cookies = CookieManager.getInstance().getCookie(url);
                    request.addRequestHeader("cookie", cookies);
                    request.addRequestHeader("User-Agent", userAgent);
                    request.setDescription("Descargando archivo desde " + currentSiteName + "...");
                    String fileName = URLUtil.guessFileName(url, contentDisposition, mimeType);
                    request.setTitle(fileName);
                    request.allowScanningByMediaScanner();
                    request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
                    request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, fileName);

                    DownloadManager dm = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
                    if (dm != null) {
                        dm.enqueue(request);
                        Toast.makeText(getApplicationContext(), "Descarga iniciada: " + fileName, Toast.LENGTH_SHORT).show();
                    }
                } catch (Exception e) {
                    Toast.makeText(getApplicationContext(), "Error al iniciar descarga: " + e.getMessage(), Toast.LENGTH_SHORT).show();
                }
            }
        });
    }

    private void requestAppPermissions() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            if (ContextCompat.checkSelfPermission(this, Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
                ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.POST_NOTIFICATIONS}, PERMISSION_REQUEST_CODE);
            }
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == PERMISSION_REQUEST_CODE) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                if (pendingNotification != null) {
                    PendingNotifData p = pendingNotification;
                    pendingNotification = null;
                    bgExecutor.execute(() -> postNativeNotification(p.id, p.title, p.body, p.targetUrl, p.appName));
                }
            }
        }
    }

    private void showOfflineScreen() {
        layoutOffline.setVisibility(View.VISIBLE);
        webView.setVisibility(View.GONE);
        updateCurrentUrlDisplay();
    }

    private void hideOfflineScreen() {
        layoutOffline.setVisibility(View.GONE);
        webView.setVisibility(View.VISIBLE);
    }

    @Override
    protected void onPause() {
        super.onPause();
        CookieManager.getInstance().flush();
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        pollerScheduler.shutdownNow();
    }
}
