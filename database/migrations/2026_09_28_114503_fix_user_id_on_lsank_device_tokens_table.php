<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'lsank_device_tokens',
            function (Blueprint $table): void {
                if (!Schema::hasColumn(
                    'lsank_device_tokens',
                    'user_id'
                )) {
                    $table
                        ->unsignedBigInteger('user_id')
                        ->nullable()
                        ->index();
                }

                if (!Schema::hasColumn(
                    'lsank_device_tokens',
                    'token'
                )) {
                    $table->string('token', 512);
                }

                if (!Schema::hasColumn(
                    'lsank_device_tokens',
                    'platform'
                )) {
                    $table
                        ->string('platform', 30)
                        ->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_device_tokens',
                    'device_id'
                )) {
                    $table
                        ->string('device_id')
                        ->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_device_tokens',
                    'device_name'
                )) {
                    $table
                        ->string('device_name')
                        ->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_device_tokens',
                    'app_version'
                )) {
                    $table
                        ->string('app_version', 50)
                        ->nullable();
                }

                if (!Schema::hasColumn(
                    'lsank_device_tokens',
                    'is_active'
                )) {
                    $table
                        ->boolean('is_active')
                        ->default(true)
                        ->index();
                }

                if (!Schema::hasColumn(
                    'lsank_device_tokens',
                    'last_used_at'
                )) {
                    $table
                        ->timestamp('last_used_at')
                        ->nullable();
                }
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'lsank_device_tokens',
            function (Blueprint $table): void {
                $columns = [
                    'user_id',
                    'token',
                    'platform',
                    'device_id',
                    'device_name',
                    'app_version',
                    'is_active',
                    'last_used_at',
                ];

                foreach ($columns as $column) {
                    if (Schema::hasColumn(
                        'lsank_device_tokens',
                        $column
                    )) {
                        $table->dropColumn($column);
                    }
                }
            }
        );
    }
};