<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'from_status_id',
        'to_status_id',
        'performed_by',
        'performed_by_name',
        'note',
        'stage',
        'visible_to_citizen',
        'duration_seconds',
    ];

    protected $casts = [
        'stage' => 'integer',
        'visible_to_citizen' => 'boolean',
        'duration_seconds' => 'integer',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class, 'to_status_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
