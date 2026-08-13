<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsank_notices', function (Blueprint $table) {
            $table->bigIncrements('notice_id');

            $table->string('notice_no', 80)->unique();
            $table->string('template_code', 20);
            $table->string('notice_type', 10);
            $table->string('category', 10)->nullable();

            $table->string('status', 80)->default('Aktif');

            $table->string('okn_name')->nullable();
            $table->string('identity_no', 100)->nullable();
            $table->text('registered_address')->nullable();
            $table->string('phone', 80)->nullable();

            $table->text('offence_section')->nullable();
            $table->text('activity_category')->nullable();
            $table->text('offence_details')->nullable();
            $table->text('offence_location')->nullable();

            $table->date('inspection_date')->nullable();
            $table->string('inspection_time', 80)->nullable();
            $table->string('coordinates', 120)->nullable();

            $table->decimal('compound_amount', 12, 2)->nullable();
            $table->string('compound_status', 80)->nullable();
            $table->date('compound_due_date')->nullable();

            $table->json('form_data')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamp('issued_at')->nullable();

            $table->timestamps();

            $table->index(['notice_type', 'status']);
            $table->index('template_code');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_notices');
    }
};
