<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            if (!Schema::hasColumn('documents', 'summary_type')) {
                $table->string('summary_type', 40)->nullable()->after('extracted_text');
            }
        });

        Schema::table('summaries', function (Blueprint $table): void {
            if (!Schema::hasColumn('summaries', 'summary_type')) {
                $table->string('summary_type', 40)->default('general_user')->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('summaries', function (Blueprint $table): void {
            $table->dropColumn('summary_type');
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn('summary_type');
        });
    }
};
