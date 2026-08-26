<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Repair notification deliveries
        |--------------------------------------------------------------------------
        */

        if (
            Schema::hasTable(
                'lsank_notification_deliveries'
            )
        ) {
            $deliveryCount = DB::table(
                'lsank_notification_deliveries'
            )->count();

            $deliveryMalformed =
                !Schema::hasColumn(
                    'lsank_notification_deliveries',
                    'notification_id'
                );

            if ($deliveryMalformed) {
                if ($deliveryCount > 0) {
                    throw new RuntimeException(
                        'lsank_notification_deliveries is malformed '
                        . 'but contains data. Repair aborted.'
                    );
                }

                Schema::drop(
                    'lsank_notification_deliveries'
                );
            }
        }

        if (
            !Schema::hasTable(
                'lsank_notification_deliveries'
            )
        ) {
            Schema::create(
                'lsank_notification_deliveries',
                function (Blueprint $table) {
                    $table->bigIncrements(
                        'delivery_id'
                    );

                    $table->unsignedBigInteger(
                        'notification_id'
                    );

                    $table->string(
                        'channel',
                        30
                    );

                    $table->string(
                        'recipient',
                        500
                    )->nullable();

                    $table->string(
                        'status',
                        30
                    )->default('pending');

                    $table->string(
                        'provider',
                        100
                    )->nullable();

                    $table->string(
                        'provider_message_id',
                        255
                    )->nullable();

                    $table->unsignedSmallInteger(
                        'attempt_count'
                    )->default(0);

                    $table->json(
                        'payload'
                    )->nullable();

                    $table->text(
                        'last_error'
                    )->nullable();

                    $table->timestamp(
                        'queued_at'
                    )->nullable();

                    $table->timestamp(
                        'processing_at'
                    )->nullable();

                    $table->timestamp(
                        'sent_at'
                    )->nullable();

                    $table->timestamp(
                        'delivered_at'
                    )->nullable();

                    $table->timestamp(
                        'failed_at'
                    )->nullable();

                    $table->timestamp(
                        'next_attempt_at'
                    )->nullable();

                    $table->string(
                        'delivery_key',
                        255
                    )->unique();

                    $table->timestamps();

                    $table->index(
                        [
                            'notification_id',
                            'channel',
                        ],
                        'idx_notification_delivery_channel'
                    );

                    $table->index(
                        [
                            'status',
                            'next_attempt_at',
                        ],
                        'idx_notification_delivery_retry'
                    );

                    $table->foreign(
                        'notification_id'
                    )
                        ->references(
                            'notification_id'
                        )
                        ->on(
                            'lsank_notifications'
                        )
                        ->cascadeOnDelete();
                }
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Rebuild notification templates
        |--------------------------------------------------------------------------
        |
        | Current legacy table is empty, so rebuild it using the new engine
        | schema rather than keeping obsolete legacy columns.
        |
        */

        if (
            Schema::hasTable(
                'lsank_notification_templates'
            )
        ) {
            $templateCount = DB::table(
                'lsank_notification_templates'
            )->count();

            if ($templateCount > 0) {
                throw new RuntimeException(
                    'lsank_notification_templates contains data. '
                    . 'Repair aborted to prevent data loss.'
                );
            }

            Schema::drop(
                'lsank_notification_templates'
            );
        }

        Schema::create(
            'lsank_notification_templates',
            function (Blueprint $table) {
                $table->bigIncrements(
                    'template_id'
                );

                $table->string(
                    'event_type',
                    120
                );

                $table->string(
                    'audience',
                    80
                );

                $table->string(
                    'channel',
                    30
                );

                $table->string(
                    'language',
                    10
                )->default('ms');

                $table->string(
                    'subject_template',
                    255
                )->nullable();

                $table->string(
                    'title_template',
                    255
                )->nullable();

                $table->text(
                    'body_template'
                );

                $table->string(
                    'provider_template_name',
                    255
                )->nullable();

                $table->json(
                    'provider_parameter_keys'
                )->nullable();

                $table->string(
                    'severity',
                    30
                )->default('info');

                $table->unsignedTinyInteger(
                    'priority'
                )->default(3);

                $table->boolean(
                    'action_required'
                )->default(false);

                $table->string(
                    'action_label',
                    100
                )->nullable();

                $table->boolean(
                    'show_as_ribbon'
                )->default(false);

                $table->unsignedSmallInteger(
                    'ribbon_duration_seconds'
                )->default(7);

                $table->boolean(
                    'mandatory'
                )->default(false);

                $table->boolean(
                    'is_enabled'
                )->default(true);

                $table->timestamps();

                $table->unique(
                    [
                        'event_type',
                        'audience',
                        'channel',
                        'language',
                    ],
                    'uq_notification_template_event_audience_channel_lang'
                );

                $table->index(
                    [
                        'event_type',
                        'is_enabled',
                    ],
                    'idx_notification_template_event_enabled'
                );
            }
        );
    }

    public function down(): void
    {
        /*
         * Intentionally empty.
         *
         * This is a corrective migration for an existing LSANK database.
         * Automatic rollback should not destroy notification data.
         */
    }
};