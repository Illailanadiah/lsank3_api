<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            $table->boolean('same_info')->default(false)->after('user_id');

            $table->string('business_name')->nullable();
            $table->string('business_address_1')->nullable();
            $table->string('business_address_2')->nullable();

            $table->unsignedBigInteger('business_district_id')->nullable();
            $table->string('business_district_name')->nullable();
            $table->string('business_city')->nullable();
            $table->string('business_postcode', 10)->nullable();
            $table->string('business_state')->default('Kedah');

            $table->string('business_phone')->nullable();
            $table->string('business_fax')->nullable();
            $table->string('business_email')->nullable();

            $table->string('effluent_activity_type')->nullable();

            $table->foreign('business_district_id')
                ->references('district_id')
                ->on('lsank_districts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            $table->dropForeign(['business_district_id']);

            $table->dropColumn([
                'same_info',
                'business_name',
                'business_address_1',
                'business_address_2',
                'business_district_id',
                'business_district_name',
                'business_city',
                'business_postcode',
                'business_state',
                'business_phone',
                'business_fax',
                'business_email',
                'effluent_activity_type',
            ]);
        });
    }
};