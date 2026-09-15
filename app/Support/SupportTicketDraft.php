<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Copilot → support ticket drafts must carry real issue detail, not greetings.
 */
class SupportTicketDraft
{
    /** @var list<string> */
    private const TRIVIAL = [
        'hi', 'hii', 'hiii', 'hello', 'hey', 'yo', 'sup', 'hola', 'thanks', 'thank you',
        'ok', 'okay', 'yes', 'no', 'test', 'testing', 'help', 'please help', 'assalam',
        'salam', 'good morning', 'good evening', 'good afternoon',
    ];

    /**
     * @return array{ok: bool, message: ?string}
     */
    public static function validate(string $subject, string $body): array
    {
        $subject = trim(preg_replace('/\s+/', ' ', $subject) ?? $subject);
        $body = trim($body);

        if (mb_strlen($subject) < 8) {
            return ['ok' => false, 'message' => 'Subject needs at least 8 characters — summarize the issue (e.g. “Domain tag not detecting visits”).'];
        }

        if (mb_strlen($body) < 40) {
            return ['ok' => false, 'message' => 'Description needs at least 40 characters. Include what you tried, the page/domain, and any error you see.'];
        }

        if (self::isTrivial($subject) || self::isTrivial($body)) {
            return ['ok' => false, 'message' => 'A greeting or “hi” is not enough for a ticket. Describe the product issue so support can help.'];
        }

        $subjectWords = self::contentWordCount($subject);
        $bodyWords = self::contentWordCount($body);
        if ($subjectWords < 2 || $bodyWords < 6) {
            return ['ok' => false, 'message' => 'Add more detail — subject and description should explain the problem in plain words.'];
        }

        // Same short phrase repeated as subject+body is not actionable.
        if (Str::lower($subject) === Str::lower(Str::limit($body, 120, '')) && $bodyWords < 12) {
            return ['ok' => false, 'message' => 'Expand the description beyond the subject line with steps to reproduce or context.'];
        }

        return ['ok' => true, 'message' => null];
    }

    public static function isTrivial(string $text): bool
    {
        $flat = Str::lower(trim(preg_replace('/\s+/', ' ', $text) ?? $text));
        $flat = rtrim($flat, "!.?");

        if ($flat === '' || mb_strlen($flat) < 4) {
            return true;
        }

        if (in_array($flat, self::TRIVIAL, true)) {
            return true;
        }

        // "hi hi", "hello hello"
        $parts = preg_split('/\s+/', $flat) ?: [];
        if (count($parts) <= 2 && collect($parts)->every(fn ($p) => in_array($p, self::TRIVIAL, true) || mb_strlen($p) < 3)) {
            return true;
        }

        return false;
    }

    private static function contentWordCount(string $text): int
    {
        $tokens = preg_split('/\W+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = 0;
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2 || in_array($token, self::TRIVIAL, true)) {
                continue;
            }
            $n++;
        }

        return $n;
    }
}
