<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfidCard extends Model
{
    protected $fillable = [
        'uid',
        'user_id',
        'label',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this RFID card.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
