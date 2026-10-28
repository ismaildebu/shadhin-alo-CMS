<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoChannel extends Model
{
    protected $fillable = ['channel_id', 'name', 'url', 'auto_publish', 'last_synced_at', 'last_error', 'created_by'];

    protected function casts(): array
    {
        return [
            'auto_publish' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }
}