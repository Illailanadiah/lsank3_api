<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable()->after('report_id');
            $table->string('activity_name')->nullable()->after('report_type');

            $table->enum('report_status', [
                'draft',
                'submitted',
            ])->default('draft')->after('activity_name');

            $table->json('report_data')->nullable()->after('description');

            $table->decimal('security_amount', 12, 2)->nullable()->after('report_data');
            $table->string('government_project')->nullable()->after('security_amount');
            $table->string('project_invoice_mode')->nullable()->after('government_project');

            $table->boolean('invoice_generate')->default(false)->after('project_invoice_mode');
            $table->decimal('invoice_fee_caj', 12, 2)->default(0)->after('invoice_generate');
            $table->decimal('invoice_fee_lesen', 12, 2)->nullable()->after('invoice_fee_caj');
            $table->decimal('invoice_fee_sekuriti', 12, 2)->nullable()->after('invoice_fee_lesen');
            $table->boolean('invoice_exempt')->default(false)->after('invoice_fee_sekuriti');

            $table->date('license_start_date')->nullable()->after('invoice_exempt');
            $table->date('license_end_date')->nullable()->after('license_start_date');

            $table->timestamp('submitted_at')->nullable()->after('license_end_date');

            $table->foreign('application_id')
                ->references('application_id')
                ->on('lsank_applications')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lsank_reports', function (Blueprint $table) {
            $table->dropForeign(['application_id']);

            $table->dropColumn([
                'application_id',
                'activity_name',
                'report_status',
                'report_data',
                'security_amount',
                'government_project',
                'project_invoice_mode',
                'invoice_generate',
                'invoice_fee_caj',
                'invoice_fee_lesen',
                'invoice_fee_sekuriti',
                'invoice_exempt',
                'license_start_date',
                'license_end_date',
                'submitted_at',
            ]);
        });
    }
};