<?php

namespace App\Services\Ai;

/**
 * Port FaqMatcher. Khớp từ trước khi gọi Gemini.
 */
class FaqMatcher
{
    /**
     * @param  list<object>  $faqs
     */
    public function match(string $message, array $faqs, float $threshold = 0.45): ?object
    {
        $queryTokens = $this->tokenize($message);
        if ($queryTokens === [] || $faqs === []) {
            return null;
        }
        $best = null;
        $bestScore = 0.0;
        foreach ($faqs as $faq) {
            $tokens = $this->tokenize($faq->question . ' ' . $faq->keywords);
            $score = $this->overlap($queryTokens, $tokens);
            $score += $this->phraseBonus($message, (string) $faq->keywords);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $faq;
            }
        }

        return ($best !== null && $bestScore >= $threshold) ? $best : null;
    }

    /** @return list<string> */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s,]+/u', ' ', $text) ?? $text;
        $parts = preg_split('/[\s,]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['tôi', 'bạn', 'là', 'có', 'của', 'và', 'cho', 'ạ', 'nhé', 'với', 'vậy', 'thì', 'làm', 'sao', 'để', 'như', 'thế', 'nào', 'gì', 'được', 'không', 'muốn', 'cần', 'hỏi', 'mình', 'em', 'shop', 'thể', 'hay', 'à', 'nhỉ', 'đâu', 'khi', 'ra', 'về', 'xin', 'dạ', 'vui', 'lòng', 'biết', 'giúp', 'hả', 'hen'];

        return array_values(array_diff(array_unique($parts), $stop));
    }

    /** @param  list<string>  $query @param  list<string>  $target */
    private function overlap(array $query, array $target): float
    {
        if ($query === [] || $target === []) {
            return 0.0;
        }

        return count(array_intersect($query, $target)) / count($query);
    }

    private function phraseBonus(string $message, string $keywords): float
    {
        $normalized = mb_strtolower(trim($message), 'UTF-8');
        $bonus = 0.0;
        foreach (preg_split('/[,;]+/u', $keywords) ?: [] as $phrase) {
            $phrase = trim(mb_strtolower($phrase, 'UTF-8'));
            if ($phrase !== '' && mb_strpos($normalized, $phrase) !== false) {
                $bonus = max($bonus, 0.35);
            }
        }

        return $bonus;
    }
}
