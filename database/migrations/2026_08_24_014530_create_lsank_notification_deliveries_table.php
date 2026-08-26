<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsank_notification_deliveries', function (Blueprint $table) {
            $table->bigIncrements('delivery_id');
            $table->unsignedBigInteger('notification_id');

            $table->string('channel', 30);
            $table->string('recipient', 500)->nullable();

            $table->string('status', 30)->default('pending');
            $table->string('provider', 100)->nullable();
            $table->string('provider_message_id', 255)->nullable();

            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->json('payload')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->string('delivery_key', 255)->unique();

            $table->timestamps();

            $table->index(
                ['notification_id', 'channel'],
                'idx_lsank_notif_delivery_notification_channel'
            );

            $table->foreign('notification_id')
                ->references('notification_id')
                ->on('lsank_notifications')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_notification_deliveries');
    }
};