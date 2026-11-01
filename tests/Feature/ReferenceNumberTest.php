<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Application;
use App\Support\ReferenceNumberGenerator;
use Tests\PortalTestCase;

/**
 * The reference number is the only handle a citizen gets for a case, so it has
 * to stay unique, ordered inside a year and never reused after a withdrawal.
 */
final class ReferenceNumberTest extends PortalTestCase
{
    public function test_the_first_case_of_a_year_starts_the_sequence_at_one(): void
    {
        $this->assertSame($this->reference(1), app(ReferenceNumberGenerator::class)->generate());
    }

    public function test_the_sequence_increments_with_every_generated_reference(): void
    {
        $generator = app(ReferenceNumberGenerator::class);

        $this->stored(1);
        $this->assertSame($this->reference(2), $generator->generate());

        $this->stored(2);
        $this->assertSame($this->reference(3), $generator->generate());
    }

    public function test_a_withdrawn_case_keeps_its_number(): void
    {
        $withdrawn = $this->stored(7);
        $withdrawn->delete();

        $this->assertTrue($withdrawn->trashed());
        $this->assertSame($this->reference(8), app(ReferenceNumberGenerator::class)->generate());
    }

    public function test_each_year_starts_a_fresh_sequence(): void
    {
        $lastYear = (int) date('Y') - 1;

        Application::factory()->create([
            'submitted_at' => now()->subYear(),
            'reference_number' => sprintf('%s-%s-%06d', $this->prefix(), $lastYear, 42),
        ]);

        $this->assertSame($this->reference(1), app(ReferenceNumberGenerator::class)->generate());
    }

    public function test_two_submissions_never_share_a_reference(): void
    {
        $citizen = $this->citizen();

        $first = $this->submit($citizen)->assertCreated()->json('data.reference_number');
        $second = $this->submit($citizen)->assertCreated()->json('data.reference_number');

        $this->assertMatchesRegularExpression('/^'.preg_quote($this->prefix(), '/').'-\d{4}-\d{6}$/', $first);
        $this->assertNotSame($first, $second);
        $this->assertSame($this->reference(1), $first);
        $this->assertSame($this->reference(2), $second);
    }

    private function prefix(): string
    {
        return (string) config('portal.reference.prefix', 'PSP');
    }

    private function reference(int $sequence): string
    {
        return sprintf('%s-%s-%06d', $this->prefix(), date('Y'), $sequence);
    }

    private function stored(int $sequence): Application
    {
        return Application::factory()->create([
            'reference_number' => $this->reference($sequence),
        ]);
    }
}
