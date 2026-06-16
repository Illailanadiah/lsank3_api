<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE lsank_users
            MODIFY user_type VARCHAR(100) NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE lsank_users
            MODIFY user_type ENUM(
                'public',
                'internal',
                'admin'
            ) NOT NULL DEFAULT 'public'
        ");
    }
};