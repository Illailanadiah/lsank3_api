<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_water_body_applications', function (Blueprint $table) {
            $table->json('draft_data')->nullable()->after('activity_details');
        });
    }

    public function down(): void
    {
        Schema::table('lsank_water_body_applications', function (Blueprint $table) {
            $table->dropColumn('draft_data');
        });
    }
};