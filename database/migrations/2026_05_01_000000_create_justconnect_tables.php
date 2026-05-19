<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 191)->unique();
            $table->string('password');
            $table->string('organisation', 191)->nullable();
            $table->string('role')->default('Legal Professional');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('otp_code', 6)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->string('mfa_channel', 20)->default('email');
            $table->string('mfa_security_question')->nullable();
            $table->string('mfa_security_answer_hash')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime_type', 100)->default('application/pdf');
            $table->unsignedInteger('file_size')->default(0);
            $table->unsignedSmallInteger('page_count')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->string('summary_type', 40)->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('summaries', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('document_id');
            $table->unsignedInteger('user_id');
            $table->string('summary_type', 40)->default('general_user');
            $table->string('document_type', 100)->nullable();
            $table->string('case_number', 100)->nullable();
            $table->json('parties')->nullable();
            $table->string('date_of_document', 60)->nullable();
            $table->string('court', 150)->nullable();
            $table->string('judge', 150)->nullable();
            $table->text('executive_summary')->nullable();
            $table->text('professional_summary')->nullable();
            $table->text('citizen_summary')->nullable();
            $table->text('key_findings')->nullable();
            $table->json('key_obligations')->nullable();
            $table->text('legal_principles')->nullable();
            $table->text('outcome')->nullable();
            $table->text('practical_implications')->nullable();
            $table->json('result_cards')->nullable();
            $table->json('structured_panels')->nullable();
            $table->json('supporting_passages')->nullable();
            $table->json('source_map')->nullable();
            $table->json('semantic_profile')->nullable();
            $table->json('nlp_entities')->nullable();
            $table->json('nlp_keywords')->nullable();
            $table->string('nlp_sentiment', 20)->nullable();
            $table->float('nlp_readability')->nullable();
            $table->string('nlp_language', 10)->nullable()->default('en');
            $table->json('nlp_legal_categories')->nullable();
            $table->string('ai_provider', 30)->nullable();
            $table->unsignedInteger('processing_ms')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index('document_id');
            $table->index('user_id');
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('downloads', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('summary_id');
            $table->timestamp('downloaded_at')->useCurrent();

            $table->index('user_id');
            $table->index('summary_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('summary_id')->references('id')->on('summaries')->cascadeOnDelete();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        if (filter_var(env('SEED_DEMO_USER', true), FILTER_VALIDATE_BOOL)) {
            DB::table('users')->insert([
                'first_name' => env('DEMO_USER_FIRST_NAME', 'Demo'),
                'last_name' => env('DEMO_USER_LAST_NAME', 'User'),
                'email' => env('DEMO_USER_EMAIL', 'demo@justconnect.zw'),
                'password' => Hash::make(env('DEMO_USER_PASSWORD', 'Admin@2024!')),
                'organisation' => env('DEMO_USER_ORGANISATION', 'JustConnect Legal'),
                'role' => 'Legal Professional',
                'email_verified_at' => now(),
                'mfa_enabled' => false,
                'mfa_channel' => 'email',
                'mfa_security_question' => null,
                'mfa_security_answer_hash' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('downloads');
        Schema::dropIfExists('summaries');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('users');
    }
};
