<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_invoices', function (Blueprint $table) {
            $table->string('security_refund_status', 30)
                ->default('not_requested')
                ->after('status');

            $table->timestamp('security_refund_requested_at')
                ->nullable()
                ->after('security_refund_status');

            $table->timestamp('security_refunded_at')
                ->nullable()
                ->after('security_refund_requested_at');

            $table->unsignedBigInteger('security_refunded_by')
                ->nullable()
                ->after('security_refunded_at');

            $table->text('security_refund_note')
                ->nullable()
                ->after('security_refunded_by');
        });
    }

    public function down(): void
    {
        Schema::table('lsank_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'security_refund_status',
                'security_refund_requested_at',
                'security_refunded_at',
                'security_refunded_by',
                'security_refund_note',
            ]);
        });
    }
};
