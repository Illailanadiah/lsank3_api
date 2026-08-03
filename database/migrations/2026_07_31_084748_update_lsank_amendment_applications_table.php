<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'lsank_amendment_applications',
            function (Blueprint $table) {
                $table->unsignedBigInteger(
                    'source_application_id'
                )->nullable()->after('application_id');

                $table->boolean(
                    'form_a_edit_enabled'
                )->default(false)->after('amendment_type');

                $table->boolean(
                    'form_a_changed'
                )->default(false)->after('form_a_edit_enabled');

                $table->boolean(
                    'activity_changed'
                )->default(false)->after('form_a_changed');

                $table->decimal(
                    'processing_fee',
                    12,
                    2
                )->default(150)->after('reason');

                $table->decimal(
                    'information_amendment_fee',
                    12,
                    2
                )->default(0)->after('processing_fee');

                $table->decimal(
                    'incremental_charge_fee',
                    12,
                    2
                )->default(0)->after(
                    'information_amendment_fee'
                );

                $table->json(
                    'charge_breakdown'
                )->nullable()->after(
                    'incremental_charge_fee'
                );

                $table->timestamp(
                    'form_a_enabled_at'
                )->nullable()->after('status');

                $table->timestamp(
                    'submitted_at'
                )->nullable()->after('form_a_enabled_at');

                $table->timestamp(
                    'approved_at'
                )->nullable()->after('submitted_at');

                $table->timestamp(
                    'completed_at'
                )->nullable()->after('approved_at');

                $table->index(
                    ['license_id', 'status'],
                    'amendment_license_status_index'
                );

                $table->index(
                    'source_application_id',
                    'amendment_source_application_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'lsank_amendment_applications',
            function (Blueprint $table) {
                $table->dropIndex(
                    'amendment_license_status_index'
                );

                $table->dropIndex(
                    'amendment_source_application_index'
                );

                $table->dropColumn([
                    'source_application_id',
                    'form_a_edit_enabled',
                    'form_a_changed',
                    'activity_changed',
                    'processing_fee',
                    'information_amendment_fee',
                    'incremental_charge_fee',
                    'charge_breakdown',
                    'form_a_enabled_at',
                    'submitted_at',
                    'approved_at',
                    'completed_at',
                ]);
            }
        );
    }
};
