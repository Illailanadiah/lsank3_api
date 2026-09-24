<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'lsank_notification_logs',
            function (Blueprint $table) {
                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'application_id'
                )) {
                    $table->unsignedBigInteger(
                        'application_id'
                    )->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'license_id'
                )) {
                    $table->unsignedBigInteger(
                        'license_id'
                    )->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'reference_id'
                )) {
                    $table->unsignedBigInteger(
                        'reference_id'
                    )->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'reference_type'
                )) {
                    $table->string(
                        'reference_type',
                        100
                    )->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'idempotency_key'
                )) {
                    $table->string(
                        'idempotency_key',
                        64
                    )->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'attempts'
                )) {
                    $table->unsignedInteger('attempts')
                        ->default(0);
                }

                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'error_message'
                )) {
                    $table->text('error_message')
                        ->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'queued_at'
                )) {
                    $table->timestamp('queued_at')
                        ->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'sent_at'
                )) {
                    $table->timestamp('sent_at')
                        ->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_notification_logs',
                    'failed_at'
                )) {
                    $table->timestamp('failed_at')
                        ->nullable();
                }
            }
        );
    }

    public function down(): void
    {
        /*
         * Leave empty to protect the existing legal
         * notification data and table structure.
         */
    }
};