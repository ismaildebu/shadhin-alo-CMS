<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_USER = 'user';

    public const ROLE_REPORTER = 'reporter';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_ADMIN => 'অ্যাডমিন',
        self::ROLE_EDITOR => 'সম্পাদক',
        self::ROLE_REPORTER => 'রিপোর্টার',
        self::ROLE_USER => 'সাধারণ ইউজার (প্যানেলে প্রবেশ নেই)',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'bio',
    ];

    // NOTE: `role`, `is_active` and `district_id` are intentionally NOT mass assignable.

    /** In-memory defaults mirror the database defaults. */
    protected $attributes = [
        'role' => self::ROLE_USER,
        'is_active' => true,
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** May enter the admin panel at all (admin, editor, reporter) and is not deactivated. */
    public function canManageCms(): bool
    {
        return $this->is_active
            && in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_REPORTER], true);
    }

    public function isEditorOrAdmin(): bool
    {
        return $this->is_active && in_array($this->role, [self::ROLE_ADMIN, self::ROLE_EDITOR], true);
    }

    public function isAdmin(): bool
    {
        return $this->is_active && $this->role === self::ROLE_ADMIN;
    }

    public function isReporter(): bool
    {
        return $this->is_active && $this->role === self::ROLE_REPORTER;
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'author_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'district_id');
    }

    /** A reporter tied to one district may only report for that district and its upazilas. */
    public function isDistrictCorrespondent(): bool
    {
        return $this->isReporter() && $this->district_id !== null;
    }
}
