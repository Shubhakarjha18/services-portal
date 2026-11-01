<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ApplicationStatusCode;
use App\Enums\RoleName;
use Tests\TestCase;

/**
 * The workflow is declared in config/portal.php, so the configuration itself is
 * part of the product. These checks fail the build if a status, a stage or a
 * role reference is added without wiring it through properly.
 */
final class WorkflowRulesTest extends TestCase
{
    private const TERMINAL = [
        ApplicationStatusCode::Completed,
        ApplicationStatusCode::Rejected,
        ApplicationStatusCode::Cancelled,
    ];

    public function test_every_status_is_declared_in_the_workflow(): void
    {
        $workflow = config('portal.workflow');

        $this->assertSame(
            ApplicationStatusCode::values(),
            array_keys($workflow),
            'Adding an enum case without a workflow entry would leave cases stuck.'
        );
    }

    public function test_every_transition_target_is_a_real_status_reachable_by_a_real_role(): void
    {
        foreach (config('portal.workflow') as $from => $definition) {
            $this->assertNotNull(ApplicationStatusCode::tryFrom($from));
            $this->assertGreaterThanOrEqual(1, $definition['stage']);

            foreach ($definition['to'] as $to => $roles) {
                $this->assertNotNull(
                    ApplicationStatusCode::tryFrom($to),
                    "Edge {$from} -> {$to} points at a status that does not exist."
                );
                $this->assertNotEmpty($roles, "Edge {$from} -> {$to} would be impossible to perform.");

                foreach ($roles as $role) {
                    $this->assertInstanceOf(RoleName::class, $role);
                }
            }
        }
    }

    public function test_closed_cases_have_no_way_back_out(): void
    {
        foreach (self::TERMINAL as $status) {
            $this->assertTrue($status->isTerminal());
            $this->assertSame([], $status->allowedTransitions());
            $this->assertSame(7, $status->stage());
        }

        foreach (array_filter(ApplicationStatusCode::cases(), fn (ApplicationStatusCode $code) => ! $code->isTerminal()) as $status) {
            $this->assertFalse($status->isTerminal());
            $this->assertNotEmpty($status->allowedTargetCodes(), "{$status->value} is open but has no next step.");
        }
    }

    public function test_the_happy_path_gets_progressively_more_complete(): void
    {
        $path = [
            ApplicationStatusCode::Submitted,
            ApplicationStatusCode::UnderReview,
            ApplicationStatusCode::DocumentsRequired,
            ApplicationStatusCode::InProgress,
            ApplicationStatusCode::PendingApproval,
            ApplicationStatusCode::Approved,
            ApplicationStatusCode::Completed,
        ];

        $stages = array_map(fn (ApplicationStatusCode $code) => $code->stage(), $path);

        // DocumentsRequired and InProgress are a loop, so the sequence only has
        // to never go backwards and must finish at the highest stage.
        $this->assertSame(1, $stages[0]);
        $this->assertSame(7, end($stages));
        $this->assertSame($stages, array_values(array_unique($stages)));

        for ($index = 1; $index < count($stages); $index++) {
            $this->assertGreaterThanOrEqual($stages[$index - 1], $stages[$index]);
        }
    }

    public function test_only_an_admin_can_give_the_final_approval(): void
    {
        $edges = ApplicationStatusCode::PendingApproval->allowedTransitions();

        $this->assertSame([RoleName::Admin], $edges[ApplicationStatusCode::Approved->value]);
        $this->assertSame([RoleName::Admin], $edges[ApplicationStatusCode::Rejected->value]);
        $this->assertContains(RoleName::Officer, $edges[ApplicationStatusCode::InProgress->value]);
    }

    public function test_withdrawing_is_the_one_move_an_applicant_owns(): void
    {
        foreach ([ApplicationStatusCode::Submitted, ApplicationStatusCode::UnderReview, ApplicationStatusCode::DocumentsRequired, ApplicationStatusCode::OnHold] as $status) {
            $this->assertContains(
                RoleName::Public,
                $status->allowedTransitions()[ApplicationStatusCode::Cancelled->value],
                "{$status->value} must stay cancellable by the applicant."
            );
        }

        $this->assertNotContains(
            RoleName::Public,
            ApplicationStatusCode::Submitted->allowedTransitions()[ApplicationStatusCode::UnderReview->value],
            'An applicant can never start their own review.'
        );
    }

    public function test_uploads_are_limited_to_office_formats_and_a_private_disk(): void
    {
        $disk = config('filesystems.disks.'.config('portal.documents.disk'));

        $this->assertSame('local', $disk['driver']);
        $this->assertArrayNotHasKey('url', $disk, 'A public URL on the document disk would bypass the audited download route.');

        $this->assertNotContains('exe', config('portal.documents.allowed_mimes'));
        $this->assertContains('pdf', config('portal.documents.allowed_mimes'));
        $this->assertGreaterThan(0, config('portal.documents.max_kilobytes'));
    }

    public function test_the_public_endpoints_are_rate_limited(): void
    {
        foreach (['login', 'register', 'track', 'api'] as $limiter) {
            $this->assertGreaterThan(0, (int) config("portal.rate_limits.{$limiter}"));
        }

        $this->assertLessThan(config('portal.rate_limits.api'), config('portal.rate_limits.track'));
    }
}
