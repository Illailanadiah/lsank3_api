<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('lsank_applications', 'review_data')) {
                $table->json('review_data')->nullable()->after('draft_data');
            }

            if (!Schema::hasColumn('lsank_applications', 'submitted_data')) {
                $table->json('submitted_data')->nullable()->after('review_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lsank_applications', function (Blueprint $table) {
            if (Schema::hasColumn('lsank_applications', 'submitted_data')) {
                $table->dropColumn('submitted_data');
            }

            if (Schema::hasColumn('lsank_applications', 'review_data')) {
                $table->dropColumn('review_data');
            }
        });
    }
};
