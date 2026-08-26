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
                'lsank_user_devices'
            )
        ) {
            return;
        }

        Schema::create(
            'lsank_user_devices',
            function (Blueprint $table) {
                $table->bigIncrements(
                    'device_id'
                );

                $table->unsignedBigInteger(
                    'user_id'
                );

                $table->string(
                    'fcm_token',
                    500
                )->unique();

                $table->string(
                    'platform',
                    30
                )->nullable();

                $table->string(
                    'device_name',
                    150
                )->nullable();

                $table->string(
                    'app_version',
                    50
                )->nullable();

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->timestamp(
                    'last_seen_at'
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'user_id',
                        'is_active',
                    ],
                    'idx_lsank_user_device_user_active'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'lsank_user_devices'
        );
    }
};