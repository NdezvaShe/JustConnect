<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'mfa_enabled')) {
                $table->boolean('mfa_enabled')->default(false)->after('otp_expires_at');
            }

            if (!Schema::hasColumn('users', 'mfa_channel')) {
                $table->string('mfa_channel', 20)->default('email')->after('mfa_enabled');
            }

            if (!Schema::hasColumn('users', 'mfa_security_question')) {
                $table->string('mfa_security_question')->nullable()->after('mfa_channel');
            }

            if (!Schema::hasColumn('users', 'mfa_security_answer_hash')) {
                $table->string('mfa_security_answer_hash')->nullable()->after('mfa_security_question');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'mfa_security_answer_hash')) {
                $table->dropColumn('mfa_security_answer_hash');
            }

            if (Schema::hasColumn('users', 'mfa_security_question')) {
                $table->dropColumn('mfa_security_question');
            }

            if (Schema::hasColumn('users', 'mfa_channel')) {
                $table->dropColumn('mfa_channel');
            }

            if (Schema::hasColumn('users', 'mfa_enabled')) {
                $table->dropColumn('mfa_enabled');
            }
        });
    }
};
