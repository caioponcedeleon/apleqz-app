<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_records', function (Blueprint $table) {
            $table->id();
            $table->string('driver');
            $table->string('model');
            $table->string('purpose');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['driver', 'created_at']);
            $table->index(['purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_records');
    }
};
