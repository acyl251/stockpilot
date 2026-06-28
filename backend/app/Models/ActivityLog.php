<?php

namespace App\Models;

class ActivityLog extends BaseModel
{
    // Logs are immutable — no updated_at column.
    const UPDATED_AT = null;

    protected $fillable = [
        'organisation_id',
        'user_id',
        'action',
        'module',
        'description',
        'meta',
        'ip_address',
    ];

    protected $casts = [
        'meta'       => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
