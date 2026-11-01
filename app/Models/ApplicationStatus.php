<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatusCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApplicationStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'sort_order',
        'color',
        'is_terminal',
        'is_citizen_visible',
        'counts_as_open',
    ];

    protected $casts = [
        'code' => ApplicationStatusCode::class,
        'sort_order' => 'integer',
        'is_terminal' => 'boolean',
        'is_citizen_visible' => 'boolean',
        'counts_as_open' => 'boolean',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function transitionsIn(): HasMany
    {
        return $this->hasMany(ApplicationTransition::class, 'to_status_id');
    }
}
