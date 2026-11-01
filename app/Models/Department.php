<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'contact_email',
        'default_sla_days',
        'is_active',
    ];

    protected $casts = [
        'default_sla_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
