<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_licenses', function (Blueprint $table) {
            if (!Schema::hasColumn('lsank_licenses', 'latitude')) {
    Schema::table('lsank_licenses', function (Blueprint $table) {
        $table->decimal('latitude', 11, 8)
            ->nullable()
            ->after('activity_location');
    });
}

            if (!Schema::hasColumn('lsank_licenses', 'longitude')) {
    Schema::table('lsank_licenses', function (Blueprint $table) {
        // Letakkan baris asal $table->...('longitude', ...) di sini.
    });
}
        });
    }

    public function down(): void
    {
        Schema::table('lsank_licenses', function (Blueprint $table) {
            if (Schema::hasColumn('lsank_licenses', 'longitude')) {
                $table->dropColumn('longitude');
            }

            if (Schema::hasColumn('lsank_licenses', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};