<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AnalysisProgressService
{
    public function start(int $documentId): void
    {
        $this->put($documentId, [
            'status' => 'processing',
            'progress' => 5,
            'step' => 'Preparing analysis...',
            'message' => null,
        ]);
    }

    public function update(int $documentId, string $status, int $progress, string $step, ?string $message = null): void
    {
        $this->put($documentId, [
            'status' => $status,
            'progress' => max(0, min(100, $progress)),
            'step' => $step,
            'message' => $message,
        ]);
    }

    public function get(int $documentId): array
    {
        return Cache::get($this->key($documentId), [
            'status' => 'pending',
            'progress' => 0,
            'step' => 'Waiting to start...',
            'message' => null,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function clear(int $documentId): void
    {
        Cache::forget($this->key($documentId));
    }

    private function put(int $documentId, array $payload): void
    {
        Cache::put(
            $this->key($documentId),
            $payload + ['updated_at' => now()->toIso8601String()],
            now()->addHours(6)
        );
    }

    private function key(int $documentId): string
    {
        return 'document_analysis_progress_' . $documentId;
    }
}
