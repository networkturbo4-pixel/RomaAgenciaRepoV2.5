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
import com.google.firebase.messaging.FirebaseMessaging;

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
    private static final int UNLOCK_REQUEST_CODE = 3003;
    private static final String KEY_APP_LOCK = "app_lock_enabled";
    private static final long LOCK_TIMEOUT_MS = 5 * 60 * 1000L;
    private long stoppedAt = 0;
    private boolean unlocking = false;
    private boolean firstStart = true;
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
    private final AtomicInteger notifIdCounter = new AtomicInteger(1000);

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        NotificationService.createChannels(this);
        preferences = SecurePrefs.get(this);
        serverUrl = preferences.getString(KEY_SERVER_URL, getString(R.string.server_url));
        if (serverUrl == null || serverUrl.contains("crm.romaagencia.com")) {
            serverUrl = getString(R.string.server_url);
            preferences.edit().putString(KEY_SERVER_URL, serverUrl).apply();
        }
        currentSiteName = preferences.getString(KEY_SITE_NAME, getString(R.string.app_name));

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
        requestBatteryExemption();
        syncBrandingFromApi();
        checkForUpdate();
        NotificationService.startIfNeeded(this);

        String notifTargetUrl = getIntent() != null ? getIntent().getStringExtra("target_url") : null;
        if ((notifTargetUrl == null || notifTargetUrl.isEmpty()) && getIntent() != null && getIntent().getData() != null) {
            notifTargetUrl = deepLinkToUrl(getIntent().getData());
        }
        hideOfflineScreen();
        if (notifTargetUrl != null && !notifTargetUrl.isEmpty()) {
            webView.loadUrl(resolveFullUrl(notifTargetUrl));
        } else {
            webView.loadUrl(serverUrl);
        }
    }

    /** Enlaces profundos: https://romaagencia.lat/... abren dentro de la app. */
    private String deepLinkToUrl(Uri data) {
        if (data == null || data.getHost() == null) return null;
        Uri base = Uri.parse(serverUrl);
        if (base.getHost() != null && data.getHost().equalsIgnoreCase(base.getHost())) {
            return data.toString();
        }
        return null;
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        if (intent != null) {
            String targetUrl = intent.getStringExtra("target_url");
            if ((targetUrl == null || targetUrl.isEmpty()) && intent.getData() != null) {
                targetUrl = deepLinkToUrl(intent.getData());
            }
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

    /** Puente JS: la notificación nativa la publica SIEMPRE el servicio (una sola vía, sin duplicados). */
    public class RomaJsBridge {
        private volatile String lastKey = "";
        private volatile long lastTime = 0;

        @JavascriptInterface
        public void syncBranding(String siteName, String faviconUrl) {
            bgExecutor.execute(() -> updateBrandingData(siteName, faviconUrl));
        }

        @JavascriptInterface
        public void showNotificationWithId(int id, String title, String body, String targetUrl, String iconUrl, String appName) {
            // Las notificaciones del servidor ya las entrega el servicio; ignorar para evitar duplicados
        }

        @JavascriptInterface
        public void showNotification(String title, String body, String targetUrl, String iconUrl, String appName) {
            // Solo avisos locales (ej. alarma de reunión) con antirrebote
            String key = title + "|" + body;
            long now = System.currentTimeMillis();
            if (key.equals(lastKey) && now - lastTime < 60000) return;
            lastKey = key;
            lastTime = now;
            bgExecutor.execute(() -> NotificationService.post(MainActivity.this, 0, title, body, targetUrl));
        }

        @JavascriptInterface
        public boolean isNativeApp() {
            return true;
        }

        @JavascriptInterface
        public void registerPush(int userId) {
            registerFcmToken(userId);
        }

        @JavascriptInterface
        public void setAppLock(boolean enabled) {
            preferences.edit().putBoolean(KEY_APP_LOCK, enabled).apply();
        }

        @JavascriptInterface
        public void setQuietHours(int startHour, int endHour) {
            preferences.edit()
                    .putInt(NotificationService.KEY_QUIET_START, startHour)
                    .putInt(NotificationService.KEY_QUIET_END, endHour)
                    .apply();
        }

        @JavascriptInterface
        public void setNotifTypes(String csv) {
            preferences.edit().putString(NotificationService.KEY_TYPES, csv == null ? "" : csv.trim()).apply();
        }

        @JavascriptInterface
        public String getAppSettings() {
            try {
                JSONObject o = new JSONObject();
                o.put("app_lock", preferences.getBoolean(KEY_APP_LOCK, true));
                o.put("quiet_start", preferences.getInt(NotificationService.KEY_QUIET_START, -1));
                o.put("quiet_end", preferences.getInt(NotificationService.KEY_QUIET_END, -1));
                o.put("types", preferences.getString(NotificationService.KEY_TYPES, ""));
                return o.toString();
            } catch (Exception e) {
                return "{}";
            }
        }
    }

    /** Desactiva Notification/Push web dentro de la APK para que no haya notificaciones dobles. */
    private static final String DISABLE_WEB_NOTIFS_JS =
            "(function(){try{" +
            "function N(){}; N.permission='denied'; N.requestPermission=function(){return Promise.resolve('denied');};" +
            "Object.defineProperty(window,'Notification',{value:N,configurable:true,writable:true});" +
            "try{delete window.PushManager;}catch(e){}" +
            "if(navigator.serviceWorker){try{Object.defineProperty(navigator,'serviceWorker',{value:undefined,configurable:true});}catch(e){}}" +
            "}catch(e){}})();";

    private void requestBatteryExemption() {
        try {
            android.os.PowerManager pm = (android.os.PowerManager) getSystemService(Context.POWER_SERVICE);
            if (pm != null && !pm.isIgnoringBatteryOptimizations(getPackageName())) {
                Intent i = new Intent(android.provider.Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS);
                i.setData(Uri.parse("package:" + getPackageName()));
                startActivity(i);
            }
        } catch (Exception ignored) {}
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
                view.evaluateJavascript(DISABLE_WEB_NOTIFS_JS, null);
                progressBar.setVisibility(View.VISIBLE);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                super.onPageFinished(view, url);
                progressBar.setVisibility(View.GONE);
                swipeRefreshLayout.setRefreshing(false);
                hideOfflineScreen();
                CookieManager.getInstance().flush();
                view.evaluateJavascript(DISABLE_WEB_NOTIFS_JS, null);
            }

            @Override
            public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
                handler.cancel();
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
        if (requestCode == UNLOCK_REQUEST_CODE) {
            unlocking = false;
            if (resultCode == RESULT_OK) {
                webView.setVisibility(View.VISIBLE);
            } else {
                finish();
            }
            super.onActivityResult(requestCode, resultCode, data);
            return;
        }
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
                NotificationService.startIfNeeded(MainActivity.this);
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
    protected void onStart() {
        super.onStart();
        boolean lockOn = preferences != null && preferences.getBoolean(KEY_APP_LOCK, true);
        if (lockOn && !unlocking) {
            long away = stoppedAt == 0 ? 0 : System.currentTimeMillis() - stoppedAt;
            if (firstStart || away > LOCK_TIMEOUT_MS) {
                promptUnlock();
            }
        }
        firstStart = false;
    }

    @Override
    protected void onStop() {
        super.onStop();
        if (!unlocking) stoppedAt = System.currentTimeMillis();
    }

    /** Registra el token FCM del dispositivo en el servidor (requiere sesión iniciada) y desactiva el servicio de respaldo. */
    private void registerFcmToken(int userId) {
        if (userId <= 0) return;
        try {
            FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
                if (!task.isSuccessful() || task.getResult() == null) return;
                final String token = task.getResult();
                final String marker = userId + "|" + token;
                long last = preferences.getLong("fcm_registered_time", 0);
                if (marker.equals(preferences.getString(NotificationService.KEY_FCM_DONE, ""))
                        && System.currentTimeMillis() - last < 12L * 3600 * 1000) {
                    return;
                }
                bgExecutor.execute(() -> {
                    HttpURLConnection conn = null;
                    try {
                        String cookies = CookieManager.getInstance().getCookie(serverUrl);
                        String base = serverUrl.endsWith("/") ? serverUrl : serverUrl + "/";
                        conn = (HttpURLConnection) new URL(base + "ajax/fcm_register.php").openConnection();
                        conn.setConnectTimeout(8000);
                        conn.setReadTimeout(8000);
                        conn.setRequestMethod("POST");
                        conn.setDoOutput(true);
                        if (cookies != null) conn.setRequestProperty("Cookie", cookies);
                        conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
                        String body = "token=" + java.net.URLEncoder.encode(token, "UTF-8")
                                + "&device=" + java.net.URLEncoder.encode(Build.MANUFACTURER + " " + Build.MODEL, "UTF-8");
                        conn.getOutputStream().write(body.getBytes("UTF-8"));
                        if (conn.getResponseCode() == 200) {
                            BufferedReader r = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                            StringBuilder sb = new StringBuilder();
                            String line;
                            while ((line = r.readLine()) != null) sb.append(line);
                            r.close();
                            if (new JSONObject(sb.toString()).optBoolean("success", false)) {
                                preferences.edit()
                                        .putString(NotificationService.KEY_FCM_DONE, marker)
                                        .putLong("fcm_registered_time", System.currentTimeMillis())
                                        .putBoolean(NotificationService.KEY_FCM_READY, true)
                                        .apply();
                                // FCM activo: ya no hace falta el servicio de segundo plano (ahorra batería)
                                runOnUiThread(() -> stopService(new Intent(MainActivity.this, NotificationService.class)));
                            }
                        }
                    } catch (Exception ignored) {
                    } finally {
                        if (conn != null) conn.disconnect();
                    }
                });
            });
        } catch (Throwable ignored) {}
    }

    /** Busca una versión nueva de la APK en GitHub Releases (máx. cada 6 h) y ofrece descargarla. */
    private void checkForUpdate() {
        bgExecutor.execute(() -> {
            long now = System.currentTimeMillis();
            if (now - preferences.getLong("update_check_time", 0) < 6L * 3600 * 1000) return;
            HttpURLConnection conn = null;
            try {
                conn = (HttpURLConnection) new URL("https://api.github.com/repos/networkturbo4-pixel/RomaAgenciaRepoV2.5/releases/tags/app-apk-latest").openConnection();
                conn.setConnectTimeout(8000);
                conn.setReadTimeout(8000);
                conn.setRequestProperty("Accept", "application/vnd.github+json");
                conn.setRequestProperty("User-Agent", "RomaAndroidApp");
                if (conn.getResponseCode() != 200) return;
                BufferedReader r = new BufferedReader(new InputStreamReader(conn.getInputStream()));
                StringBuilder sb = new StringBuilder();
                String line;
                while ((line = r.readLine()) != null) sb.append(line);
                r.close();
                preferences.edit().putLong("update_check_time", now).apply();

                JSONObject rel = new JSONObject(sb.toString());
                java.util.regex.Matcher m = java.util.regex.Pattern.compile("versionCode:\\s*(\\d+)").matcher(rel.optString("body", ""));
                if (!m.find()) return;
                int remote = Integer.parseInt(m.group(1));
                @SuppressWarnings("deprecation")
                int current = getPackageManager().getPackageInfo(getPackageName(), 0).versionCode;
                if (remote <= current || preferences.getInt("update_dismissed", 0) >= remote) return;

                org.json.JSONArray assets = rel.optJSONArray("assets");
                if (assets == null || assets.length() == 0) return;
                final String apkUrl = assets.getJSONObject(0).optString("browser_download_url", "");
                if (apkUrl.isEmpty()) return;

                runOnUiThread(() -> {
                    if (isFinishing()) return;
                    new AlertDialog.Builder(MainActivity.this)
                            .setTitle("Nueva versión disponible")
                            .setMessage("Hay una actualización de " + currentSiteName + " con mejoras y correcciones.")
                            .setPositiveButton("Actualizar", (d, w) -> startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(apkUrl))))
                            .setNegativeButton("Después", (d, w) -> preferences.edit().putInt("update_dismissed", remote).apply())
                            .show();
                });
            } catch (Exception ignored) {
            } finally {
                if (conn != null) conn.disconnect();
            }
        });
    }

    /** Pide huella / patrón / PIN del teléfono (si tiene bloqueo de pantalla configurado). */    private void promptUnlock() {
        try {
            android.app.KeyguardManager km = (android.app.KeyguardManager) getSystemService(Context.KEYGUARD_SERVICE);
            if (km == null || !km.isDeviceSecure()) return;
            Intent i = km.createConfirmDeviceCredentialIntent(currentSiteName, "Verifica tu identidad para continuar");
            if (i == null) return;
            unlocking = true;
            webView.setVisibility(View.INVISIBLE);
            startActivityForResult(i, UNLOCK_REQUEST_CODE);
        } catch (Exception e) {
            unlocking = false;
            webView.setVisibility(View.VISIBLE);
        }
    }

    @Override
    protected void onPause() {
        super.onPause();
        CookieManager.getInstance().flush();
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
    }
}



