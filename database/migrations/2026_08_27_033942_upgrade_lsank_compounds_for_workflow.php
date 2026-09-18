<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lsank_compounds')) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | LINKAGE
        |--------------------------------------------------------------------------
        */

        Schema::table('lsank_compounds', function (Blueprint $table) {
            $table->unsignedBigInteger('inspection_report_id')
                ->nullable()
                ->after('compound_id')
                ->index();

            $table->unsignedBigInteger('notice_id')
                ->nullable()
                ->after('inspection_report_id')
                ->index();

            $table->unsignedBigInteger('invoice_id')
                ->nullable()
                ->after('notice_id')
                ->index();

            $table->unsignedBigInteger('legal_referral_id')
                ->nullable()
                ->after('invoice_id')
                ->index();

            $table->unsignedBigInteger('application_id')
                ->nullable()
                ->after('license_id')
                ->index();

            $table->unsignedBigInteger('user_id')
                ->nullable()
                ->after('application_id')
                ->index();
        });

        /*
        |--------------------------------------------------------------------------
        | COMPOUND SNAPSHOT DATA
        |--------------------------------------------------------------------------
        */

        Schema::table('lsank_compounds', function (Blueprint $table) {
            $table->string('file_no', 150)
                ->nullable()
                ->after('user_id');

            $table->string('license_no', 150)
                ->nullable()
                ->after('file_no');

            $table->string('party_name', 255)
                ->nullable()
                ->after('license_no');

            $table->string('register_no', 150)
                ->nullable()
                ->after('party_name');

            $table->string('compound_type', 150)
                ->nullable()
                ->after('register_no');

            $table->string('district', 150)
                ->nullable()
                ->after('compound_type');

            $table->text('location')
                ->nullable()
                ->after('district');

            $table->string('section_regulation', 255)
                ->nullable()
                ->after('location');
        });

        /*
        |--------------------------------------------------------------------------
        | WORKFLOW
        |--------------------------------------------------------------------------
        |
        | Existing compound_status_id is retained for legacy compatibility.
        |
        | workflow_status:
        |
        | draft
        | issued
        | paid
        | overdue
        | escalated
        | cancelled
        |
        */

        Schema::table('lsank_compounds', function (Blueprint $table) {
            $table->string('workflow_status', 30)
                ->default('draft')
                ->after('compound_status_id')
                ->index();

            $table->timestamp('issued_at')
                ->nullable()
                ->after('workflow_status');

            $table->timestamp('due_at')
                ->nullable()
                ->after('issued_at')
                ->index();

            $table->timestamp('paid_at')
                ->nullable()
                ->after('due_at');

            $table->timestamp('overdue_at')
                ->nullable()
                ->after('paid_at');

            $table->timestamp('escalated_at')
                ->nullable()
                ->after('overdue_at');

            $table->unsignedBigInteger('updated_by')
                ->nullable()
                ->after('created_by');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('lsank_compounds')) {
            return;
        }

        Schema::table('lsank_compounds', function (Blueprint $table) {
            $table->dropColumn([
                'inspection_report_id',
                'notice_id',
                'invoice_id',
                'legal_referral_id',
                'application_id',
                'user_id',

                'file_no',
                'license_no',
                'party_name',
                'register_no',
                'compound_type',
                'district',
                'location',
                'section_regulation',

                'workflow_status',
                'issued_at',
                'due_at',
                'paid_at',
                'overdue_at',
                'escalated_at',

                'updated_by',
            ]);
        });
    }
};