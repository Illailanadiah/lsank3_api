<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsank_criminal_cases', function (Blueprint $table) {
            $table->bigIncrements('criminal_case_id');

            $table->string('case_no', 50)->unique();

            $table->unsignedBigInteger('license_id')->nullable()->index();
            $table->string('file_no', 100)->nullable()->index();

            $table->string('offence', 255);
            $table->string('party_name', 255);
            $table->string('section_regulation', 255)->nullable();

            $table->string('court_location', 255)->nullable();
            $table->string('judge_name', 255)->nullable();
            $table->date('mention_date')->nullable();

            $table->string('punishment', 100)->default('Tiada');
            $table->decimal('outstanding_amount', 12, 2)->default(0);

            $table->string('case_status', 100)
                ->default('Sedang Berjalan')
                ->index();

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();

            $table->timestamps();

            $table->foreign('license_id')
                ->references('license_id')
                ->on('lsank_licenses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_criminal_cases');
    }
};
