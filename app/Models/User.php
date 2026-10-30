<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;

    public const ROLE_USER = 'user';

    public const ROLE_REPORTER = 'reporter';

    public const ROLE_EDITOR = 'editor';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER_ADMIN = 'super-admin';

    /**
     * Available CMS roles and their display labels.
     *
     * Note: the protected super administrator is identified by the
     * `is_super_admin` flag, not by this list. ROLE_SUPER_ADMIN is kept
     * only for display purposes and can never be assigned from a form.
     *
     * @var array<string, string>
     */
    public const ROLES = [
        self::ROLE_SUPER_ADMIN => 'সুপার অ্যাডমিন',
        self::ROLE_ADMIN => 'অ্যাডমিন',
        self::ROLE_EDITOR => 'সম্পাদক',
        self::ROLE_REPORTER => 'রিপোর্টার',
        self::ROLE_USER => 'সাধারণ ইউজার (প্যানেলে প্রবেশ নেই)',
    ];

    /**
     * The only attributes that may change on the super administrator.
     * Everything else is locked (see booted() and the database triggers).
     *
     * @var list<string>
     */
    private const SUPER_ADMIN_MUTABLE = [
        'password',
        'remember_token',
        'updated_at',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * Privileged attributes (role, is_active, is_super_admin) must never
     * be mass assignable.
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

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => self::ROLE_USER,
        'is_active' => true,
    ];

    /**
     * Attributes hidden during serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Report the super administrator's role as "super-admin".
     *
     * The stored `role` column stays "admin" (the database triggers lock it),
     * so the flag decides what the application sees. Queries such as
     * where('role', 'admin') still read the stored value.
     */
    public function getRoleAttribute(?string $value): ?string
    {
        return ! empty($this->attributes['is_super_admin'])
            ? self::ROLE_SUPER_ADMIN
            : $value;
    }

    /**
     * Determine whether this account is the protected super administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->exists
            && (bool) $this->is_super_admin;
    }

    /**
     * Determine whether the user holds a permission from the role matrix
     * (see App\Support\Permissions). The super administrator holds all.
     */
    public function hasPermission(string $permission): bool
    {
        return (bool) $this->is_active
            && (
                $this->isSuperAdmin()
                || Permissions::roleAllows($this->role, $permission)
            );
    }

    /**
     * Determine whether the user may access the CMS panel.
     */
    public function canManageCms(): bool
    {
        return $this->hasPermission('cms_access');
    }

    /**
     * Determine whether the user has editor-level news rights
     * (the "news_review" permission; admin and editor by default).
     */
    public function isEditorOrAdmin(): bool
    {
        return $this->hasPermission('news_review');
    }

    /**
     * Determine whether the user is an active administrator.
     */
    public function isAdmin(): bool
    {
        return $this->is_active
            && (
                $this->isSuperAdmin()
                || $this->role === self::ROLE_ADMIN
            );
    }

    /**
     * Determine whether the user is an active reporter.
     */
    public function isReporter(): bool
    {
        return $this->is_active
            && $this->role === self::ROLE_REPORTER;
    }

    /**
     * Get news articles authored by this user.
     */
    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'author_id');
    }

    /**
     * Get the assigned district.
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'district_id');
    }

    /**
     * Determine whether this user is a district-based correspondent.
     */
    public function isDistrictCorrespondent(): bool
    {
        return $this->isReporter()
            && $this->district_id !== null;
    }

    /**
     * Protect the super administrator at the model level.
     *
     * This is the first layer. The second layer is the pair of database
     * triggers (protect_super_admin_delete / protect_super_admin_update),
     * which also stop mass deletes and raw queries that bypass model events.
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            if ($user->isSuperAdmin()) {
                throw new \RuntimeException(
                    'The super administrator account cannot be deleted.'
                );
            }
        });

        static::updating(function (User $user): void {
            // Judge by the stored value, so the flag cannot be flipped
            // off and changed in the same save.
            if (! (bool) $user->getOriginal('is_super_admin')) {
                return;
            }

            $illegal = array_diff(
                array_keys($user->getDirty()),
                self::SUPER_ADMIN_MUTABLE
            );

            if ($illegal !== []) {
                throw new \RuntimeException(
                    'Only the super administrator password can be changed.'
                );
            }
        });
    }
}