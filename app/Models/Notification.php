<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A system-wide in-app notification.
 *
 * Every admin sees the same rows. Read state is shared: once one admin reads a
 * notification, `read_at` is set and the unread badge clears for all admins.
 */
class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'data',
        'read_at',
        'read_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }
}
