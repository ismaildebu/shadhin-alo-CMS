<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One saved cell of the role/permission matrix.
 * Read and written through App\Support\Permissions.
 */
class RolePermission extends Model
{
    protected $fillable = [
        'role',
        'permission',
        'allowed',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
        ];
    }
}