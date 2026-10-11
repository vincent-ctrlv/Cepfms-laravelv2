<?php

namespace App\Services;

use Throwable;
/**
 * CEPFMS — Anti-Spam, Heuristic Analysis, and Duplicate Detection Service
 */
class SpamService
{
    /**
     * Common promotional and spam keywords (multilingual: English / Tagalog / Spam patterns)
     */
    private const SPAM_KEYWORDS = [
        'casino', 'viagra', 'cialis', 'cryptocurrency', 'bitcoin', 'forex trading',
        'free bonus', 'buy cheap', 'seo service', 'backlink', 'porn', 'adult dating',
        'slot machine', 'gacor', 'judi online', 'whatsapp +', 'telegram @', 'earn \$\d+',
        'work from home and earn', 'click here to claim', 'winner of prize', 'gift card free',
        'sugar daddy', 'escort service', 'payday loan', 'fast cash loan'
    ];

    /**
     * Hard readability gate for citizen text — rejects obvious keyboard mashing
     * ("sdadadsadassss", "cccccccccc") before it is saved. Kept deliberately loose
     * so short real reports ("Walang tubig sa Purok 3") still pass.
     * Mirrored client-side in frontend/js/citizen.js (unreadableReason).
     *
     * @return string|null Why the text looks unreadable, or null if it looks fine.
     */
    public static function unreadableReason(string $text, int $minWords = 1): ?string
    {
        $lower = mb_strtolower($text, 'UTF-8');
        $words = preg_split('/\s+/u', trim($lower), -1, PREG_SPLIT_NO_EMPTY);
        $realWords = array_filter($words, fn($w) => preg_match('/\p{L}{2,}|\p{N}/u', $w));

        if (count($realWords) < $minWords) {
            return 'too_few_words';
        }
        // No real English/Filipino word runs 26+ letters without a break
        if (preg_match('/\p{L}{26,}/u', $lower)) {
            return 'long_word';
        }
        // Same letter 5+ times in a row ("sssss", "ccccc")
        if (preg_match('/(\p{L})\1{4,}/u', $lower)) {
            return 'repeated_letters';
        }
        // Long consonant-only stretch ("qwrtzxcv")
        if (preg_match('/[b-df-hj-np-tv-xz]{7,}/u', $lower)) {
            return 'consonant_run';
        }
        // Almost no vowels overall — only judged with enough letters to be reliable
        $letters = preg_replace('/[^\p{L}]+/u', '', $lower);
        $letterCount = mb_strlen($letters, 'UTF-8');
        if ($letterCount >= 10 && preg_match_all('/[aeiouy]/u', $letters) / $letterCount < 0.15) {
            return 'no_vowels';
        }
        return null;
    }

    /**
     * Compute normalized content hash for duplicate detection.
     */
    public static function computeContentHash(string $subject, string $description): string
    {
        $normalized = mb_strtolower(trim($subject . ' ' . $description), 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized);
        return hash('sha256', $normalized);
    }

