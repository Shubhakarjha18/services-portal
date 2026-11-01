<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'department_id',
        'phone',
        'national_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Applications submitted by this citizen. */
    public function submissions(): HasMany
    {
        return $this->hasMany(Application::class, 'citizen_id');
    }

    /** Cases assigned to this officer. */
    public function assignedCases(): HasMany
    {
        return $this->hasMany(Application::class, 'assigned_officer_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ApplicationComment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /** Permissions granted through the user's role. */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'permission_role',
            'role_id',
            'permission_id',
            'role_id',
            'id'
        );
    }

    public function roleName(): ?RoleName
    {
        return $this->role?->name;
    }

    public function hasRole(RoleName|string ...$roles): bool
    {
        $current = $this->roleName()?->value;

        if ($current === null) {
            return false;
        }

        $needles = array_map(
            fn (RoleName|string $role): string => $role instanceof RoleName ? $role->value : $role,
            $roles
        );

        return in_array($current, $needles, true);
    }

    public function isStaff(): bool
    {
        return $this->roleName()?->isStaff() ?? false;
    }

    /**
     * Admins pass every ability so newly added permissions are usable without
     * reseeding the pivot table.
     */
    public function hasPermission(string $ability): bool
    {
        if ($this->hasRole(RoleName::Admin)) {
            return true;
        }

        if ($this->relationLoaded('permissions')) {
            return $this->permissions->contains('name', $ability);
        }

        return $this->permissions()->where('name', $ability)->exists();
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role_id', function ($query) {
            $query->select('id')
                ->from('roles')
                ->whereIn('name', array_column(RoleName::staff(), 'value'));
        });
    }

    public function scopeRoleNamed(Builder $query, RoleName|string $role): Builder
    {
        $value = $role instanceof RoleName ? $role->value : $role;

        return $query->whereHas('role', fn (Builder $q) => $q->where('name', $value));
    }

    public function scopeCitizens(Builder $query): Builder
    {
        return $query->whereIn('role_id', function ($query) {
            $query->select('id')->from('roles')->where('name', RoleName::Public->value);
        });
    }
}
