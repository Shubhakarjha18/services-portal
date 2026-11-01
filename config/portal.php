<?php

declare(strict_types=1);

use App\Enums\ApplicationStatusCode;
use App\Enums\RoleName;

return [

    /*
    |--------------------------------------------------------------------------
    | Tracking reference numbers
    |--------------------------------------------------------------------------
    */
    'reference' => [
        'prefix' => env('PORTAL_REFERENCE_PREFIX', 'PSP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Status workflow
    |--------------------------------------------------------------------------
    | Every edge below is enforced server side. A transition is allowed only
    | when the target appears in the list AND at least one of the listed roles
    | is granted to the acting user.
    */
    'workflow' => [

        ApplicationStatusCode::Submitted->value => [
            'to' => [
                ApplicationStatusCode::UnderReview->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::OnHold->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::Cancelled->value => [RoleName::Public, RoleName::Admin],
            ],
            'stage' => 1,
        ],

        ApplicationStatusCode::UnderReview->value => [
            'to' => [
                ApplicationStatusCode::DocumentsRequired->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::InProgress->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::OnHold->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::Rejected->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::Cancelled->value => [RoleName::Public, RoleName::Admin],
            ],
            'stage' => 2,
        ],

        ApplicationStatusCode::DocumentsRequired->value => [
            'to' => [
                ApplicationStatusCode::UnderReview->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::Cancelled->value => [RoleName::Public, RoleName::Admin],
            ],
            'stage' => 3,
        ],

        ApplicationStatusCode::InProgress->value => [
            'to' => [
                ApplicationStatusCode::PendingApproval->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::DocumentsRequired->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::OnHold->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::Rejected->value => [RoleName::Officer, RoleName::Admin],
            ],
            'stage' => 4,
        ],

        ApplicationStatusCode::OnHold->value => [
            'to' => [
                ApplicationStatusCode::UnderReview->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::InProgress->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::Cancelled->value => [RoleName::Public, RoleName::Admin],
            ],
            'stage' => 4,
        ],

        ApplicationStatusCode::PendingApproval->value => [
            'to' => [
                ApplicationStatusCode::Approved->value => [RoleName::Admin],
                ApplicationStatusCode::InProgress->value => [RoleName::Officer, RoleName::Admin],
                ApplicationStatusCode::Rejected->value => [RoleName::Admin],
            ],
            'stage' => 5,
        ],

        ApplicationStatusCode::Approved->value => [
            'to' => [
                ApplicationStatusCode::Completed->value => [RoleName::Officer, RoleName::Admin],
            ],
            'stage' => 6,
        ],

        ApplicationStatusCode::Completed->value => ['to' => [], 'stage' => 7],
        ApplicationStatusCode::Rejected->value => ['to' => [], 'stage' => 7],
        ApplicationStatusCode::Cancelled->value => ['to' => [], 'stage' => 7],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */
    'documents' => [
        'disk' => 'documents',
        'max_kilobytes' => (int) env('PORTAL_MAX_DOCUMENT_KILOBYTES', 10240),
        'allowed_mimes' => [
            'pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx',
        ],
        'storage_prefix' => 'applications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracking
    |--------------------------------------------------------------------------
    */
    'tracking_poll_seconds' => (int) env('PORTAL_TRACKING_POLL_SECONDS', 15),

    'default_sla_days' => (int) env('PORTAL_DEFAULT_SLA_DAYS', 15),

    /*
    |--------------------------------------------------------------------------
    | Rate limits (per minute, keyed per authenticated user / IP)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'login' => 10,
        'register' => 5,
        'track' => 30,
        'api' => 120,
    ],
];
