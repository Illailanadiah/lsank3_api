<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `lsank_reports`
             MODIFY `report_status`
             ENUM('draft','submitted','cancelled')
             NOT NULL DEFAULT 'draft'"
        );
    }

    public function down(): void
    {
        DB::table('lsank_reports')
            ->where('report_status', 'cancelled')
            ->update([
                'report_status' => 'submitted',
            ]);

        DB::statement(
            "ALTER TABLE `lsank_reports`
             MODIFY `report_status`
             ENUM('draft','submitted')
             NOT NULL DEFAULT 'draft'"
        );
    }
};
