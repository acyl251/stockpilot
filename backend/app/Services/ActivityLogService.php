<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogService
{
    /**
     * Log an action in an authenticated tenant context.
     * Silently swallows any exception so logging never breaks the main flow.
     */
    public static function log(
        string $action,
        string $module,
        string $description,
        array  $meta = []
    ): void {
        try {
            ActivityLog::create([
                'organisation_id' => app('current_organisation_id'),
                'user_id'         => app('current_user')->id,
                'action'          => $action,
                'module'          => $module,
                'description'     => $description,
                'meta'            => $meta ?: null,
                'ip_address'      => request()->ip(),
            ]);
        } catch (\Throwable) {
            // Logging must never break the main request.
        }
    }

    /**
     * Special log for login events — called before auth middleware binds tenant context.
     */
    public static function logLogin(User $user): void
    {
        try {
            // Bypass TenantScope — organisation_id is set explicitly.
            $log = new ActivityLog([
                'organisation_id' => $user->organisation_id,
                'user_id'         => $user->id,
                'action'          => 'login',
                'module'          => 'utilisateur',
                'description'     => "Connexion de {$user->prenom} {$user->nom} ({$user->email})",
                'ip_address'      => request()->ip(),
            ]);
            $log->saveQuietly();
        } catch (\Throwable) {
            // Non-fatal.
        }
    }
}
