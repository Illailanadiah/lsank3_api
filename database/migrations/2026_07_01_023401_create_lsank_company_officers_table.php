<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsank_company_officers', function (Blueprint $table) {
            $table->id('company_officer_id');

            $table->unsignedBigInteger('company_id');

            $table->string('officer_name');
            $table->string('officer_phone', 30);
            $table->string('officer_position')->nullable();

            $table->timestamps();

            $table->foreign('company_id')
                ->references('company_id')
                ->on('lsank_companies')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_company_officers');
    }
};