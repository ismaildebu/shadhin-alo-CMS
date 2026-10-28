<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Advertisement extends Model
{
    protected $fillable = [
        'title', 'placement', 'destination_url', 'status', 'design_data',
        'export_path', 'created_by',
    ];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'design_data' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getExportUrlAttribute(): ?string
    {
        return $this->export_path ? asset('storage/'.$this->export_path) : null;
    }
}
