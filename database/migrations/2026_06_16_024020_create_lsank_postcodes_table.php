<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsank_postcodes', function (Blueprint $table) {
            $table->id('postcode_id');

            $table->unsignedBigInteger('district_id');
            $table->string('city_name');
            $table->string('postcode', 10);
            $table->string('state_name')->default('Kedah');
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->timestamps();

            $table->foreign('district_id')
                ->references('district_id')
                ->on('lsank_districts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_postcodes');
    }
};