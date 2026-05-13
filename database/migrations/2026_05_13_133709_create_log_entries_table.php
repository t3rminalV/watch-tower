<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();
            $table->string('trace_id', 36)->nullable()->index();

            $table->string('level', 20);
            $table->text('message');
            $table->string('source_type', 50)->nullable();
            $table->string('source_label')->nullable();
            $table->string('user_name')->nullable();
            $table->json('context')->nullable();

            $table->string('environment', 50)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'level']);
            $table->index(['project_id', 'source_type']);
            $table->index(['project_id', 'user_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_entries');
    }
};
