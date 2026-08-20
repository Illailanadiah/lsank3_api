<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'lsank_notifications',
            function (Blueprint $table) {

                /*
                |--------------------------------------------------------------------------
                | Event / Audience
                |--------------------------------------------------------------------------
                |
                | notification_type kekal sebagai:
                | email / whatsapp / in_app
                |
                | event_type digunakan untuk:
                | legal_civil_created
                | legal_criminal_created
                | legal_referral_created
                | notice_action_required
                |
                */
                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'event_type'
                    )
                ) {
                    $table->string(
                        'event_type',
                        100
                    )->nullable()
                        ->after('notification_type');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'audience'
                    )
                ) {
                    $table->string(
                        'audience',
                        50
                    )->nullable()
                        ->after('event_type');
                }

                /*
                |--------------------------------------------------------------------------
                | Severity / Priority
                |--------------------------------------------------------------------------
                */
                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'severity'
                    )
                ) {
                    $table->string(
                        'severity',
                        30
                    )->default('info')
                        ->after('audience');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'priority'
                    )
                ) {
                    $table->unsignedTinyInteger(
                        'priority'
                    )->default(3)
                        ->after('severity');
                }

                /*
                |--------------------------------------------------------------------------
                | Action
                |--------------------------------------------------------------------------
                */
                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'action_required'
                    )
                ) {
                    $table->boolean(
                        'action_required'
                    )->default(false)
                        ->after('related_id');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'action_label'
                    )
                ) {
                    $table->string(
                        'action_label',
                        100
                    )->nullable()
                        ->after('action_required');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'action_url'
                    )
                ) {
                    $table->string(
                        'action_url',
                        500
                    )->nullable()
                        ->after('action_label');
                }

                /*
                |--------------------------------------------------------------------------
                | Login Ribbon
                |--------------------------------------------------------------------------
                */
                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'show_as_ribbon'
                    )
                ) {
                    $table->boolean(
                        'show_as_ribbon'
                    )->default(false)
                        ->after('action_url');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'ribbon_duration_seconds'
                    )
                ) {
                    $table->unsignedSmallInteger(
                        'ribbon_duration_seconds'
                    )->default(7)
                        ->after('show_as_ribbon');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'first_shown_at'
                    )
                ) {
                    $table->timestamp(
                        'first_shown_at'
                    )->nullable()
                        ->after('ribbon_duration_seconds');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'last_shown_at'
                    )
                ) {
                    $table->timestamp(
                        'last_shown_at'
                    )->nullable()
                        ->after('first_shown_at');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'shown_count'
                    )
                ) {
                    $table->unsignedInteger(
                        'shown_count'
                    )->default(0)
                        ->after('last_shown_at');
                }

                /*
                |--------------------------------------------------------------------------
                | Read / Dismiss / Complete
                |--------------------------------------------------------------------------
                |
                | is_read dikekalkan untuk compatibility code lama.
                | read_at memberi masa sebenar notification dibaca.
                |
                */
                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'read_at'
                    )
                ) {
                    $table->timestamp(
                        'read_at'
                    )->nullable()
                        ->after('is_read');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'dismissed_at'
                    )
                ) {
                    $table->timestamp(
                        'dismissed_at'
                    )->nullable()
                        ->after('read_at');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'action_completed_at'
                    )
                ) {
                    $table->timestamp(
                        'action_completed_at'
                    )->nullable()
                        ->after('dismissed_at');
                }

                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'expires_at'
                    )
                ) {
                    $table->timestamp(
                        'expires_at'
                    )->nullable()
                        ->after('action_completed_at');
                }

                /*
                |--------------------------------------------------------------------------
                | Extra Data
                |--------------------------------------------------------------------------
                */
                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'metadata'
                    )
                ) {
                    $table->json(
                        'metadata'
                    )->nullable()
                        ->after('expires_at');
                }

                /*
                |--------------------------------------------------------------------------
                | Duplicate Protection
                |--------------------------------------------------------------------------
                |
                | Example:
                | civil_created:12:holder:5
                | civil_created:12:legal:16
                |
                */
                if (
                    !Schema::hasColumn(
                        'lsank_notifications',
                        'event_key'
                    )
                ) {
                    $table->string(
                        'event_key',
                        255
                    )->nullable()
                        ->unique()
                        ->after('metadata');
                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'lsank_notifications',
            function (Blueprint $table) {

                $columns = [
                    'event_type',
                    'audience',
                    'severity',
                    'priority',
                    'action_required',
                    'action_label',
                    'action_url',
                    'show_as_ribbon',
                    'ribbon_duration_seconds',
                    'first_shown_at',
                    'last_shown_at',
                    'shown_count',
                    'read_at',
                    'dismissed_at',
                    'action_completed_at',
                    'expires_at',
                    'metadata',
                    'event_key',
                ];

                foreach ($columns as $column) {
                    if (
                        Schema::hasColumn(
                            'lsank_notifications',
                            $column
                        )
                    ) {
                        $table->dropColumn(
                            $column
                        );
                    }
                }
            }
        );
    }
};