<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lsank_water_body_vessel_details')) {
            return;
        }

        Schema::create('lsank_water_body_vessel_details', function (Blueprint $table) {
            $table->id('vessel_detail_id');

            $table->unsignedBigInteger('water_body_id');
            $table->unsignedBigInteger('application_id');

            $table->string('vessel_type', 100);
            $table->string('vessel_name', 255);
            $table->string('registration_no', 150);
            $table->unsignedInteger('passenger_capacity')->default(0);

            $table->timestamps();

            $table->unique(
                ['water_body_id', 'registration_no'],
                'water_body_vessel_registration_unique'
            );

            $table->index('application_id');

            $table->foreign('water_body_id')
                ->references('water_body_id')
                ->on('lsank_water_body_applications')
                ->cascadeOnDelete();

            $table->foreign('application_id')
                ->references('application_id')
                ->on('lsank_applications')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_water_body_vessel_details');
    }
};