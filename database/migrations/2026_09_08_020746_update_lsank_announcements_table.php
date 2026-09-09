<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lsank_announcements', function (Blueprint $table) {
            $table->string('category', 100)
                ->default('Umum')
                ->after('content');

            $table->string('type', 50)
                ->default('general')
                ->after('category');

            $table->boolean('is_important')
                ->default(false)
                ->after('status');

            $table->boolean('is_pinned')
                ->default(false)
                ->after('is_important');
        });

        // content -> message
        DB::statement(
            'ALTER TABLE lsank_announcements
             CHANGE content message LONGTEXT NOT NULL'
        );

        // publish_start -> start_at
        DB::statement(
            'ALTER TABLE lsank_announcements
             CHANGE publish_start start_at TIMESTAMP NULL DEFAULT NULL'
        );

        // publish_end -> end_at
        DB::statement(
            'ALTER TABLE lsank_announcements
             CHANGE publish_end end_at TIMESTAMP NULL DEFAULT NULL'
        );

        // status lama:
        // draft / published / unpublished
        //
        // status baru:
        // draft / published / archived

        DB::table('lsank_announcements')
            ->where('status', 'unpublished')
            ->update([
                'status' => 'draft',
            ]);

        DB::statement(
            "ALTER TABLE lsank_announcements
             MODIFY status ENUM(
                'draft',
                'published',
                'archived'
             ) NOT NULL DEFAULT 'draft'"
        );

        Schema::table('lsank_announcements', function (Blueprint $table) {
            $table->index('status');
            $table->index('category');
            $table->index('type');
            $table->index('is_important');
            $table->index('is_pinned');
            $table->index('start_at');
            $table->index('end_at');
        });
    }

    public function down(): void
    {
        Schema::table('lsank_announcements', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['category']);
            $table->dropIndex(['type']);
            $table->dropIndex(['is_important']);
            $table->dropIndex(['is_pinned']);
            $table->dropIndex(['start_at']);
            $table->dropIndex(['end_at']);
        });

        DB::statement(
            "ALTER TABLE lsank_announcements
             MODIFY status ENUM(
                'draft',
                'published',
                'unpublished'
             ) NOT NULL DEFAULT 'draft'"
        );

        DB::statement(
            'ALTER TABLE lsank_announcements
             CHANGE message content LONGTEXT NOT NULL'
        );

        DB::statement(
            'ALTER TABLE lsank_announcements
             CHANGE start_at publish_start TIMESTAMP NULL DEFAULT NULL'
        );

        DB::statement(
            'ALTER TABLE lsank_announcements
             CHANGE end_at publish_end TIMESTAMP NULL DEFAULT NULL'
        );

        Schema::table('lsank_announcements', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'type',
                'is_important',
                'is_pinned',
            ]);
        });
    }
};
