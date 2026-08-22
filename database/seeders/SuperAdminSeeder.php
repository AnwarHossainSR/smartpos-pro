<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\TransactionalSeeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a platform super-admin user for managing tenants.
 * This user has no tenant — they operate across all tenants.
 */
class SuperAdminSeeder extends TransactionalSeeder
{
    public function seed(): void
    {
        User::withoutTenancy(function () {
            User::updateOrCreate(
                ['email' => 'superadmin@smartpos.com'],
                [
                    'name' => 'Super Admin',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            )->forceFill(['is_super_admin' => true])->save();
        });
    }
}
