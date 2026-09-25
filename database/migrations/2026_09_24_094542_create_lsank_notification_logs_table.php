<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The table may already exist from the previous
         * legal-notification implementation.
         */
        if (Schema::hasTable('lsank_notification_logs')) {
            return;
        }

        Schema::create(
            'lsank_notification_logs',
            function (Blueprint $table) {
                $table->bigIncrements(
                    'notification_log_id'
                );

                $table->string('event_code', 100);

                $table->string('channel', 30)
                    ->default('mail');

                $table->unsignedBigInteger(
                    'recipient_user_id'
                )->nullable();

                $table->string('recipient_email');

                $table->unsignedBigInteger(
                    'application_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'license_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'reference_id'
                )->nullable();

                $table->string(
                    'reference_type',
                    100
                )->nullable();

                $table->string('subject');

                $table->json('payload')->nullable();

                $table->string('status', 30)
                    ->default('pending');

                $table->string(
                    'idempotency_key',
                    64
                )->unique();

                $table->unsignedInteger('attempts')
                    ->default(0);

                $table->text('error_message')
                    ->nullable();

                $table->timestamp('queued_at')
                    ->nullable();

                $table->timestamp('sent_at')
                    ->nullable();

                $table->timestamp('failed_at')
                    ->nullable();

                $table->timestamps();

                $table->index([
                    'event_code',
                    'status',
                ]);

                $table->index('application_id');
                $table->index('license_id');

                $table->index([
                    'reference_type',
                    'reference_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'lsank_notification_logs'
        );
    }
};