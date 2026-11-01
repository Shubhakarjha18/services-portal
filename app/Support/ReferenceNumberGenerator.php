<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Application;

/**
 * Builds citizen-facing tracking ids in the form PSP-2026-000123.
 *
 * The sequence is zero padded so the lexicographically greatest reference for
 * a year is also the numerically greatest one, which keeps the lookup portable
 * across MySQL and the in-memory SQLite used by the test suite.
 */
class ReferenceNumberGenerator
{
    public function __construct(private readonly int $maxAttempts = 5) {}

    public function generate(): string
    {
        $prefix = config('portal.reference.prefix', 'PSP');
        $year = now()->format('Y');

        for ($attempt = 0; $attempt < $this->maxAttempts; $attempt++) {
            $reference = $this->format($prefix, $year, $this->nextSequence($prefix, $year) + $attempt);

            if (! Application::withTrashed()->where('reference_number', $reference)->exists()) {
                return $reference;
            }
        }

        return $this->format($prefix, $year, now()->format('His'));
    }

    private function format(string $prefix, string $year, int $sequence): string
    {
        return sprintf('%s-%s-%06d', $prefix, $year, $sequence);
    }

    private function nextSequence(string $prefix, string $year): int
    {
        $highest = Application::withTrashed()
            ->where('reference_number', 'like', "{$prefix}-{$year}-%")
            ->max('reference_number');

        if (! is_string($highest)) {
            return 1;
        }

        return (int) substr($highest, -6) + 1;
    }
}
