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

        return $this->postGenerateContent($payload, max(1, (int) config('ai.timeout')));
    }

    /**
     * Một lượt user + parts (text, inlineData ảnh, …).
     *
     * @param  list<array<string, mixed>>  $userParts
     * @param  array<string, mixed>  $generationConfig
     * @return array{ok: bool, content: string, error: string}
     */
    public function generateFromUserParts(
        array $userParts,
        string $system = '',
        array $generationConfig = []
    ): array {
        if ($userParts === []) {
            return ['ok' => false, 'content' => '', 'error' => 'empty_messages'];
        }

        $defaults = [
            'temperature' => (float) config('ai.temperature'),
            'maxOutputTokens' => (int) config('ai.max_tokens'),
        ];
        $payload = [
            'contents' => [['role' => 'user', 'parts' => $userParts]],
            'generationConfig' => array_merge($defaults, $generationConfig),
        ];
        if (trim($system) !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => trim($system)]]];
        }

        $timeout = max(1, (int) config('ai.timeout'));
        $customTimeout = (int) ($generationConfig['timeout'] ?? 0);
        if ($customTimeout > 0) {
            $timeout = $customTimeout;
        }
        unset($payload['generationConfig']['timeout']);

        return $this->postGenerateContent($payload, $timeout);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, content: string, error: string}
     */
    private function postGenerateContent(array $payload, int $timeout): array
    {
        $key = (string) config('ai.gemini_api_key');
        if ($key === '') {
            return ['ok' => false, 'content' => '', 'error' => 'missing_api_key'];
        }

        $url = rtrim((string) config('ai.gemini_endpoint'), '/') . '/' . config('ai.gemini_model') . ':generateContent';

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout(min(25, $timeout))
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