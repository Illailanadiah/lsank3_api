<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_licenses', function (Blueprint $table) {
            $table->string('activity_name', 255)
                ->nullable()
                ->after('license_type');

            $table->text('activity_location')
                ->nullable()
                ->after('activity_name');

            $table->uuid('qr_token')
                ->nullable()
                ->unique()
                ->after('license_status_id');

            $table->string('qr_payload_hash', 64)
                ->nullable()
                ->unique()
                ->after('qr_token');

            $table->timestamp('generated_at')
                ->nullable()
                ->after('license_pdf_path');

            $table->timestamp('pdf_downloaded_at')
                ->nullable()
                ->after('generated_at');

            $table->timestamp('printed_at')
                ->nullable()
                ->after('pdf_downloaded_at');

            $table->timestamp('qr_downloaded_at')
                ->nullable()
                ->after('printed_at');

            $table->unique(
                'application_id',
                'uq_lsank_licenses_application_id'
            );
        });
    }

    public function down(): void
    {
        Schema::table('lsank_licenses', function (Blueprint $table) {
            $table->dropUnique(
                'uq_lsank_licenses_application_id'
            );

            $table->dropUnique([
                'qr_token',
            ]);

            $table->dropUnique([
                'qr_payload_hash',
            ]);

            $table->dropColumn([
                'activity_name',
                'activity_location',
                'qr_token',
                'qr_payload_hash',
                'generated_at',
                'pdf_downloaded_at',
                'printed_at',
                'qr_downloaded_at',
            ]);
        });
    }
};