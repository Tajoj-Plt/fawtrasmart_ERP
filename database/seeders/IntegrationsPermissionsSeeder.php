<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class IntegrationsPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'integrations.view',
            'integrations.create',
            'integrations.edit',
            'integrations.delete',
            'integrations.test',
            'smtp.view',
            'smtp.manage',
            'sms.view',
            'sms.create',
            'sms.edit',
            'sms.delete',
            'sms.test',
            'api_keys.view',
            'api_keys.create',
            'api_keys.edit',
            'api_keys.delete',
            'api_keys.revoke',
            'integration_logs.view',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }
    }
}
