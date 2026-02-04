<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_guardian_errors', function (Blueprint $table): void {
            $table->id();
            $table->string('error_id')->unique();
            $table->string('exception_class');
            $table->string('error_code')->nullable();
            $table->text('message');
            $table->integer('status_code');
            $table->json('context')->nullable();
            $table->json('meta')->nullable();
            $table->string('request_method');
            $table->text('request_url');
            $table->json('request_headers')->nullable();
            $table->json('request_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('user_id')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->integer('occurrence_count')->default(1);
            $table->timestamp('first_occurred_at');
            $table->timestamp('last_occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status_code', 'created_at']);
            $table->index(['error_code', 'created_at']);
            $table->index(['is_resolved', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('api_guardian_error_trends', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('error_code')->nullable();
            $table->integer('status_code');
            $table->integer('count')->default(0);
            $table->json('hourly_distribution')->nullable();
            $table->timestamps();

            $table->unique(['date', 'error_code', 'status_code']);
            $table->index(['date', 'status_code']);
        });

        Schema::create('api_guardian_circuit_breakers', function (Blueprint $table): void {
            $table->id();
            $table->string('service');
            $table->string('operation')->nullable();
            $table->enum('state', ['closed', 'open', 'half_open'])->default('closed');
            $table->integer('failure_count')->default(0);
            $table->integer('failure_threshold');
            $table->integer('recovery_timeout');
            $table->integer('success_threshold');
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();

            $table->unique(['service', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_guardian_circuit_breakers');
        Schema::dropIfExists('api_guardian_error_trends');
        Schema::dropIfExists('api_guardian_errors');
    }
};
