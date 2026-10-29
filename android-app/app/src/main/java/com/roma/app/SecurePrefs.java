package com.roma.app;

import android.content.Context;
import android.content.SharedPreferences;

import androidx.security.crypto.EncryptedSharedPreferences;
import androidx.security.crypto.MasterKey;

import java.util.Map;

/**
 * Preferencias cifradas (AES-256). Migra automáticamente las preferencias antiguas en texto plano.
 * Si el dispositivo no soporta el cifrado, usa las preferencias normales para no romper la app.
 */
public final class SecurePrefs {
    private static SharedPreferences cached;

    private SecurePrefs() {}

    public static synchronized SharedPreferences get(Context ctx) {
        if (cached != null) return cached;
        Context app = ctx.getApplicationContext();
        SharedPreferences plain = app.getSharedPreferences(NotificationService.PREFS_NAME, Context.MODE_PRIVATE);
        try {
            MasterKey key = new MasterKey.Builder(app)
                    .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
                    .build();
            SharedPreferences enc = EncryptedSharedPreferences.create(
                    app,
                    "roma_secure_prefs",
                    key,
                    EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                    EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM);

            Map<String, ?> old = plain.getAll();
            if (!old.isEmpty()) {
                SharedPreferences.Editor e = enc.edit();
                for (Map.Entry<String, ?> en : old.entrySet()) {
                    Object v = en.getValue();
                    if (v instanceof String) e.putString(en.getKey(), (String) v);
                    else if (v instanceof Integer) e.putInt(en.getKey(), (Integer) v);
                    else if (v instanceof Boolean) e.putBoolean(en.getKey(), (Boolean) v);
                    else if (v instanceof Long) e.putLong(en.getKey(), (Long) v);
                    else if (v instanceof Float) e.putFloat(en.getKey(), (Float) v);
                }
                e.apply();
                plain.edit().clear().apply();
            }
            cached = enc;
        } catch (Throwable t) {
            cached = plain;
        }
        return cached;
    }
}
