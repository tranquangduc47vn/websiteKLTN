<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Gemini generateContent. Key chỉ ở header, không gắn vào URL.
 */
class GeminiClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array{ok: bool, content: string, error: string}
     */
    public function complete(array $messages): array
    {
        $key = (string) config('ai.gemini_api_key');
        if ($key === '') {
            return ['ok' => false, 'content' => '', 'error' => 'missing_api_key'];
        }

        $system = '';
        $contents = [];
        foreach ($messages as $message) {
            $text = trim((string) ($message['content'] ?? ''));
            if ($text === '') {
                continue;
            }
            if (($message['role'] ?? '') === 'system') {
                $system .= ($system === '' ? '' : "\n\n") . $text;
                continue;
            }
            $role = ($message['role'] ?? '') === 'assistant' ? 'model' : 'user';
            $last = count($contents) - 1;
            if ($last >= 0 && $contents[$last]['role'] === $role) {
                $contents[$last]['parts'][0]['text'] .= "\n" . $text;
            } else {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }
        if ($contents === []) {
            return ['ok' => false, 'content' => '', 'error' => 'empty_messages'];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) config('ai.temperature'),
                'maxOutputTokens' => (int) config('ai.max_tokens'),
            ],
        ];
        if ($system !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $url = rtrim((string) config('ai.gemini_endpoint'), '/') . '/' . config('ai.gemini_model') . ':generateContent';
        $timeout = max(1, (int) config('ai.timeout'));

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(10, $timeout))
                ->withHeaders(['x-goog-api-key' => $key])
                ->acceptJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('ai.gemini.transport', ['error' => 'timeout_or_network']);

            return ['ok' => false, 'content' => '', 'error' => 'timeout'];
        }

        $status = $response->status();
        if ($status === 429) {
            Log::warning('ai.gemini.http', ['status' => 429]);

            return ['ok' => false, 'content' => '', 'error' => 'rate_limited'];
        }
        if ($status === 401 || $status === 403) {
            Log::warning('ai.gemini.http', ['status' => $status]);

            return ['ok' => false, 'content' => '', 'error' => 'unauthorized'];
        }
        if ($status >= 500) {
            Log::warning('ai.gemini.http', ['status' => $status]);

            return ['ok' => false, 'content' => '', 'error' => 'server_error'];
        }
        if (! $response->successful()) {
            Log::warning('ai.gemini.http', ['status' => $status]);

            return ['ok' => false, 'content' => '', 'error' => 'bad_response'];
        }

        $text = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
        if (trim($text) === '') {
            return ['ok' => false, 'content' => '', 'error' => 'empty_response'];
        }

        return ['ok' => true, 'content' => trim($text), 'error' => ''];
    }
}
