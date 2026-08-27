<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Idempotent role bootstrap — safe to run on every deploy without resetting users.
 */
class EnsureRolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'store_manager', 'cashier', 'site_manager'] as $name) {
            Role::findOrCreate($name);
        }
    }
}
