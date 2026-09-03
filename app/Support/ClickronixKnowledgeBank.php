<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Local Clickronix Copilot knowledge bank (no OpenAI / external LLM).
 * Answers come from FAQ + intent sections in resources/guidance/*.txt
 */
class ClickronixKnowledgeBank
{
    private const CACHE_KEY = 'clickronix.kb.entries.v2';

    private const CACHE_TTL_SECONDS = 3600;

    /** @var list<string> */
    private const AGENT_PHRASES = [
        'agent',
        'human',
        'live support',
        'live agent',
        'real person',
        'representative',
        'talk to someone',
        'connect me with support',
        'mujhe banda chahiye',
        'someone from clickronix',
        'speak to a person',
        'talk to a human',
    ];

    public static function isAvailable(): bool
    {
        return self::assistantPath() !== null || self::copilotPath() !== null;
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *   answer: string,
     *   title: ?string,
     *   related_page: ?string,
     *   steps: ?string,
     *   image_url: ?string,
     *   confidence: float,
     *   article_id: ?int,
     *   offer_ticket: bool,
     *   department: ?string,
     *   source: string
     * }|null
     */
    public static function answer(string $message): ?array
    {
        $message = trim($message);
        if ($message === '' || ! self::isAvailable()) {
            return null;
        }

        if ($agent = self::liveAgentReply($message)) {
            return $agent;
        }

        $needle = Str::lower($message);
        $best = null;
        $bestScore = 0.0;

        foreach (self::entries() as $entry) {
            $score = self::score($needle, $entry);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $entry;
            }
        }

        if (! $best || $bestScore < 0.16) {
            return null;
        }

        $answer = trim((string) $best['answer']);
        $related = $best['related_page'] ?? null;
        if ($related && ! str_contains($answer, 'Go to:')) {
            $answer .= "\n\nGo to:\n".$related;
        }

