package com.roma.app;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;

/** Reinicia el servicio de notificaciones al encender el teléfono o actualizar la app. */
public class BootReceiver extends BroadcastReceiver {
    @Override
    public void onReceive(Context context, Intent intent) {
        NotificationService.startIfNeeded(context);
    }
}
