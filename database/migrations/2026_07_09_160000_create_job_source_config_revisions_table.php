<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_source_config_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('job_source_id')->constrained('job_sources')->cascadeOnDelete();
            $table->unsignedInteger('config_version');
            $table->jsonb('extraction_config');
            $table->timestamp('created_at');

            $table->unique(['job_source_id', 'config_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_source_config_revisions');
    }
};
