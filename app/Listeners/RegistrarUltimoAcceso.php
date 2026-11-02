<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

/**
 * Registra el último acceso exitoso del usuario.
 *
 * El dato alimenta el KPI de seguridad "usuarios inactivos > 30 días".
 */
class RegistrarUltimoAcceso
{
    public function handle(Login $event): void
    {
        $event->user->forceFill([
            'last_login_at' => now(),
        ])->save();
    }
}
