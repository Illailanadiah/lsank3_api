<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('lsank_applications', 'current_step')) {
                $table->integer('current_step')->default(0)->after('application_status');
            }

            if (!Schema::hasColumn('lsank_applications', 'draft_data')) {
                $table->json('draft_data')->nullable()->after('current_step');
            }

            if (!Schema::hasColumn('lsank_applications', 'applicant_type')) {
                $table->string('applicant_type')->nullable()->after('draft_data');
            }

            if (!Schema::hasColumn('lsank_applications', 'identity_no')) {
                $table->string('identity_no', 100)->nullable()->after('applicant_type');
            }

            if (!Schema::hasColumn('lsank_applications', 'phone_no')) {
                $table->string('phone_no', 50)->nullable()->after('identity_no');
            }

            if (!Schema::hasColumn('lsank_applications', 'address')) {
                $table->text('address')->nullable()->after('phone_no');
            }

            if (!Schema::hasColumn('lsank_applications', 'company_name')) {
                $table->string('company_name')->nullable()->after('address');
            }

            if (!Schema::hasColumn('lsank_applications', 'registration_no')) {
                $table->string('registration_no', 100)->nullable()->after('company_name');
            }

            if (!Schema::hasColumn('lsank_applications', 'business_address')) {
                $table->text('business_address')->nullable()->after('registration_no');
            }

            if (!Schema::hasColumn('lsank_applications', 'business_phone')) {
                $table->string('business_phone', 50)->nullable()->after('business_address');
            }

            if (!Schema::hasColumn('lsank_applications', 'business_email')) {
                $table->string('business_email')->nullable()->after('business_phone');
            }

            if (!Schema::hasColumn('lsank_applications', 'responsible_officer_name')) {
                $table->string('responsible_officer_name')->nullable()->after('business_email');
            }

            if (!Schema::hasColumn('lsank_applications', 'responsible_officer_phone')) {
                $table->string('responsible_officer_phone', 50)->nullable()->after('responsible_officer_name');
            }

            if (!Schema::hasColumn('lsank_applications', 'responsible_officer_position')) {
                $table->string('responsible_officer_position')->nullable()->after('responsible_officer_phone');
            }

            if (!Schema::hasColumn('lsank_applications', 'officers')) {
                $table->json('officers')->nullable()->after('responsible_officer_position');
            }

            if (!Schema::hasColumn('lsank_applications', 'activity_type_id')) {
                $table->unsignedBigInteger('activity_type_id')->nullable()->after('officers');
            }

            if (!Schema::hasColumn('lsank_applications', 'activity_name')) {
                $table->string('activity_name')->nullable()->after('activity_type_id');
            }

            if (!Schema::hasColumn('lsank_applications', 'district')) {
                $table->string('district', 100)->nullable()->after('activity_name');
            }

            if (!Schema::hasColumn('lsank_applications', 'activity_location')) {
                $table->text('activity_location')->nullable()->after('district');
            }

            if (!Schema::hasColumn('lsank_applications', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('activity_location');
            }

            if (!Schema::hasColumn('lsank_applications', 'latitude')) {
                $table->decimal('latitude', 11, 8)->nullable()->after('longitude');
            }

            if (!Schema::hasColumn('lsank_applications', 'operating_days')) {
                $table->string('operating_days')->nullable()->after('latitude');
            }

            if (!Schema::hasColumn('lsank_applications', 'operating_time')) {
                $table->string('operating_time')->nullable()->after('operating_days');
            }

            if (!Schema::hasColumn('lsank_applications', 'activity_details')) {
                $table->text('activity_details')->nullable()->after('operating_time');
            }

            if (!Schema::hasColumn('lsank_applications', 'recreation_details')) {
                $table->json('recreation_details')->nullable()->after('activity_details');
            }
        });

        DB::table('lsank_applications')
            ->where('application_status', 'submitted')
            ->update(['application_status' => 'dalam_proses']);

        DB::table('lsank_applications')
            ->where('application_status', 'Baharu')
            ->update(['application_status' => 'dalam_proses']);

        DB::table('lsank_applications')
            ->where('application_status', 'Dalam Semakan')
            ->update(['application_status' => 'dalam_proses']);

        DB::table('lsank_applications')
            ->where('application_status', 'Lulus')
            ->update(['application_status' => 'lulus']);

        DB::table('lsank_applications')
            ->where('application_status', 'Gagal')
            ->update(['application_status' => 'gagal']);

        DB::table('lsank_applications')
            ->whereNull('application_status')
            ->update(['application_status' => 'draf']);

        DB::table('lsank_applications')
            ->whereNull('payment_status')
            ->update(['payment_status' => 'belum_bayar']);
    }

    public function down(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            $columns = [
                'recreation_details',
                'activity_details',
                'operating_time',
                'operating_days',
                'latitude',
                'longitude',
                'activity_location',
                'district',
                'activity_name',
                'activity_type_id',
                'officers',
                'responsible_officer_position',
                'responsible_officer_phone',
                'responsible_officer_name',
                'business_email',
                'business_phone',
                'business_address',
                'registration_no',
                'company_name',
                'address',
                'phone_no',
                'identity_no',
                'applicant_type',
                'draft_data',
                'current_step',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('lsank_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};