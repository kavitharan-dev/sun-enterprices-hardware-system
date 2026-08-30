<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'company_name' => 'SUN ENTERPRICES',
            'company_address' => 'Ward No 03, Nilaweli, Trincomalee',
            'company_phone' => '0750683828 / 0756422450',
            'company_email' => 'info@sunenterprise.lk',
            'currency' => 'Rs.',
            'currency_code' => 'LKR',
            'invoice_prefix' => 'INV',
            'purchase_prefix' => 'PO',
            'material_request_prefix' => 'MR',
            'material_issue_prefix' => 'MI',
            'project_prefix' => 'PRJ',
            'worker_prefix' => 'WRK',
            'timezone' => 'Asia/Colombo',
        ];

        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
