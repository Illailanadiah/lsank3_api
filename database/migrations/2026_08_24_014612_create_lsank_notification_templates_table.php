<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lsank_notification_templates', function (Blueprint $table) {
            $table->bigIncrements('template_id');

            $table->string('event_type', 120);
            $table->string('audience', 80);
            $table->string('channel', 30);

            $table->string('language', 10)->default('ms');

            $table->string('subject_template', 255)->nullable();
            $table->string('title_template', 255)->nullable();
            $table->text('body_template');

            $table->string('provider_template_name', 255)->nullable();
            $table->json('provider_parameter_keys')->nullable();

            $table->string('severity', 30)->default('info');
            $table->unsignedTinyInteger('priority')->default(3);

            $table->boolean('action_required')->default(false);
            $table->string('action_label', 100)->nullable();

            $table->boolean('show_as_ribbon')->default(false);
            $table->unsignedSmallInteger('ribbon_duration_seconds')->default(7);

            $table->boolean('mandatory')->default(false);
            $table->boolean('is_enabled')->default(true);

            $table->timestamps();

            $table->unique(
                ['event_type', 'audience', 'channel', 'language'],
                'uq_lsank_notif_template_event_audience_channel_lang'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lsank_notification_templates');
    }
};