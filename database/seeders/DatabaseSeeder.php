<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Order matters: roles and the catalog must exist before accounts and
     * demo cases reference them.
     *
     * Model events stay enabled on purpose so the seeded data also fills the
     * audit trail and the timeline views.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CatalogSeeder::class,
            UserSeeder::class,
            DemoApplicationSeeder::class,
        ]);

        $this->command?->newLine();
        $this->command?->info('Demo accounts (password: password)');
        $this->command?->line('  admin@portal.local    Administrator');
        $this->command?->line('  officer@portal.local  Case Officer (Home Affairs)');
        $this->command?->line('  citizen@portal.local  Public User');
    }
}
