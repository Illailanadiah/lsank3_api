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
            if (!Schema::hasColumn('lsank_applications', 'application_status')) {
                $table->string('application_status')->default('draf')->after('application_type');
            }

            if (!Schema::hasColumn('lsank_applications', 'payment_status')) {
                $table->string('payment_status')->default('belum_bayar')->after('application_status');
            }

            if (!Schema::hasColumn('lsank_applications', 'current_step')) {
                $table->integer('current_step')->default(0)->after('payment_status');
            }

            if (!Schema::hasColumn('lsank_applications', 'draft_data')) {
                $table->json('draft_data')->nullable()->after('current_step');
            }
        });

        DB::table('lsank_applications')
            ->where('application_status', 'submitted')
            ->update(['application_status' => 'dalam_proses']);

        DB::table('lsank_applications')
            ->whereNull('application_status')
            ->update(['application_status' => 'draf']);
    }

    public function down(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            if (Schema::hasColumn('lsank_applications', 'draft_data')) {
                $table->dropColumn('draft_data');
            }

            if (Schema::hasColumn('lsank_applications', 'current_step')) {
                $table->dropColumn('current_step');
            }
        });
    }
};