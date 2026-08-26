<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Repair malformed notification deliveries table
        |--------------------------------------------------------------------------
        |
        | The previous skeleton migration already ran and created only:
        | id, created_at, updated_at.
        |
        | Because that table is empty, rebuild it correctly.
        |
        */

        if (
            Schema::hasTable(
                'lsank_notification_deliveries'
            )
            && !Schema::hasColumn(
                'lsank_notification_deliveries',
                'notification_id'
            )
        ) {
            Schema::drop(
                'lsank_notification_deliveries'
            );
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
                        'idx_notif_delivery_notification_channel'
                    );

                    $table->index(
                        [
                            'status',
                            'next_attempt_at',
                        ],
                        'idx_notif_delivery_status_retry'
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
        | Upgrade existing notification templates
        |--------------------------------------------------------------------------
        |
        | Keep legacy columns:
        |
        | template_name
        | template_code
        | channel
        | subject
        | body
        | status
        |
        | Add the new notification engine columns.
        |
        */

        if (
            Schema::hasTable(
                'lsank_notification_templates'
            )
        ) {
            Schema::table(
                'lsank_notification_templates',
                function (Blueprint $table) {
                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'event_type'
                        )
                    ) {
                        $table->string(
                            'event_type',
                            120
                        )->nullable()
                            ->after(
                                'template_code'
                            );
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'audience'
                        )
                    ) {
                        $table->string(
                            'audience',
                            80
                        )->nullable()
                            ->after(
                                'event_type'
                            );
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'language'
                        )
                    ) {
                        $table->string(
                            'language',
                            10
                        )->default('ms')
                            ->after(
                                'channel'
                            );
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'subject_template'
                        )
                    ) {
                        $table->string(
                            'subject_template',
                            255
                        )->nullable()
                            ->after(
                                'subject'
                            );
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'title_template'
                        )
                    ) {
                        $table->string(
                            'title_template',
                            255
                        )->nullable()
                            ->after(
                                'subject_template'
                            );
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'body_template'
                        )
                    ) {
                        $table->text(
                            'body_template'
                        )->nullable()
                            ->after(
                                'body'
                            );
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'provider_template_name'
                        )
                    ) {
                        $table->string(
                            'provider_template_name',
                            255
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'provider_parameter_keys'
                        )
                    ) {
                        $table->json(
                            'provider_parameter_keys'
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'severity'
                        )
                    ) {
                        $table->string(
                            'severity',
                            30
                        )->default('info');
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'priority'
                        )
                    ) {
                        $table->unsignedTinyInteger(
                            'priority'
                        )->default(3);
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'action_required'
                        )
                    ) {
                        $table->boolean(
                            'action_required'
                        )->default(false);
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'action_label'
                        )
                    ) {
                        $table->string(
                            'action_label',
                            100
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'show_as_ribbon'
                        )
                    ) {
                        $table->boolean(
                            'show_as_ribbon'
                        )->default(false);
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'ribbon_duration_seconds'
                        )
                    ) {
                        $table->unsignedSmallInteger(
                            'ribbon_duration_seconds'
                        )->default(7);
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'mandatory'
                        )
                    ) {
                        $table->boolean(
                            'mandatory'
                        )->default(false);
                    }

                    if (
                        !Schema::hasColumn(
                            'lsank_notification_templates',
                            'is_enabled'
                        )
                    ) {
                        $table->boolean(
                            'is_enabled'
                        )->default(true);
                    }
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Intentionally do not rollback the existing
         * template table because it predates this
         * notification engine.
         *
         * Deliveries may safely be removed.
         */

        Schema::dropIfExists(
            'lsank_notification_deliveries'
        );
    }
};