<?php

namespace App\Support;

use App\Models\GuidanceArticle;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class GuidanceService
{
    /**
     * @return array{answer: string, title: ?string, related_page: ?string, steps: ?string, image_url: ?string, confidence: float, article_id: ?int, offer_ticket: bool, department?: ?string, source?: string}
     */
    public static function answer(string $message, ?string $department = null): array
    {
        $message = trim($message);
        if ($message === '') {
            return self::fallback('Please type a short question about Clickronix setup, billing, or integrations.');
        }

        // Local knowledge bank (FAQ + intent map) — no OpenAI required.
        $kb = ClickronixKnowledgeBank::answer($message);
        $db = self::answerFromArticles($message, $department);

        if ($kb && $db) {
            $winner = ($kb['confidence'] ?? 0) >= ($db['confidence'] ?? 0) ? $kb : $db;
            // Prefer KB for live-agent routing.
            if (($kb['source'] ?? '') === 'live_agent_intent') {
                $winner = $kb;
            }

            return $winner;
        }

        if ($kb) {
            return $kb;
        }

        if ($db) {
            return $db;
        }

        return self::fallback(
            "I couldn’t find a confident answer in the Clickronix knowledge bank.\n\n"
            ."Try asking about a specific page (Dashboard, Advanced View, Platform Integrate, Detection Panel, Domains, Billing) "
            ."or open a support ticket and our team will help.",
            true
        );
    }

    /**
     * @return array{answer: string, title: ?string, related_page: ?string, steps: ?string, image_url: ?string, confidence: float, article_id: ?int, offer_ticket: bool, source: string}|null
     */
    private static function answerFromArticles(string $message, ?string $department = null): ?array
    {
        if (! class_exists(GuidanceArticle::class)) {
            return null;
        }

        try {
            $articles = GuidanceArticle::query()
                ->where('is_published', true)
                ->when($department, fn ($q) => $q->where(function ($qq) use ($department): void {
                    $qq->whereNull('department')->orWhere('department', $department);
                }))
                ->orderByDesc('id')
                ->limit(80)
                ->get();
        } catch (\Throwable) {
            return null;
        }

        if ($articles->isEmpty()) {
            return null;
        }

        $needle = Str::lower($message);
        $best = null;
        $bestScore = 0.0;

        foreach ($articles as $article) {
            $score = self::score($needle, $article);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $article;
            }
        }

        if (! $best || $bestScore < 0.18) {
            return null;
        }

        $answer = trim((string) $best->answer);
        if ($best->related_page) {
            $answer .= "\n\nGo to: ".$best->related_page;
        }
        if ($best->steps) {
            $answer .= "\n\nNext steps:\n".$best->steps;
        }

        return [
            'answer' => $answer,
            'title' => $best->title,
            'related_page' => $best->related_page,
            'steps' => $best->steps,
            'image_url' => method_exists($best, 'imageUrl') ? $best->imageUrl() : null,
            'confidence' => round(min(1.0, $bestScore), 2),
            'article_id' => (int) $best->id,
            'offer_ticket' => $bestScore < 0.45,
            'source' => 'guidance_article',
        ];
    }

    private static function score(string $needle, GuidanceArticle $article): float
    {
        $haystacks = Collection::make([
            $article->title,
            $article->keywords,
            $article->answer,
        ])->filter()->map(fn ($v) => Str::lower((string) $v))->all();

        foreach ((array) ($article->question_variants ?? []) as $variant) {
            $haystacks[] = Str::lower((string) $variant);
        }

        $tokens = preg_split('/\W+/', $needle, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return 0.0;
        }

        $score = 0.0;
        foreach ($haystacks as $hay) {
            if ($hay === '') {
                continue;
            }
            if (str_contains($hay, $needle)) {
                $score += 1.0;
            }
            $hits = 0;
            foreach ($tokens as $token) {
                if (strlen($token) < 3) {
                    continue;
                }
                if (str_contains($hay, $token)) {
                    $hits++;
                }
            }
            $score += $hits / max(1, count($tokens));
        }

        return $score / max(1, count($haystacks));
    }

    /**
     * @return array{answer: string, title: ?string, related_page: ?string, steps: ?string, image_url: ?string, confidence: float, article_id: ?int, offer_ticket: bool, source: string}
     */
    private static function fallback(string $answer, bool $offerTicket = false): array
    {
        return [
            'answer' => $answer,
            'title' => null,
            'related_page' => null,
            'steps' => null,
            'image_url' => null,
            'confidence' => 0.0,
            'article_id' => null,
            'offer_ticket' => $offerTicket,
            'source' => 'fallback',
        ];
    }
}
