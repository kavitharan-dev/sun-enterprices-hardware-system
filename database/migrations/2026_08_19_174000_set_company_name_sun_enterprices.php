<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('company_name', 'Sun Enterprices');
    }

    public function down(): void
    {
        //
    }
};
