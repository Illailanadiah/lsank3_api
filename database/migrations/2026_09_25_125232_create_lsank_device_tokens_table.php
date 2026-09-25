<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('lsank_device_tokens', function (Blueprint $table) {
        $table->bigIncrements('device_token_id');

        $table->unsignedBigInteger('user_id');

        $table->text('token');
        $table->string('token_hash', 64)->unique();

        $table->string('device_id', 191)->nullable();
        $table->string('device_name', 191)->nullable();
        $table->string('platform', 30)->nullable();
        $table->string('app_version', 30)->nullable();

        $table->boolean('is_active')->default(true);
        $table->timestamp('last_used_at')->nullable();
        $table->timestamps();

        $table->index(['user_id', 'is_active']);

        $table->foreign('user_id')
            ->references('user_id')
            ->on('lsank_users')
            ->cascadeOnDelete();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lsank_device_tokens');
    }
};
