<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Silber\Bouncer\BouncerFacade;

class AuditorRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Auditor role
        $auditor = BouncerFacade::role()->firstOrCreate([
            'name' => 'auditor',
            'title' => 'Auditor',
            'scope' => 1,
        ]);

        // Grant view-only permissions
        $viewPermissions = [
            'view-invoice',
            'view-audit-logs',
            'view-email-logs',
            'dashboard',
        ];

        foreach ($viewPermissions as $permission) {
            BouncerFacade::allow($auditor)->to($permission);
        }

        // Also grant audit and email log permissions to existing super admin roles
        $superAdminRoles = BouncerFacade::role()->where('name', 'super admin')->get();
        
        foreach ($superAdminRoles as $superAdminRole) {
            BouncerFacade::allow($superAdminRole)->to('view-audit-logs');
            BouncerFacade::allow($superAdminRole)->to('view-email-logs');
        }
    }
} 