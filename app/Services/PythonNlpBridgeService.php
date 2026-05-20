<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PythonNlpBridgeService
{
    public function isEnabled(): bool
    {
        return (bool) $this->setting('services.python_nlp.enabled', false);
    }

    public function analyse(string $text, string $filename = ''): ?array
    {
        return $this->post('/analyse', [
            'text' => $text,
            'filename' => $filename,
        ]);
    }

    public function embed(string $text): ?array
    {
        $payload = $this->post('/embed', ['text' => $text]);

        return is_array($payload['vector'] ?? null) ? $payload['vector'] : null;
    }

    public function learn(array $example): void
    {
        $this->post('/learn', ['example' => $example]);
    }

    private function post(string $path, array $payload): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $response = Http::timeout((int) $this->setting('services.python_nlp.timeout', 8))
                ->acceptJson()
                ->post(rtrim((string) $this->setting('services.python_nlp.base_url', 'http://127.0.0.1:8001'), '/') . $path, $payload);

            if ($response->failed()) {
                Log::warning('JustConnect: Python NLP microservice request failed.', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);

                return null;
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::notice('JustConnect: Python NLP microservice unavailable.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        try {
            return config($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
