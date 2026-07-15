<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('lsank_invoices', 'payment_type')) {
                $table->string('payment_type', 100)
                    ->nullable()
                    ->after('invoice_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lsank_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('lsank_invoices', 'payment_type')) {
                $table->dropColumn('payment_type');
            }
        });
    }
};