    /**
     * Comprehensive spam and abuse analysis.
     *
     * @param string $subject
     * @param string $description
     * @param string $fullName
     * @param string $email
     * @param array  $meta Additional metadata: [ip, honeypot, form_loaded_at]
     * @return array{
     *   blocked: bool,
     *   block_message: string|null,
     *   spam_score: int,
     *   spam_flags: array<string>,
     *   content_hash: string,
     *   flag_label: string|null
     * }
     */
    public static function analyze(
        string $subject,
        string $description,
        string $fullName,
        string $email,
        array $meta = []
    ): array {
        $spamScore = 0;
        $spamFlags = [];
        $contentHash = self::computeContentHash($subject, $description);

        $combinedText = $subject . "\n" . $description;
        $ip = $meta['ip'] ?? client_ip();
        $honeypot = trim((string) ($meta['honeypot'] ?? ''));
        $formLoadedAt = (int) ($meta['form_loaded_at'] ?? 0);

        // 1. Honeypot check — bots filling invisible fields
        if ($honeypot !== '') {
            return [
                'blocked' => true,
                'block_message' => 'Your submission could not be verified. Please try again.',
                'spam_score' => 100,
                'spam_flags' => ['Bot Detected (Honeypot)'],
                'content_hash' => $contentHash,
                'flag_label' => 'Bot Activity',
            ];
        }

        // 2. Rapid submission timing check (less than 3 seconds to fill form)
        if ($formLoadedAt > 0) {
            $elapsedSeconds = time() - $formLoadedAt;
            if ($elapsedSeconds >= 0 && $elapsedSeconds < 3) {
                $spamScore += 35;
                $spamFlags[] = 'Rapid Submission';
            }
        }

        // 3. Duplicate Submission Detection via database query
        $duplicateCheck = self::checkDuplicates($contentHash, $email, $ip);
        if ($duplicateCheck['hard_duplicate']) {
            return [
                'blocked' => true,
                'block_message' => 'This submission appears to be a duplicate. Please review your information before submitting again.',
                'spam_score' => 90,
                'spam_flags' => ['Duplicate Submission'],
                'content_hash' => $contentHash,
                'flag_label' => 'Duplicate',
            ];
        } elseif ($duplicateCheck['soft_duplicate']) {
            $spamScore += 40;
            $spamFlags[] = 'Duplicate Risk';
        }

        // 4. Excessive Repeated Characters (e.g. "aaaaaaa", "!!!!!!!!!!", "?????")
        if (preg_match('/(.)\1{6,}/u', $combinedText) || preg_match('/[!?.]{5,}/', $combinedText)) {
            $spamScore += 25;
            $spamFlags[] = 'Excessive Repeated Characters';
        }

        // 5. Excessive URLs / Link count
        $urlCount = preg_match_all('#https?://|www\.[a-z0-9\-]+|[a-z0-9\-]+\.(com|net|org|xyz|top|ru|cn|biz|info|site|online)/?#i', $combinedText);
        if ($urlCount >= 2) {
            $spamScore += 30;
            $spamFlags[] = 'Excessive Links';
        }

        // 6. Promotional / Spam Keywords
        $lowerText = mb_strtolower($combinedText, 'UTF-8');
        $foundKeywords = [];
        foreach (self::SPAM_KEYWORDS as $kw) {
            if (str_contains($lowerText, $kw)) {
                $foundKeywords[] = $kw;
            }
        }
        if (!empty($foundKeywords)) {
            $spamScore += min(50, count($foundKeywords) * 25);
            $spamFlags[] = 'Promotional Content';
        }

        // 7. Meaningless or Low-Effort Content (e.g., no alphanumeric words or pure punctuation)
        $cleanAlnum = preg_replace('/[^\p{L}\p{N}]+/u', '', $description);
        if (mb_strlen($cleanAlnum, 'UTF-8') < 8) {
            $spamScore += 20;
            $spamFlags[] = 'Low Content Quality';
        }

        // 8. Gibberish / Keyboard-Mash Detection — real English/Filipino/Taglish text
        // always carries a healthy mix of vowels; random typing ("qdqdqdqwdqdqdw")
        // typically doesn't. Only judged once there's enough letters to be reliable.
        $descLettersOnly = preg_replace('/[^\p{L}]+/u', '', mb_strtolower($description, 'UTF-8'));
        $letterCount = mb_strlen($descLettersOnly, 'UTF-8');
        if ($letterCount >= 10) {
            $vowelCount = preg_match_all('/[aeiou]/u', $descLettersOnly);
            if (($vowelCount / $letterCount) < 0.15) {
                $spamScore += 30;
                $spamFlags[] = 'Possible Gibberish';
            }
        }

        // Determine primary moderation flag label if spam score is noticeable
        $flagLabel = null;
        if ($spamScore >= 40 || !empty($spamFlags)) {
            if ($spamScore >= 70) {
                // Very high combined score overrides individual flag labels
                $flagLabel = 'Potential Spam';
            } elseif (in_array('Duplicate Risk', $spamFlags, true)) {
                $flagLabel = 'Duplicate';
            } elseif (in_array('Excessive Links', $spamFlags, true)) {
                $flagLabel = 'Excessive Links';
            } elseif (in_array('Possible Gibberish', $spamFlags, true) || in_array('Low Content Quality', $spamFlags, true)) {
                $flagLabel = 'Low Quality / Gibberish';
            } elseif ($spamScore >= 50) {
                $flagLabel = 'Potential Spam';
            } else {
                $flagLabel = 'Suspicious Activity';
            }
        }

        return [
            'blocked' => false,
            'block_message' => null,
            'spam_score' => $spamScore,
            'spam_flags' => $spamFlags,
            'content_hash' => $contentHash,
            'flag_label' => $flagLabel,
        ];
    }

    /**
     * Check if identical content hash was recently submitted.
     * @return array{hard_duplicate: bool, soft_duplicate: bool}
     */
    private static function checkDuplicates(string $contentHash, string $email, string $ip): array
    {
        try {
            $pdo = db();
            // Hard duplicate: identical content from same email or IP within the last 2 hours
            $stmt = $pdo->prepare("
                SELECT s.id, s.created_at
                FROM submissions s
                JOIN citizens c ON c.id = s.citizen_id
                WHERE s.content_hash = ?
                  AND (c.email = ? OR s.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR))
                LIMIT 1
            ");
            $stmt->execute([$contentHash, $email]);
            $match = $stmt->fetch();

            if ($match) {
                return ['hard_duplicate' => true, 'soft_duplicate' => true];
            }

            // Soft duplicate: identical content submitted anywhere in the last 24 hours
            $stmt2 = $pdo->prepare("
                SELECT id FROM submissions
                WHERE content_hash = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                LIMIT 1
            ");
            $stmt2->execute([$contentHash]);
            if ($stmt2->fetch()) {
                return ['hard_duplicate' => false, 'soft_duplicate' => true];
            }
        } catch (Throwable $e) {
            logger()->warning('[CEPFMS] Duplicate check error: ' . $e->getMessage());
        }

        return ['hard_duplicate' => false, 'soft_duplicate' => false];
    }
}
