<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'code',
        'description',
        'fee',
        'sla_days',
        'required_documents',
        'eligibility_notes',
        'is_public',
        'requires_payment',
    ];

    protected $casts = [
        'fee' => 'decimal:2',
        'sla_days' => 'integer',
        'required_documents' => 'array',
        'eligibility_notes' => 'array',
        'is_public' => 'boolean',
        'requires_payment' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function scopePublicCatalog(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** Required document labels with a safe fallback for malformed rows. */
    public function requiredDocumentLabels(): array
    {
        return array_values(array_filter((array) ($this->required_documents ?? [])));
    }
}
