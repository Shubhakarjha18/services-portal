<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ApplicationStatusCode;
use App\Models\Application;
use Tests\PortalTestCase;

/**
 * Dashboard counters. The same numbers feed the JSON endpoint and the Inertia
 * page, so they are asserted once here and reused by the front-end tests.
 */
final class DashboardTest extends PortalTestCase
{
    public function test_a_citizen_only_sees_their_own_numbers(): void
    {
        $citizen = $this->citizen();
        Application::factory()->forCitizen($citizen)->count(2)->create();
        Application::factory()->count(5)->create();

        $stats = $this->apiAs($citizen, 'get', '/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.totals.all', 2)
            ->json('data');

        $this->assertSame(2, $stats['totals']['open']);
        $this->assertSame([], $stats['by_department'], 'Departmental breakdowns are staff only.');
        $this->assertNull($stats['unassigned']);
        $this->assertNull($stats['my_queue']);
        $this->assertArrayHasKey('statuses', $this->apiAs($citizen, 'get', '/api/v1/dashboard/stats')->json('meta'));
    }

    public function test_overdue_and_due_soon_buckets_are_counted_separately(): void
    {
        $admin = $this->admin();

        Application::factory()->underReview()->overdue()->create();
        Application::factory()->underReview()->create(['due_at' => now()->addDay()]);
        Application::factory()->underReview()->create(['due_at' => now()->addDays(40)]);
        Application::factory()->completed()->create();

        $totals = $this->apiAs($admin, 'get', '/api/v1/dashboard/stats')->assertOk()->json('data.totals');

        $this->assertSame(4, $totals['all']);
        $this->assertSame(3, $totals['open']);
        $this->assertSame(1, $totals['overdue']);
        $this->assertSame(1, $totals['due_soon']);
        $this->assertSame(1, $totals['closed']);
    }

    public function test_the_officer_view_reports_a_personal_queue_and_unassigned_work(): void
    {
        $officer = $this->officer();

        Application::factory()->underReview()->create([
            'department_id' => $officer->department_id,
            'assigned_officer_id' => $officer->id,
        ]);
        Application::factory()->submitted()->create(['department_id' => $officer->department_id]);
        Application::factory()->submitted()->create();

        $stats = $this->apiAs($officer, 'get', '/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.totals.all', 2)
            ->assertJsonPath('data.my_queue', 1)
            ->assertJsonPath('data.unassigned', 1)
            ->json('data');

        $this->assertNotEmpty($stats['by_department']);

        $byStatus = collect($stats['by_status'])->mapWithKeys(fn (array $row) => [$row['code'] => $row['count']])->all();
        ksort($byStatus);

        $this->assertSame(['submitted' => 1, 'under_review' => 1], $byStatus);
    }

    public function test_the_workload_report_is_staff_only(): void
    {
        $officer = $this->officer();
        // due_at stays in the future so only the explicit overdue fixture is counted late.
        Application::factory()->underReview()->create([
            'department_id' => $officer->department_id,
            'assigned_officer_id' => $officer->id,
            'due_at' => now()->addDays(10),
        ]);
        Application::factory()->underReview()->overdue()->create([
            'department_id' => $officer->department_id,
            'assigned_officer_id' => $officer->id,
        ]);

        $rows = $this->apiAs($this->admin(), 'get', '/api/v1/dashboard/workload')
            ->assertOk()
            ->json('data');

        $mine = collect($rows)->firstWhere('id', $officer->id);

        $this->assertNotNull($mine);
        $this->assertSame(2, $mine['open_cases']);
        $this->assertSame(1, $mine['overdue_cases']);
        $this->assertSame(0, $mine['closed_cases']);

        $this->apiAs($this->citizen(), 'get', '/api/v1/dashboard/workload')->assertForbidden();
    }

    public function test_the_submission_trend_only_counts_the_last_thirty_days(): void
    {
        $admin = $this->admin();

        Application::factory()->create(['submitted_at' => now()->subDays(3)]);
        Application::factory()->create(['submitted_at' => now()->subDays(60)]);

        $trend = $this->apiAs($admin, 'get', '/api/v1/dashboard/stats')->json('data.submissions_last_30_days');

        $this->assertCount(1, $trend);
        $this->assertSame(1, reset($trend));
    }

    public function test_the_status_catalog_is_returned_for_rendering_the_legend(): void
    {
        $meta = $this->apiAs($this->citizen(), 'get', '/api/v1/dashboard/stats')->assertOk()->json('meta');

        $this->assertCount(count(ApplicationStatusCode::cases()), $meta['statuses']);
        $this->assertArrayHasKey('generated_at', $meta);
    }
}
