<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'lsank_license_termination_requests',
            function (Blueprint $table) {
                $table->bigIncrements('termination_request_id');

                $table->unsignedBigInteger('license_id');
                $table->unsignedBigInteger('application_id');

                $table->string('application_type', 30)
                    ->nullable();

                $table->text('reason');

                /*
                 * pending
                 * approved
                 * rejected
                 */
                $table->string('termination_status', 30)
                    ->default('pending');

                $table->unsignedBigInteger('requested_by_user_id')
                    ->nullable();

                $table->timestamp('requested_at')
                    ->nullable();

                $table->unsignedBigInteger('decided_by_user_id')
                    ->nullable();

                $table->text('director_remark')
                    ->nullable();

                $table->timestamp('decided_at')
                    ->nullable();

                /*
                 * not_started
                 * pending
                 * refunded
                 */
                $table->string('security_refund_status', 30)
                    ->default('not_started');

                $table->decimal(
                    'security_refund_amount',
                    12,
                    2
                )->nullable();

                $table->string(
                    'security_refund_reference',
                    255
                )->nullable();

                $table->text('security_refund_note')
                    ->nullable();

                $table->timestamp('security_refunded_at')
                    ->nullable();

                $table->unsignedBigInteger(
                    'security_refunded_by_user_id'
                )->nullable();

                $table->timestamps();

                $table->index(
                    ['license_id', 'termination_status'],
                    'idx_license_termination_status'
                );

                $table->index(
                    'application_id',
                    'idx_termination_application_id'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'lsank_license_termination_requests'
        );
    }
};
