<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Support\Audit\AuditReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationDocument extends Model implements AuditReference
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'application_id',
        'uploaded_by',
        'label',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'size_bytes',
        'checksum',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => DocumentStatus::class,
        'size_bytes' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function auditReference(): ?string
    {
        return $this->application?->reference_number;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isVerified(): bool
    {
        return $this->status === DocumentStatus::Accepted;
    }
}