        return [
            'answer' => $answer,
            'title' => $best['title'] ?? null,
            'related_page' => $related,
            'steps' => null,
            'image_url' => null,
            'confidence' => round(min(1.0, $bestScore), 2),
            'article_id' => null,
            'offer_ticket' => $bestScore < 0.38,
            'department' => $best['department'] ?? null,
            'source' => 'knowledge_bank',
        ];
    }

    /**
     * @return list<array{title: string, answer: string, keywords: string, related_page: ?string, department: ?string}>
     */
    public static function entries(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            $entries = self::builtInEntries();

            if ($path = self::assistantPath()) {
                $entries = array_merge($entries, self::parseFaqFile(self::readUtf8($path)));
            }

            if ($path = self::copilotPath()) {
                $raw = self::readUtf8($path);
                $entries = array_merge($entries, self::parseCopilotSections($raw));
                $entries = array_merge($entries, self::parseIntentMap($raw));
            }

            return $entries;
        });
    }

    /**
     * @return list<array{title: string, answer: string, keywords: string, related_page: ?string, department: ?string}>
     */
    private static function builtInEntries(): array
    {
        return [
            [
                'title' => 'Campaign Performance',
                'answer' => "Campaigns live under Paid Advertising.\n\nGo to:\nPaid Advertising → Dashboard → Campaign Performance\n\nThere you can see each Google Ads campaign's clicks, invalid clicks, and protection results. For one campaign's IPs and detections, open Paid Advertising → Advanced View and filter by campaign.",
                'keywords' => 'campaign campaigns campaign performance google ads campaign table invalid clicks',
                'related_page' => 'Paid Advertising → Dashboard → Campaign Performance',
                'department' => null,
            ],
        ];
    }

    private static function readUtf8(string $path): string
    {
        $raw = (string) file_get_contents($path);
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $raw);

        return is_string($clean) ? $clean : $raw;
    }

    private static function assistantPath(): ?string
    {
        $path = resource_path('guidance/support-assistant-master.txt');

        return is_readable($path) ? $path : null;
    }

    private static function copilotPath(): ?string
    {
        $path = resource_path('guidance/copilot-master.txt');

        return is_readable($path) ? $path : null;
    }

    /**
     * @return array{answer: string, title: ?string, related_page: ?string, steps: ?string, image_url: ?string, confidence: float, article_id: ?int, offer_ticket: bool, department: ?string, source: string}|null
     */
    private static function liveAgentReply(string $message): ?array
    {
        $lower = Str::lower($message);
        $wantsAgent = false;
        foreach (self::AGENT_PHRASES as $phrase) {
            if (str_contains($lower, $phrase)) {
                $wantsAgent = true;
                break;
            }
        }

        if (! $wantsAgent) {
            return null;
        }

        $department = self::guessDepartment($lower);
        if ($department) {
            return [
                'answer' => "Absolutely — I can route you to **{$department}**.\n\n"
                    ."Reply with a short summary of the issue (domain / error / what you already tried), or open a support ticket from this chat and we'll hand it off with context.",
                'title' => 'Live agent routing',
                'related_page' => null,
                'steps' => null,
                'image_url' => null,
                'confidence' => 0.95,
                'article_id' => null,
                'offer_ticket' => true,
                'department' => $department,
                'source' => 'live_agent_intent',
            ];
        }

        return [
            'answer' => "Absolutely. I can route you to the right Clickronix specialist.\n\n"
                ."What do you need help with?\n"
                ."• Technical Support\n"
                ."• Google Ads Integration\n"
                ."• Traffic Protection Specialist\n"
                ."• Analytics Specialist\n"
                ."• Billing & Plans\n"
                ."• Account & Access\n"
                ."• Security Team\n"
                ."• Privacy & Compliance\n"
                ."• Sales / Account Specialist\n\n"
                .'Or open a ticket below and we will attach this chat transcript.',
            'title' => 'Live agent routing',
            'related_page' => null,
            'steps' => null,
            'image_url' => null,
            'confidence' => 0.9,
            'article_id' => null,
            'offer_ticket' => true,
            'department' => 'General Support',
            'source' => 'live_agent_intent',
        ];
    }

    private static function guessDepartment(string $lower): ?string
    {
        $map = [
            'Technical Support' => ['tracking script', 'tag install', 'page not loading', 'dashboard error', 'data mismatch'],
            'Google Ads Integration' => ['oauth', 'google ads', 'customer id', 'sync error', 'permission to access customer', 'reconnect google'],
            'Traffic Protection Specialist' => ['invalid click', 'blocking', 'vpn', 'proxy', 'repeated click', 'risk score', 'exclusion'],
            'Analytics Specialist' => ['visitor', 'session', 'journey', 'conversion', 'sources', 'page analytics'],
            'Billing & Plans' => ['billing', 'invoice', 'upgrade', 'downgrade', 'subscription', 'payment', 'plan limit'],
            'Account & Access' => ['login', 'invite', 'password', 'organization', 'profile', 'user access'],
            'Security Team' => ['2fa', 'two factor', 'api key', 'unauthorized', 'suspicious login'],
            'Privacy & Compliance' => ['gdpr', 'ccpa', 'retention', 'masking', 'session recording'],
            'Sales / Account Specialist' => ['pricing', 'enterprise', 'capacity', 'sales'],
        ];

        foreach ($map as $dept => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($lower, $needle)) {
                    return $dept;
                }
            }
        }

        return null;
    }

    /**
     * @return list<array{title: string, answer: string, keywords: string, related_page: ?string, department: ?string}>
     */
    private static function parseFaqFile(string $raw): array
    {
        $entries = [];
        if (! preg_match_all(
            '/FAQ\s+(\d+)\s*[—\-–]\s*(.+?)(?=\nFAQ\s+\d+\s*[—\-–]|\nCLICKRONIX|\n[A-Z]\.\s+[A-Z]|\z)/s',
            $raw,
            $matches,
            PREG_SET_ORDER
        )) {
            return $entries;
        }

        foreach ($matches as $match) {
            $title = trim($match[2]);
            $body = trim($match[0]);
            // Strip the FAQ header line from answer body.
            $answer = preg_replace('/^FAQ\s+\d+\s*[—\-–]\s*.+\n?/u', '', $body) ?? $body;
            $answer = trim($answer);
            if ($answer === '') {
                continue;
            }

            $related = null;
            if (preg_match('/Go to:\s*\n+(.+?)(?:\n\n|\z)/s', $answer, $go)) {
                $related = trim(preg_replace('/\s+/', ' ', $go[1]) ?? $go[1]);
            }

            $entries[] = [
                'title' => $title,
                'answer' => $answer,
                'keywords' => Str::lower($title.' '.$answer),
                'related_page' => $related,
                'department' => null,
            ];
        }

        return $entries;
    }

    /**
     * @return list<array{title: string, answer: string, keywords: string, related_page: ?string, department: ?string}>
     */
    private static function parseCopilotSections(string $raw): array
    {
        $entries = [];
        $parts = preg_split('/\n={10,}\n/', $raw) ?: [];
        $title = null;

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // Section titles are often a short numbered heading after separators.
            if (preg_match('/^(\d+\.\s+.+)$/m', $part, $m) && strlen($part) < 120) {
                $title = trim($m[1]);
                continue;
            }

            if ($title === null) {
                // First line as title when dense section.
                $lines = preg_split('/\n/', $part) ?: [];
                $first = trim((string) ($lines[0] ?? ''));
                if ($first !== '' && strlen($first) < 120 && ! str_starts_with($first, '-')) {
                    $title = $first;
                    $answer = trim(implode("\n", array_slice($lines, 1)));
                } else {
                    continue;
                }
            } else {
                $answer = $part;
                // If this chunk starts with a new numbered heading, peel it.
                if (preg_match('/^(\d+\.\s+[^\n]+)\n([\s\S]*)$/', $part, $m)) {
                    $title = trim($m[1]);
                    $answer = trim($m[2]);
                }
            }

            if ($answer === '' || strlen($answer) < 40) {
                $title = null;
                continue;
            }

            // Skip pure identity/style sections that are too policy-heavy for FAQ replies
            // but keep troubleshooting / page knowledge.
            $entries[] = [
                'title' => $title,
                'answer' => $answer,
                'keywords' => Str::lower($title.' '.$answer),
                'related_page' => self::extractGoTo($answer),
                'department' => null,
            ];
            $title = null;
        }

        return $entries;
    }

    /**
     * Natural-language intent phrases → canned guidance from nearby section text.
     *
     * @return list<array{title: string, answer: string, keywords: string, related_page: ?string, department: ?string}>
     */
    private static function parseIntentMap(string $raw): array
    {
        $entries = [];
        if (! preg_match('/NATURAL LANGUAGE INTENT UNDERSTANDING(.+?)(?=\n={10,}\n|\z)/s', $raw, $block)) {
            return $entries;
        }

        $chunk = $block[1];
        if (! preg_match_all('/((?:"[^"]+"\n?)+)\s*→\s*([a-z0-9_]+)/', $chunk, $matches, PREG_SET_ORDER)) {
            return $entries;
        }

        $intentGuides = [
            'invalid_click_investigation' => "Go to:\nPaid Advertising → Advanced View\n\nFilter for invalid / high-risk activity, then open the IP row to review risk score, detection reasons, device ID, and GCLID.",
            'repeated_click_investigation' => "Go to:\nPaid Advertising → Detection Panel → Repeated Click Detection\n\nOr investigate the IP in Paid Advertising → Advanced View for repeat click patterns.",
            'vpn_detection' => "Go to:\nPaid Advertising → Detection Panel\n\nReview VPN / proxy signals, then investigate matching IPs in Advanced View.",
            'proxy_detection' => "Go to:\nPaid Advertising → Detection Panel\n\nCheck proxy detection modules and review flagged IPs in Advanced View.",
            'datacenter_detection' => "Go to:\nPaid Advertising → Detection Panel\n\nLook for datacenter / hosting traffic signals, then confirm in Advanced View.",
            'bot_detection' => "Go to:\nPaid Advertising → Detection Panel\n\nReview bot / automation signals and inspect suspicious visitors in Advanced View.",
            'google_ads_connection' => "Go to:\nPaid Advertising → Platform Integrate\n\nCheck Connection Health, reconnect Google if needed, then run Sync Ads.",
            'tracking_accuracy_issue' => "Go to:\nPaid Advertising → Dashboard → Google Ads Click Summary\n\nCompare Total Google Ads Clicks vs Tracked Clicks. Confirm the tracking script is on all landing pages and GCLID capture is working.",
            'keyword_tracking_issue' => "Go to:\nPaid Advertising → Dashboard → Keyword Performance\n\nIf empty, verify Google Ads sync and that campaigns have keyword data available for the selected date range.",
            'traffic_sources' => "Go to:\nAnalytics → Sources\n\nReview where visitors came from for the selected domain and date range.",
            'visitor_journey' => "Go to:\nAnalytics → Journeys\n\nOpen a session to see pages visited and conversion steps.",
            'advanced_ip_investigation' => "Go to:\nPaid Advertising → Advanced View\n\nSearch the IP, then open the row for risk score, detections, device ID, and click IDs.",
            'add_domain' => "Go to:\nSite Management → Domains → Add Domain\n\nAdd the hostname, install the tracking tag, then verify first click / health.",
            'domain_tracking_issue' => "Go to:\nSite Management → Domains\n\nConfirm the domain is active, tag is installed, and Platform Integrate → Connection Health shows tracking healthy.",
            'upgrade_plan_or_limit' => "Go to:\nSettings → Billing\n\nReview your plan limits and upgrade if you need more domains or capacity.",
            'billing_invoice' => "Go to:\nSettings → Billing\n\nOpen invoices / payment history for the selected period.",
            'campaign_performance' => "Go to:\nPaid Advertising → Dashboard → Campaign Performance\n\nThis table shows each Google Ads campaign's clicks, invalid activity, and protection results for the selected domain and date range.",
        ];

        foreach ($matches as $match) {
            $phrasesBlock = $match[1];
            $intent = trim($match[2]);
            preg_match_all('/"([^"]+)"/', $phrasesBlock, $phrases);
            $phraseList = $phrases[1] ?? [];
            if ($phraseList === []) {
                continue;
            }

            $answer = $intentGuides[$intent]
                ?? ("I can help with that.\n\nIntent: {$intent}\n\nTell me the domain and what you already tried, and I’ll guide the next step.");

            $entries[] = [
                'title' => 'Intent: '.$intent,
                'answer' => $answer,
                'keywords' => Str::lower(implode(' ', $phraseList).' '.$intent.' '.$answer),
                'related_page' => self::extractGoTo($answer),
                'department' => null,
            ];
        }

        return $entries;
    }

    private static function extractGoTo(string $text): ?string
    {
        if (preg_match('/Go to:\s*\n+(.+?)(?:\n\n|\z)/s', $text, $m)) {
            return trim(preg_replace('/\s+/', ' ', $m[1]) ?? $m[1]);
        }

        return null;
    }

    /**
     * @param  array{title: string, answer: string, keywords: string, related_page: ?string, department: ?string}  $entry
     */
    private static function score(string $needle, array $entry): float
    {
        $haystacks = array_filter([
            Str::lower($entry['title']),
            Str::lower($entry['keywords']),
            Str::lower(Str::limit($entry['answer'], 800, '')),
        ]);

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
                $score += 1.2;
            }
            $hits = 0;
            $eligible = 0;
            foreach ($tokens as $token) {
                if (strlen($token) < 3) {
                    continue;
                }
                $eligible++;
                if (str_contains($hay, $token)) {
                    $hits++;
                }
            }
            if ($eligible > 0) {
                $score += $hits / $eligible;
            }
        }

        return $score / max(1, count($haystacks));
    }
}
