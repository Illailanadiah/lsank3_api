<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsank_enquiry_messages', function (Blueprint $table) {
            $table->bigIncrements('message_id');

            $table->unsignedBigInteger('enquiry_id');

            $table->unsignedBigInteger('sender_id');

            $table->enum('sender_type', [
                'user',
                'admin',
            ]);

            $table->longText('message');

            $table->boolean('is_read')
                ->default(false);

            $table->timestamp('read_at')
                ->nullable();

            $table->timestamps();

            // ====================================================
            // INDEX
            // ====================================================

            $table->index('enquiry_id');

            $table->index('sender_id');

            $table->index('sender_type');

            $table->index('is_read');

            // ====================================================
            // FOREIGN KEY
            // ====================================================

            $table->foreign('enquiry_id')
                ->references('enquiry_id')
                ->on('lsank_enquiries')
                ->cascadeOnDelete();

            $table->foreign('sender_id')
                ->references('user_id')
                ->on('lsank_users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'lsank_enquiry_messages'
        );
    }
};
