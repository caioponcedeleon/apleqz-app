<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('area_id')->constrained()->cascadeOnDelete();
            $table->string('position');
            $table->string('company');
            $table->string('location')->nullable();
            $table->date('applied_at');
            $table->string('status');
            $table->date('rejected_at')->nullable();
            $table->date('interview_date')->nullable();
            $table->string('channel')->nullable();
            $table->text('notes')->nullable();
            $table->string('job_url')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'applied_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
