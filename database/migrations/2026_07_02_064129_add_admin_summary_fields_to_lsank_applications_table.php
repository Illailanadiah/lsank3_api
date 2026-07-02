<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('lsank_applications', 'applicant_name')) {
                $table->string('applicant_name')->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('lsank_applications', 'business_name')) {
                $table->string('business_name')->nullable()->after('applicant_name');
            }

            if (!Schema::hasColumn('lsank_applications', 'phone')) {
                $table->string('phone', 30)->nullable()->after('business_name');
            }

            if (!Schema::hasColumn('lsank_applications', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('lsank_applications', 'license_type')) {
                $table->string('license_type')->nullable()->after('email');
            }

            if (!Schema::hasColumn('lsank_applications', 'activity_type')) {
                $table->string('activity_type')->nullable()->after('license_type');
            }

            if (!Schema::hasColumn('lsank_applications', 'application_type')) {
                $table->string('application_type', 100)->default('Baharu')->after('activity_type');
            }

            if (!Schema::hasColumn('lsank_applications', 'payment_status')) {
                $table->string('payment_status', 100)->default('Belum Bayar')->after('application_type');
            }

            if (!Schema::hasColumn('lsank_applications', 'application_status')) {
                $table->string('application_status', 100)->default('Baharu')->after('payment_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            $table->dropColumn([
                'applicant_name',
                'business_name',
                'phone',
                'email',
                'license_type',
                'activity_type',
                'application_type',
                'payment_status',
                'application_status',
            ]);
        });
    }
};