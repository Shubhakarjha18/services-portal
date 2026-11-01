<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApplicationStatusCode;
use App\Models\ApplicationStatus;
use App\Models\Department;
use App\Models\ServiceType;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->statuses();
        $this->departmentsAndServices();
    }

    private function statuses(): void
    {
        $definitions = [
            ['code' => ApplicationStatusCode::Submitted, 'color' => 'blue', 'sort' => 10, 'open' => true, 'desc' => 'The application reached the portal and has a tracking number.'],
            ['code' => ApplicationStatusCode::UnderReview, 'color' => 'indigo', 'sort' => 20, 'open' => true, 'desc' => 'An officer is checking the submission against the service requirements.'],
            ['code' => ApplicationStatusCode::DocumentsRequired, 'color' => 'amber', 'sort' => 30, 'open' => true, 'desc' => 'Additional paperwork is needed before the case can move on.'],
            ['code' => ApplicationStatusCode::InProgress, 'color' => 'cyan', 'sort' => 40, 'open' => true, 'desc' => 'The department is producing the requested document or service.'],
            ['code' => ApplicationStatusCode::OnHold, 'color' => 'gray', 'sort' => 45, 'open' => true, 'desc' => 'Processing is paused, typically for an external verification.'],
            ['code' => ApplicationStatusCode::PendingApproval, 'color' => 'purple', 'sort' => 50, 'open' => true, 'desc' => 'Awaiting an administrator sign-off.'],
            ['code' => ApplicationStatusCode::Approved, 'color' => 'emerald', 'sort' => 60, 'open' => true, 'desc' => 'Approved; the document is being issued.'],
            ['code' => ApplicationStatusCode::Completed, 'color' => 'green', 'sort' => 70, 'open' => false, 'terminal' => true, 'desc' => 'Closed successfully and collectable.'],
            ['code' => ApplicationStatusCode::Rejected, 'color' => 'red', 'sort' => 80, 'open' => false, 'terminal' => true, 'desc' => 'Refused. The reason is recorded in the timeline.'],
            ['code' => ApplicationStatusCode::Cancelled, 'color' => 'slate', 'sort' => 90, 'open' => false, 'terminal' => true, 'desc' => 'Withdrawn by the applicant.'],
        ];

        foreach ($definitions as $definition) {
            /** @var ApplicationStatusCode $code */
            $code = $definition['code'];

            ApplicationStatus::updateOrCreate(
                ['code' => $code->value],
                [
                    'name' => $code->label(),
                    'description' => $definition['desc'],
                    'sort_order' => $definition['sort'],
                    'color' => $definition['color'],
                    'is_terminal' => $definition['terminal'] ?? false,
                    'is_citizen_visible' => true,
                    'counts_as_open' => $definition['open'],
                ]
            );
        }
    }

    private function departmentsAndServices(): void
    {
        $catalog = [
            'Home Affairs' => [
                'code' => 'HA',
                'email' => 'home.affairs@example.gov',
                'services' => [
                    ['name' => 'Birth certificate copy', 'code' => 'HA-BIRTH-COPY', 'fee' => 8.50, 'sla' => 10, 'docs' => ['Citizen number', 'Old certificate copy']],
                    ['name' => 'National ID renewal', 'code' => 'HA-ID-RENEW', 'fee' => 12.00, 'sla' => 20, 'docs' => ['Expired ID', 'Proof of address']],
                    ['name' => 'Marriage registration', 'code' => 'HA-MARRIAGE', 'fee' => 20.00, 'sla' => 30, 'docs' => ['Ceremony certificate', 'Both citizen numbers']],
                ],
            ],
            'Urban Planning' => [
                'code' => 'UP',
                'email' => 'urban.planning@example.gov',
                'services' => [
                    ['name' => 'Building permit', 'code' => 'UP-BUILD-PERMIT', 'fee' => 145.00, 'sla' => 45, 'docs' => ['Site plan', 'Ownership deed', 'Engineer licence']],
                    ['name' => 'Zoning certificate', 'code' => 'UP-ZONING', 'fee' => 30.00, 'sla' => 15, 'docs' => ['Plot number', 'Ownership deed']],
                ],
            ],
            'Revenue & Taxes' => [
                'code' => 'RT',
                'email' => 'revenue@example.gov',
                'services' => [
                    ['name' => 'Tax clearance certificate', 'code' => 'RT-TAX-CLEARANCE', 'fee' => 0.00, 'sla' => 7, 'docs' => ['Taxpayer number', 'Last return receipt']],
                    ['name' => 'Business tax registration', 'code' => 'RT-BIZ-REG', 'fee' => 25.00, 'sla' => 14, 'docs' => ['Company charter', 'Owner citizen number']],
                ],
            ],
            'Health Services' => [
                'code' => 'HS',
                'email' => 'health@example.gov',
                'services' => [
                    ['name' => 'Medical report request', 'code' => 'HS-MED-REPORT', 'fee' => 5.00, 'sla' => 12, 'docs' => ['Patient consent', 'Visit reference']],
                    ['name' => 'Clinic operating licence', 'code' => 'HS-CLINIC-LICENCE', 'fee' => 220.00, 'sla' => 60, 'docs' => ['Facility plan', 'Staff register', 'Inspection report']],
                ],
            ],
            'Transport' => [
                'code' => 'TR',
                'email' => 'transport@example.gov',
                'services' => [
                    ['name' => 'Driver licence upgrade', 'code' => 'TR-LICENCE-UPGRADE', 'fee' => 18.00, 'sla' => 10, 'docs' => ['Current licence', 'Eye test result']],
                    ['name' => 'Vehicle registration transfer', 'code' => 'TR-VET-TRANSFER', 'fee' => 40.00, 'sla' => 21, 'docs' => ['Sales contract', 'Plate number', 'Insurance proof']],
                ],
            ],
        ];

        foreach ($catalog as $name => $definition) {
            $department = Department::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $name,
                    'description' => $name.' department service window.',
                    'contact_email' => $definition['email'],
                    'default_sla_days' => 15,
                    'is_active' => true,
                ]
            );

            foreach ($definition['services'] as $service) {
                ServiceType::updateOrCreate(
                    ['code' => $service['code']],
                    [
                        'department_id' => $department->id,
                        'name' => $service['name'],
                        'description' => "Apply for {$service['name']} through the portal. Required paperwork: ".implode(', ', $service['docs']).'.',
                        'fee' => $service['fee'],
                        'sla_days' => $service['sla'],
                        'required_documents' => $service['docs'],
                        'eligibility_notes' => ['Applicant must hold a valid citizen number.'],
                        'is_public' => true,
                        'requires_payment' => $service['fee'] > 0,
                    ]
                );
            }
        }
    }
}
