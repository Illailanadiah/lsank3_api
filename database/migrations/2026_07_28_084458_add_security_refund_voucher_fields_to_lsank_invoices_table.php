<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_invoices', function (Blueprint $table) {
            $table
                ->string('security_refund_voucher_no', 100)
                ->nullable()
                ->after('security_refunded_by');

            $table
                ->date('security_refund_voucher_date')
                ->nullable()
                ->after('security_refund_voucher_no');

            $table
                ->decimal('security_refund_amount', 12, 2)
                ->nullable()
                ->after('security_refund_voucher_date');
        });
    }

    public function down(): void
    {
        Schema::table('lsank_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'security_refund_voucher_no',
                'security_refund_voucher_date',
                'security_refund_amount',
            ]);
        });
    }
};
