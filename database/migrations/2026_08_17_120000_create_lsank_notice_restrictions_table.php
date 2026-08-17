<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lsank_notice_restrictions')) {
            return;
        }

        Schema::create('lsank_notice_restrictions', function (Blueprint $table) {
            $table->id('restriction_id');

            // Kept without FK intentionally because existing notice-table
            // migrations may differ between current LSANK environments.
            $table->unsignedBigInteger('notice_id')->unique();

            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('license_id')->nullable()->index();
            $table->unsignedBigInteger('application_id')->nullable()->index();

            $table->string('notice_no')->nullable()->index();
            $table->string('notice_type', 20)->index();
            $table->string('category', 20)->nullable()->index();

            // active = all new / renewal and staff continuation are blocked.
            // responded/resolved/cancelled = no longer blocked.
            $table->string('restriction_status', 30)
                ->default('active')
                ->index();

            $table->string('response_required', 40)
                ->default('acknowledge');

            $table->text('restriction_reason')->nullable();
            $table->json('response_data')->nullable();

            $table->timestamp('responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('lsank_users')
                ->cascadeOnDelete();

            if (Schema::hasTable('lsank_licenses')) {
                $table->foreign('license_id')
                    ->references('license_id')
                    ->on('lsank_licenses')
                    ->nullOnDelete();
            }

            if (Schema::hasTable('lsank_applications')) {
                $table->foreign('application_id')
                    ->references('application_id')
                    ->on('lsank_applications')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_notice_restrictions');
    }
};
