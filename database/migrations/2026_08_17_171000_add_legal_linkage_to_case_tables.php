<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lsank_civil_cases')) {
            Schema::table(
                'lsank_civil_cases',
                function (Blueprint $table) {
                    if (!Schema::hasColumn(
                        'lsank_civil_cases',
                        'legal_referral_id'
                    )) {
                        $table->unsignedBigInteger(
                            'legal_referral_id'
                        )->nullable()->index();
                    }

                    if (!Schema::hasColumn(
                        'lsank_civil_cases',
                        'notice_id'
                    )) {
                        $table->unsignedBigInteger(
                            'notice_id'
                        )->nullable()->index();
                    }

                    if (!Schema::hasColumn(
                        'lsank_civil_cases',
                        'user_id'
                    )) {
                        $table->unsignedBigInteger(
                            'user_id'
                        )->nullable()->index();
                    }

                    if (!Schema::hasColumn(
                        'lsank_civil_cases',
                        'license_id'
                    )) {
                        $table->unsignedBigInteger(
                            'license_id'
                        )->nullable()->index();
                    }

                    if (!Schema::hasColumn(
                        'lsank_civil_cases',
                        'application_id'
                    )) {
                        $table->unsignedBigInteger(
                            'application_id'
                        )->nullable()->index();
                    }
                }
            );
        }

        if (Schema::hasTable('lsank_criminal_cases')) {
            Schema::table(
                'lsank_criminal_cases',
                function (Blueprint $table) {
                    if (!Schema::hasColumn(
                        'lsank_criminal_cases',
                        'legal_referral_id'
                    )) {
                        $table->unsignedBigInteger(
                            'legal_referral_id'
                        )->nullable()->index();
                    }

                    if (!Schema::hasColumn(
                        'lsank_criminal_cases',
                        'notice_id'
                    )) {
                        $table->unsignedBigInteger(
                            'notice_id'
                        )->nullable()->index();
                    }

                    if (!Schema::hasColumn(
                        'lsank_criminal_cases',
                        'user_id'
                    )) {
                        $table->unsignedBigInteger(
                            'user_id'
                        )->nullable()->index();
                    }

                    if (!Schema::hasColumn(
                        'lsank_criminal_cases',
                        'license_id'
                    )) {
                        $table->unsignedBigInteger(
                            'license_id'
                        )->nullable()->index();
                    }

                    if (!Schema::hasColumn(
                        'lsank_criminal_cases',
                        'application_id'
                    )) {
                        $table->unsignedBigInteger(
                            'application_id'
                        )->nullable()->index();
                    }

                    if (!Schema::hasColumn(
                        'lsank_criminal_cases',
                        'efiling_case_no'
                    )) {
                        $table->string(
                            'efiling_case_no',
                            255
                        )->nullable()->index();
                    }
                }
            );
        }
    }

    public function down(): void
    {
        // Intentionally retained to avoid deleting
        // active legal linkage/history accidentally.
    }
};
