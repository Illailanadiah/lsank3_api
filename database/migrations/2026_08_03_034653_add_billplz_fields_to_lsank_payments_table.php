<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_payments', function (Blueprint $table) {
            $table->string('billplz_bill_id', 100)
                ->nullable()
                ->unique();

            $table->text('billplz_payment_url')
                ->nullable();

            $table->json('billplz_create_response')
                ->nullable();

            $table->json('billplz_callback_payload')
                ->nullable();

            $table->timestamp('billplz_callback_received_at')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lsank_payments', function (Blueprint $table) {
            $table->dropUnique(
                'lsank_payments_billplz_bill_id_unique'
            );

            $table->dropColumn([
                'billplz_bill_id',
                'billplz_payment_url',
                'billplz_create_response',
                'billplz_callback_payload',
                'billplz_callback_received_at',
            ]);
        });
    }
};
