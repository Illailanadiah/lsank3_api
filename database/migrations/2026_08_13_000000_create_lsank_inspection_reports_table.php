<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsank_inspection_reports', function (Blueprint $table) {
            $table->id('report_id');

            $table->string('report_no', 50)->unique();
            $table->string('template_code', 10);

            $table->unsignedBigInteger('license_id')->nullable();

            $table->string('holder_name')->nullable();
            $table->string('registration_no', 100)->nullable();
            $table->string('license_no', 100)->nullable();
            $table->string('file_no', 100)->nullable();

            $table->text('location')->nullable();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->date('inspection_date')->nullable();
            $table->string('inspection_type')->nullable();
            $table->string('inspection_result')->nullable();

            $table->text('offence_section')->nullable();
            $table->string('report_status', 100)->default('Direkodkan');

            $table->string('prepared_by_name')->nullable();
            $table->string('reviewed_by_name')->nullable();

            $table->json('form_data');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->foreign('license_id')
                ->references('license_id')
                ->on('lsank_licenses')
                ->nullOnDelete();

            $table->foreign('created_by')
                ->references('user_id')
                ->on('lsank_users')
                ->nullOnDelete();

            $table->index(['template_code', 'inspection_date']);
            $table->index('report_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_inspection_reports');
    }
};
