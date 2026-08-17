<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lsank_legal_referrals')) {
            return;
        }

        Schema::create(
            'lsank_legal_referrals',
            function (Blueprint $table) {
                $table->id('legal_referral_id');

                $table->unsignedBigInteger('notice_id')
                    ->unique();

                $table->unsignedBigInteger('user_id')
                    ->index();

                $table->unsignedBigInteger('license_id')
                    ->nullable()
                    ->index();

                $table->unsignedBigInteger('application_id')
                    ->nullable()
                    ->index();

                $table->string('notice_no')
                    ->nullable()
                    ->index();

                $table->string('notice_type', 20)
                    ->nullable()
                    ->index();

                $table->string('category', 20)
                    ->nullable()
                    ->index();

                $table->string('case_track', 20)
                    ->index();

                $table->string('referral_status', 30)
                    ->default('triggered')
                    ->index();

                $table->timestamp('notice_date')
                    ->nullable();

                $table->timestamp('due_at')
                    ->nullable()
                    ->index();

                $table->timestamp('triggered_at')
                    ->nullable()
                    ->index();

                $table->unsignedBigInteger('civil_case_id')
                    ->nullable()
                    ->index();

                $table->unsignedBigInteger('criminal_case_id')
                    ->nullable()
                    ->index();

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'lsank_legal_referrals'
        );
    }
};
