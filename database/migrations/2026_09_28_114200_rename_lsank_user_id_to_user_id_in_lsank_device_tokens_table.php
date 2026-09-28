<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasColumn('lsank_device_tokens', 'lsank_user_id') &&
            !Schema::hasColumn('lsank_device_tokens', 'user_id')
        ) {
            Schema::table(
                'lsank_device_tokens',
                function (Blueprint $table): void {
                    $table->renameColumn(
                        'lsank_user_id',
                        'user_id'
                    );
                }
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('lsank_device_tokens', 'user_id') &&
            !Schema::hasColumn('lsank_device_tokens', 'lsank_user_id')
        ) {
            Schema::table(
                'lsank_device_tokens',
                function (Blueprint $table): void {
                    $table->renameColumn(
                        'user_id',
                        'lsank_user_id'
                    );
                }
            );
        }
    }
};