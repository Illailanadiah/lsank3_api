<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            $table->json('applicant_info')->nullable();
            $table->json('business_info')->nullable();
            $table->json('borang_c_info')->nullable();
            $table->json('borang_d_info')->nullable();
            $table->json('documents_info')->nullable();
            $table->json('terms_info')->nullable();

            $table->unsignedTinyInteger('current_step')->default(1);
            $table->enum('application_status', [
                'draft',
                'submitted',
                'processing',
                'approved',
                'rejected',
            ])->default('draft');
        });
    }

    public function down(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            $table->dropColumn([
                'applicant_info',
                'business_info',
                'borang_c_info',
                'borang_d_info',
                'documents_info',
                'terms_info',
                'current_step',
                'application_status',
            ]);
        });
    }
};