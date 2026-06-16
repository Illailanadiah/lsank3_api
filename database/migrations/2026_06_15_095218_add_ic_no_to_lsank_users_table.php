<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
{
    Schema::table('lsank_users', function (Blueprint $table) {
        $table->string('ic_no', 20)
            ->nullable()
            ->unique()
            ->after('user_id');
    });
}

    public function down(): void
    {
        Schema::table('lsank_users', function (Blueprint $table) {
            $table->dropUnique(['ic_no']);
            $table->dropColumn('ic_no');
        });
    }
};