<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('summaries', function (Blueprint $table): void {
            if (!Schema::hasColumn('summaries', 'professional_summary')) {
                $table->text('professional_summary')->nullable()->after('executive_summary');
            }
            if (!Schema::hasColumn('summaries', 'citizen_summary')) {
                $table->text('citizen_summary')->nullable()->after('professional_summary');
            }
            if (!Schema::hasColumn('summaries', 'result_cards')) {
                $table->json('result_cards')->nullable()->after('practical_implications');
            }
            if (!Schema::hasColumn('summaries', 'structured_panels')) {
                $table->json('structured_panels')->nullable()->after('result_cards');
            }
            if (!Schema::hasColumn('summaries', 'supporting_passages')) {
                $table->json('supporting_passages')->nullable()->after('structured_panels');
            }
            if (!Schema::hasColumn('summaries', 'source_map')) {
                $table->json('source_map')->nullable()->after('supporting_passages');
            }
            if (!Schema::hasColumn('summaries', 'semantic_profile')) {
                $table->json('semantic_profile')->nullable()->after('source_map');
            }
        });
    }

    public function down(): void
    {
        Schema::table('summaries', function (Blueprint $table): void {
            $columns = [
                'professional_summary',
                'citizen_summary',
                'result_cards',
                'structured_panels',
                'supporting_passages',
                'source_map',
                'semantic_profile',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('summaries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
