<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_moment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('frequency')->default('once');
            $table->dateTime('remind_at');
            $table->text('custom_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('channel')->default('mail');
            $table->timestamps();

            $table->index(['is_active', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_reminders');
    }
};
