<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | SAFETY CHECK
        |--------------------------------------------------------------------------
        |
        | The previous notification delivery table was accidentally created
        | using a default Laravel migration and currently only contains:
        |
        | id
        | created_at
        | updated_at
        |
        | The existing notification template table is also using the legacy
        | structure.
        |
        | We only rebuild these tables when they are empty.
        |
        */

        if (
            Schema::hasTable(
                'lsank_notification_deliveries'
            )
        ) {
            $deliveryCount = DB::table(
                'lsank_notification_deliveries'
            )->count();

            if ($deliveryCount > 0) {
                throw new \RuntimeException(
                    'lsank_notification_deliveries contains '
                    . $deliveryCount
                    . ' record(s). Rebuild aborted to prevent data loss.'
                );
            }
        }

        if (
            Schema::hasTable(
                'lsank_notification_templates'
            )
        ) {
            $templateCount = DB::table(
                'lsank_notification_templates'
            )->count();

            if ($templateCount > 0) {
                throw new \RuntimeException(
                    'lsank_notification_templates contains '
                    . $templateCount
                    . ' record(s). Rebuild aborted to prevent data loss.'
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | DROP OLD / MALFORMED TABLES
        |--------------------------------------------------------------------------
        */

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists(
            'lsank_notification_deliveries'
        );

        Schema::dropIfExists(
            'lsank_notification_templates'
        );

        Schema::enableForeignKeyConstraints();

        /*
        |--------------------------------------------------------------------------
        | CREATE NOTIFICATION TEMPLATES
        |--------------------------------------------------------------------------
        |
        | This table stores reusable notification templates.
        |
        | Example:
        |
        | event_type = application.submitted
        | audience   = department_staff
        | channel    = in_app
        |
        */

        Schema::create(
            'lsank_notification_templates',
            function (Blueprint $table) {
                $table->bigIncrements(
                    'template_id'
                );

                /*
                |--------------------------------------------------------------------------
                | Notification Event
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'event_type',
                    120
                );

                /*
                |--------------------------------------------------------------------------
                | Recipient Audience
                |--------------------------------------------------------------------------
                |
                | Examples:
                |
                | license_holder
                | department_staff
                | department_head
                | kewangan
                | penguatkuasa
                | perundangan
                | management
                |
                */

                $table->string(
                    'audience',
                    80
                );

                /*
                |--------------------------------------------------------------------------
                | Delivery Channel
                |--------------------------------------------------------------------------
                |
                | in_app
                | email
                | push
                | whatsapp
                |
                */

                $table->string(
                    'channel',
                    30
                );

                $table->string(
                    'language',
                    10
                )->default('ms');

                /*
                |--------------------------------------------------------------------------
                | Notification Content
                |--------------------------------------------------------------------------
                */

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

                /*
                |--------------------------------------------------------------------------
                | External Provider Template
                |--------------------------------------------------------------------------
                |
                | Mainly used for WhatsApp approved templates.
                |
                */

                $table->string(
                    'provider_template_name',
                    255
                )->nullable();

                $table->json(
                    'provider_parameter_keys'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | UI / Priority
                |--------------------------------------------------------------------------
                */

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

                /*
                |--------------------------------------------------------------------------
                | Notification Policy
                |--------------------------------------------------------------------------
                |
                | mandatory=true means the notification cannot be disabled
                | through user preferences.
                |
                */

                $table->boolean(
                    'mandatory'
                )->default(false);

                $table->boolean(
                    'is_enabled'
                )->default(true);

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Unique Template Rule
                |--------------------------------------------------------------------------
                |
                | One template for:
                |
                | event + audience + channel + language
                |
                */

                $table->unique(
                    [
                        'event_type',
                        'audience',
                        'channel',
                        'language',
                    ],
                    'uq_notif_template_event_audience_channel_lang'
                );

                $table->index(
                    [
                        'event_type',
                        'is_enabled',
                    ],
                    'idx_notif_template_event_enabled'
                );

                $table->index(
                    [
                        'audience',
                        'channel',
                    ],
                    'idx_notif_template_audience_channel'
                );
            }
        );

        /*
        |--------------------------------------------------------------------------
        | CREATE NOTIFICATION DELIVERIES
        |--------------------------------------------------------------------------
        |
        | lsank_notifications = logical notification / in-app source of truth
        |
        | lsank_notification_deliveries = individual delivery attempts
        |
        | Example:
        |
        | Notification #100
        |
        | in_app   -> sent
        | email    -> sent
        | push     -> pending
        | whatsapp -> failed
        |
        */

        Schema::create(
            'lsank_notification_deliveries',
            function (Blueprint $table) {
                $table->bigIncrements(
                    'delivery_id'
                );

                /*
                |--------------------------------------------------------------------------
                | Parent Notification
                |--------------------------------------------------------------------------
                */

                $table->unsignedBigInteger(
                    'notification_id'
                );

                /*
                |--------------------------------------------------------------------------
                | Channel
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'channel',
                    30
                );

                /*
                |--------------------------------------------------------------------------
                | Recipient
                |--------------------------------------------------------------------------
                |
                | in_app:
                | user ID
                |
                | email:
                | email address
                |
                | push:
                | FCM token
                |
                | whatsapp:
                | phone number
                |
                */

                $table->string(
                    'recipient',
                    500
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Delivery Status
                |--------------------------------------------------------------------------
                |
                | pending
                | processing
                | sent
                | delivered
                | failed
                | skipped
                |
                */

                $table->string(
                    'status',
                    30
                )->default('pending');

                /*
                |--------------------------------------------------------------------------
                | Provider
                |--------------------------------------------------------------------------
                |
                | lsank
                | smtp
                | fcm
                | meta_whatsapp
                |
                */

                $table->string(
                    'provider',
                    100
                )->nullable();

                $table->string(
                    'provider_message_id',
                    255
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Retry / Attempt
                |--------------------------------------------------------------------------
                */

                $table->unsignedSmallInteger(
                    'attempt_count'
                )->default(0);

                /*
                |--------------------------------------------------------------------------
                | Delivery Payload
                |--------------------------------------------------------------------------
                */

                $table->json(
                    'payload'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Error Tracking
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'last_error'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Delivery Timestamps
                |--------------------------------------------------------------------------
                */

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

                /*
                |--------------------------------------------------------------------------
                | Idempotency
                |--------------------------------------------------------------------------
                |
                | Prevent duplicate delivery for the same:
                |
                | notification + channel + recipient
                |
                */

                $table->string(
                    'delivery_key',
                    255
                )->unique();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Indexes
                |--------------------------------------------------------------------------
                */

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

                $table->index(
                    [
                        'channel',
                        'status',
                    ],
                    'idx_notif_delivery_channel_status'
                );

                /*
                |--------------------------------------------------------------------------
                | Foreign Key
                |--------------------------------------------------------------------------
                */

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Corrective Migration
        |--------------------------------------------------------------------------
        |
        | Deliberately left empty.
        |
        | Once notification data exists, automatic rollback of this corrective
        | migration must not destroy notification templates or delivery logs.
        |
        */
    }
};