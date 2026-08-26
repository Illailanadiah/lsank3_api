<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable(
                'lsank_notification_preferences'
            )
        ) {
            return;
        }

        Schema::create(
            'lsank_notification_preferences',
            function (Blueprint $table) {
                $table->bigIncrements(
                    'preference_id'
                );

                $table->unsignedBigInteger(
                    'user_id'
                )->unique();

                $table->boolean(
                    'in_app_enabled'
                )->default(true);

                $table->boolean(
                    'push_enabled'
                )->default(true);

                $table->boolean(
                    'email_enabled'
                )->default(true);

                $table->boolean(
                    'whatsapp_enabled'
                )->default(false);

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'lsank_notification_preferences'
        );
    }
};