<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * RBAC baseline: three roles plus the granular abilities consumed by the
 * policies and the workflow engine.
 */
class RolePermissionSeeder extends Seeder
{
    /** @var array<int, string> */
    private const ABILITIES = [
        'applications.read_all' => ['Applications: view every case', 'applications'],
        'applications.write' => ['Applications: create and edit', 'applications'],
        'applications.assign' => ['Applications: assign an officer', 'applications'],
        'applications.decide' => ['Applications: approve or reject', 'applications'],
        'documents.verify' => ['Documents: verify citizen paperwork', 'documents'],
        'audit.read' => ['Audit: read the compliance trail', 'compliance'],
        'users.manage' => ['Users: manage accounts', 'administration'],
        'catalog.manage' => ['Catalog: manage departments and services', 'administration'],
    ];

    public function run(): void
    {
        foreach (self::ABILITIES as $name => [$displayName, $group]) {
            Permission::firstOrCreate(['name' => $name], [
                'display_name' => $displayName,
                'group' => $group,
            ]);
        }

        $roles = [
            RoleName::Admin->value => [
                'display_name' => 'Administrator',
                'description' => 'Full portal control, including approvals and the audit trail.',
                'level' => 30,
                'permissions' => array_keys(self::ABILITIES),
            ],
            RoleName::Officer->value => [
                'display_name' => 'Case Officer',
                'description' => 'Works the departmental queue, verifies documents, rejects incomplete cases.',
                'level' => 20,
                'permissions' => [
                    'applications.read_all',
                    'applications.write',
                    'applications.assign',
                    'applications.decide',
                    'documents.verify',
                    'audit.read',
                ],
            ],
            RoleName::Public->value => [
                'display_name' => 'Public User',
                'description' => 'Citizen account: submits applications and tracks their own cases.',
                'level' => 10,
                'permissions' => ['applications.write'],
            ],
        ];

        foreach ($roles as $name => $definition) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                [
                    'display_name' => $definition['display_name'],
                    'description' => $definition['description'],
                    'level' => $definition['level'],
                    'is_system' => true,
                ]
            );

            $ids = Permission::whereIn('name', $definition['permissions'])->pluck('id');
            $role->permissions()->sync($ids);
        }
    }
}
