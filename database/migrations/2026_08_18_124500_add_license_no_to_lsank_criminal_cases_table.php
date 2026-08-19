<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lsank_criminal_cases')) {
            return;
        }

        Schema::table(
            'lsank_criminal_cases',
            function (Blueprint $table) {
                if (!Schema::hasColumn(
                    'lsank_criminal_cases',
                    'license_no'
                )) {
                    $table->string(
                        'license_no',
                        255
                    )
                        ->nullable()
                        ->after('file_no')
                        ->index();
                }
            }
        );
    }

    public function down(): void
    {
        if (
            Schema::hasTable('lsank_criminal_cases') &&
            Schema::hasColumn(
                'lsank_criminal_cases',
                'license_no'
            )
        ) {
            Schema::table(
                'lsank_criminal_cases',
                function (Blueprint $table) {
                    $table->dropColumn('license_no');
                }
            );
        }
    }
};
