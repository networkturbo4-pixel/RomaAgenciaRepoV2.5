package com.roma.app;

import android.content.SharedPreferences;

import androidx.annotation.NonNull;

import com.google.firebase.messaging.FirebaseMessagingService;
import com.google.firebase.messaging.RemoteMessage;

import java.util.Map;

/** Recibe los push de FCM (incluso con la app cerrada) y muestra UNA notificación nativa. */
public class RomaFirebaseService extends FirebaseMessagingService {

    @Override
    public void onNewToken(@NonNull String token) {
        // Forzar nuevo registro en el servidor la próxima vez que se abra la app con sesión
        SecurePrefs.get(this).edit().putString(NotificationService.KEY_FCM_DONE, "").apply();
    }

    @Override
    public void onMessageReceived(@NonNull RemoteMessage message) {
        Map<String, String> d = message.getData();
        if (d == null || d.isEmpty()) return;

        SharedPreferences prefs = SecurePrefs.get(this);
        if (NotificationService.isQuietNow(prefs)) return;

        String type = d.get("type");
        String types = prefs.getString(NotificationService.KEY_TYPES, "");
        if (!types.isEmpty() && type != null && !(("," + types + ",").contains("," + type + ","))) return;

        int id = 0;
        try {
            id = Integer.parseInt(d.get("id"));
        } catch (Exception ignored) {}
        if (id > 0 && !NotificationService.claim(prefs, id)) return; // ya mostrada (evita duplicados)

        NotificationService.post(this, id, d.get("title"), d.get("message"), d.get("link"));
    }
}
