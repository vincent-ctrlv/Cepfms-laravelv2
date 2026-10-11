<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;
/**
 * CEPFMS — AI Classification Service
 *
 * Classifies a citizen submission's free-text description (English / Filipino /
 * Taglish / informal grammar) into:
 *   submissionType, category, priority, summary, confidence, reasoning
 *
 * Uses Google Gemini via REST API when GEMINI_API_KEY is configured (GEMINI_MODEL,
 * retried once on GEMINI_FALLBACK_MODEL if the first is overloaded).
 * Falls back to a deterministic keyword classifier when no key is set or the
 * API call fails, so submission creation is NEVER blocked by AI availability.
 */

class AIClassifier
{
    public const CATEGORIES = [
        'Infrastructure & Roads', 'Public Safety', 'Sanitation & Waste',
        'Parks & Recreation', 'Transport & Transit', 'Licensing & Permits',
        'Noise & Disturbance', 'Healthcare Services', 'Education', 'Other',
    ];

    public const PRIORITIES = ['Critical', 'High', 'Medium', 'Low'];
    public const TYPES = ['feedback', 'complaint', 'proposal'];
    public const SENTIMENTS = ['Positive', 'Neutral', 'Negative'];

    /**
     * @return array{submissionType:string,category:string,priority:string,summary:string,confidence:float,reasoning:string,sentiment:string,sentiment_score:float,readable:bool}
     */
    public static function classify(string $subject, string $description): array
    {
        $aiEnabled = self::isEnabled();

        if ($aiEnabled && config('cepfms.gemini.api_key') !== '') {
            try {
                $result = self::classifyWithGemini($subject, $description);
                if ($result !== null) {
                    return $result;
                }
            } catch (Throwable $e) {
                logger()->warning('[CEPFMS] Gemini classification failed, falling back: ' . $e->getMessage());
            }
        }

        return self::classifyWithKeywords($subject, $description);
    }

    private static function isEnabled(): bool
    {
        try {
            $val = DB::table('settings')->where('setting_key', 'ai_classification_enabled')->value('setting_value');
            return $val === null ? true : ((string) $val === '1');
        } catch (Throwable $e) {
            return true;
        }
    }

    private static function classifyWithGemini(string $subject, string $description): ?array
    {
        $prompt = self::buildPrompt($subject, $description);

        $body = json_encode([
            'contents' => [[
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
                'responseMimeType' => 'application/json',
            ],
        ]);

        // Primary model first; on overload/rate-limit/retired-model errors,
        // one retry on the fallback model before giving up.
        $models = array_values(array_unique([config('cepfms.gemini.model'), config('cepfms.gemini.fallback_model')]));
        $response = null;
        foreach ($models as $i => $model) {
            $ch = curl_init(config('cepfms.gemini.api_base') . rawurlencode($model) . ':generateContent?key=' . urlencode(config('cepfms.gemini.api_key')));
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_TIMEOUT        => $i === 0 ? 15 : 12,
                CURLOPT_CONNECTTIMEOUT => 6,
            ]);
            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw !== false && !$curlError && $httpCode < 400) {
                $response = $raw;
                break;
            }

            $apiMessage = is_string($raw) ? (json_decode($raw, true)['error']['message'] ?? '') : '';
            logger()->warning("[CEPFMS] Gemini $model HTTP $httpCode: " . ($curlError ?: mb_substr($apiMessage, 0, 200)));
            $retryable = $curlError || in_array($httpCode, [404, 429], true) || $httpCode >= 500;
            if (!$retryable) {
                return null;
            }
        }
        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response, true);
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$text) {
            return null;
        }

        $text = preg_replace('/^```json|```$/m', '', trim($text));
        $parsed = json_decode(trim($text), true);
        if (!is_array($parsed)) {
            return null;
        }

        return self::normalize($parsed);
    }

    private static function buildPrompt(string $subject, string $description): string
    {
        $categories = implode(', ', self::CATEGORIES);
        return <<<PROMPT
You are a public-sector citizen feedback triage assistant for a Philippine local government's Legislative Services Office. Citizens write in English, Filipino, Taglish, informal grammar, or with spelling mistakes.

Classify the submission below and respond with ONLY a raw JSON object (no markdown, no commentary) with these exact keys:
- submissionType: one of "feedback", "complaint", "proposal"
- category: one of [$categories]
- priority: one of "Critical", "High", "Medium", "Low"
- summary: a concise one-sentence English summary (max 25 words)
- confidence: a number between 0 and 1
- reasoning: a short explanation (max 20 words) of why this classification was chosen
- sentiment: one of "Positive", "Neutral", "Negative" — the citizen's emotional tone
- sentiment_score: a number from -1 (very negative/angry) to 1 (very positive/grateful), 0 is neutral
- readable: true or false — false ONLY when the description is gibberish, keyboard-mashing, random
  letters, or otherwise carries no real message (e.g. "asdasd qweqwe", "dawdadwad cszcxz").
  Bad grammar, misspellings, slang, Taglish, or very short but real messages are still true.

Guidance on the parts that are easy to get wrong:
- "feedback" vs "complaint": a complaint reports something broken, unsafe, or not working that
  needs to be fixed. Feedback is praise, a general observation, or a minor gripe with no urgent
  ask. When in doubt, look for whether the citizen expects a fix ("complaint") or is just sharing
  an opinion ("feedback").
- "proposal" is only for a citizen suggesting a NEW idea or initiative — not for reporting an
  existing problem, even if they phrase it as "sana magkaroon ng..." about something broken.
- Priority reflects real-world urgency and safety risk, not how angry the writer sounds. A
  furious complaint about a minor issue is still Low/Medium; a calmly-worded but genuinely
  dangerous hazard (exposed wiring, deep uncovered excavation, a health hazard) is High/Critical.
- Sentiment is independent of priority and type: a Critical safety complaint can be written
  calmly (mildly Negative) or furiously (strongly Negative); a Low-priority thank-you note is
  Positive. Judge sentiment purely from the writer's tone and word choice, not the situation's
  severity.

Examples:

Subject: "Sobrang bilis magmaneho dito sa may school, delikado sa mga bata"
Description: "Almost nasagasaan yung anak ko papuntang school. Walang speed bump o warning sign."
→ {"submissionType":"complaint","category":"Public Safety","priority":"Critical","summary":"Reckless driving near a school is endangering children; no speed bump or warning sign exists.","confidence":0.9,"reasoning":"Direct safety risk to children near a school, needs immediate traffic control.","sentiment":"Negative","sentiment_score":-0.7}

Subject: "Salamat sa mabilis na pag-ayos ng kalsada"
Description: "Grabe ang bilis ng team, isang linggo lang tapos na. Sana ganito palagi."
→ {"submissionType":"feedback","category":"Infrastructure & Roads","priority":"Low","summary":"Citizen thanks the department for quickly repairing a road within one week.","confidence":0.92,"reasoning":"Purely positive feedback about completed work, no action needed.","sentiment":"Positive","sentiment_score":0.85}

Subject: "Suggestion: covered walkway from terminal to market"
Description: "Sana magkaroon ng covered walkway galing sa jeepney terminal papunta sa palengke, para di basang-basa pag umuulan."
→ {"submissionType":"proposal","category":"Infrastructure & Roads","priority":"Medium","summary":"Citizen proposes building a covered walkway from the jeepney terminal to the market to protect commuters from rain.","confidence":0.85,"reasoning":"New infrastructure idea, not an existing hazard, so proposal not complaint.","sentiment":"Positive","sentiment_score":0.3}

Subject: "Streetlight not working for weeks"
Description: "Ilang linggo na patay yung streetlight sa may kanto. Medyo madilim lang sa gabi pero ok naman pa."
→ {"submissionType":"complaint","category":"Infrastructure & Roads","priority":"Medium","summary":"A streetlight has been out for weeks, making the corner dim at night but not immediately dangerous.","confidence":0.8,"reasoning":"Genuine unresolved issue but citizen notes it's manageable, so Medium not High.","sentiment":"Neutral","sentiment_score":-0.2}

Subject: "May tumutulo na kuryente sa poste malapit sa bahay namin"
Description: "Nakikita namin na may sparks paminsan-minsan sa poste, sobrang delikado lalo na pag umuulan."
→ {"submissionType":"complaint","category":"Public Safety","priority":"Critical","summary":"A utility pole near a residence is sparking intermittently, posing a serious electrocution risk especially in rain.","confidence":0.93,"reasoning":"Active electrical hazard with imminent danger to residents.","sentiment":"Negative","sentiment_score":-0.6}

Subject: "Good staff"
Description: "dawdad wadwad acszc xzczc"
→ {"submissionType":"feedback","category":"Other","priority":"Low","summary":"Unreadable text — the description appears to be random keyboard input.","confidence":0.3,"reasoning":"Description has no real words; likely spam or a test.","sentiment":"Neutral","sentiment_score":0,"readable":false}

Now classify this submission:

Subject: {$subject}
Description: {$description}
PROMPT;
    }

    /**
     * Deterministic offline fallback classifier using keyword matching.
     * Guarantees the app works fully without an API key.
     */
    private static function classifyWithKeywords(string $subject, string $description): array
    {
        $text = mb_strtolower($subject . ' ' . $description);

        $categoryKeywords = [
            'Infrastructure & Roads' => ['kalsada', 'road', 'pothole', 'tubig', 'water main', 'leak', 'flood', 'flooding', 'baha', 'binaha', 'drainage', 'imburnal', 'barado', 'bridge', 'construction', 'streetlight', 'ilaw'],
            'Public Safety'          => ['peligro', 'delikado', 'safety', 'abandoned vehicle', 'sirang sasakyan', 'crime', 'holdap', 'away', 'insidente', 'sunog', 'nasusunog', 'fire', 'aksidente', 'accident', 'kuryente'],
            'Sanitation & Waste'     => ['basura', 'garbage', 'waste', 'collection', 'trash', 'ihi', 'dumi', 'sanitation'],
            'Parks & Recreation'     => ['park', 'plaza', 'damo', 'vegetation', 'sidewalk', 'playground', 'palaruan', 'punong kahoy', 'tree'],
            'Transport & Transit'    => ['bus', 'jeep', 'jeepney', 'route', 'traysikel', 'tricycle', 'bike lane', 'ruta', 'byahe', 'terminal'],
            'Licensing & Permits'    => ['permit', 'lisensya', 'renewal', 'business permit', 'clearance', 'requirements'],
            'Noise & Disturbance'    => ['ingay', 'noise', 'kalog', 'maingay', 'videoke', 'karaoke', 'construction noise'],
            'Healthcare Services'    => ['ospital', 'hospital', 'health center', 'clinic', 'gamot', 'doktor', 'doctor', 'vaccine', 'bakuna'],
            'Education'              => ['eskwela', 'school', 'guro', 'teacher', 'estudyante', 'classroom'],
        ];

        $bestCategory = 'Other';
        $bestScore = 0;
        foreach ($categoryKeywords as $cat => $words) {
            $score = self::countWords($text, $words);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCategory = $cat;
            }
        }

        [$sentiment, $sentimentScore] = self::detectSentimentWithKeywords($text);

        // Type detection. Explicit suggestion words mean proposal; otherwise a
        // reported problem means complaint. "sana"/"dapat" alone are too common
        // in Filipino ("sana maayos agad", "salamat, sana ganito palagi") to
        // override a complaint or a thank-you, so they only decide ties.
        $strongProposal = self::countWords($text, ['proposal', 'propose', 'mungkahi', 'imungkahi', 'suggest', 'suggestion', 'idea', 'magdagdag', 'magkaroon']) > 0;
        $complaintSignal = self::countWords($text, ['reklamo', 'complaint', 'hindi', 'late', 'delay', 'delayed', 'sira', 'sirang', 'nasira', 'tumutulo', 'umaapaw', 'walang', 'leak', 'ingay', 'maingay', 'barado', 'baha', 'binaha', 'sunog', 'nasusunog', 'patay', 'natumba', 'delikado', 'peligro', 'dangerous', 'broken', 'not working', 'too long', 'no update', 'no updates']) > 0;
        $weakProposal = self::countWords($text, ['sana', 'dapat', 'paalala']) > 0;

        if ($strongProposal) {
            $type = 'proposal';
        } elseif ($complaintSignal) {
            $type = 'complaint';
        } elseif ($weakProposal && $sentiment !== 'Positive') {
            $type = 'proposal';
        } else {
            $type = 'feedback';
        }

        // Priority detection
        $priority = 'Medium';
        if (preg_match('/\b(delikado|emergency|peligro|critical|malaking|nasusunog|fire|sunog|nasugatan|tulong)\b/i', $text)) {
            $priority = 'Critical';
        } elseif (preg_match('/\b(agad|urgent|matagal na|sobrang tagal|malubha)\b/i', $text)) {
            $priority = 'High';
        } elseif (preg_match('/\b(minor|konti|slight|sa hinaharap|balang araw)\b/i', $text)) {
            $priority = 'Low';
        }

        $summary = mb_substr(trim(preg_replace('/\s+/', ' ', $description)), 0, 140);
        if (mb_strlen($description) > 140) $summary .= '…';

        return self::normalize([
            'submissionType'  => $type,
            'category'        => $bestCategory,
            'priority'        => $priority,
            'summary'         => $summary,
            'confidence'      => $bestScore > 0 ? min(0.75, 0.45 + ($bestScore * 0.1)) : 0.35,
            'reasoning'       => 'Offline keyword classifier matched ' . $bestScore . ' relevant term(s) for ' . $bestCategory . '.',
            'sentiment'       => $sentiment,
            'sentiment_score' => $sentimentScore,
        ]);
    }

    /**
     * Counts how many of $words appear in $text as whole words/phrases (an
     * optional plural "s" is allowed). Plain substring matching caused false
     * hits like "ayos" in "maayos", "bus" in "business", "park" in "parking".
     */
    private static function countWords(string $text, array $words): int
    {
        $count = 0;
        foreach ($words as $w) {
            if (preg_match('/(?<!\p{L})' . preg_quote($w, '/') . 's?(?!\p{L})/u', $text)) $count++;
        }
        return $count;
    }

    /**
     * @return array{0:string,1:float} [sentiment label, sentiment score -1..1]
     */
    private static function detectSentimentWithKeywords(string $text): array
    {
        $positiveWords = [
            'salamat', 'thank', 'thanks', 'grateful', 'appreciate', 'appreciated',
            'maganda', 'mabuti', 'mahusay', 'magaling', 'ang galing', 'great', 'excellent', 'satisfied',
            'masaya', 'happy', 'nice', 'good job', 'well done', 'ayos', 'napakabilis', 'mabilis',
        ];
        $negativeWords = [
            'galit', 'angry', 'frustrated', 'frustrating', 'disappointed', 'nakakainis',
            'nakakabwisit', 'terrible', 'horrible', 'worst', 'useless', 'walang kwenta',
            'bad', 'poor', 'reklamo', 'nakakagalit', 'sobrang tagal', 'grabe ang tagal',
            'lokohan', 'pabaya', 'kapabayaan', 'nakakadismaya', 'sayang',
        ];

        $posScore = self::countWords($text, $positiveWords);
        $negScore = self::countWords($text, $negativeWords);

        if ($posScore === 0 && $negScore === 0) {
            return ['Neutral', 0.0];
        }
        if ($posScore > $negScore) {
            return ['Positive', min(0.9, 0.3 + ($posScore - $negScore) * 0.15)];
        }
        if ($negScore > $posScore) {
            return ['Negative', max(-0.9, -0.3 - ($negScore - $posScore) * 0.15)];
        }
        return ['Neutral', 0.0];
    }

    private static function normalize(array $data): array
    {
        $type = in_array($data['submissionType'] ?? '', self::TYPES, true) ? $data['submissionType'] : 'feedback';
        $category = in_array($data['category'] ?? '', self::CATEGORIES, true) ? $data['category'] : 'Other';
        $priority = in_array($data['priority'] ?? '', self::PRIORITIES, true) ? $data['priority'] : 'Medium';
        $confidence = is_numeric($data['confidence'] ?? null) ? max(0, min(1, (float) $data['confidence'])) : 0.5;
        $sentiment = in_array($data['sentiment'] ?? '', self::SENTIMENTS, true) ? $data['sentiment'] : 'Neutral';
        $sentimentScore = is_numeric($data['sentiment_score'] ?? null) ? max(-1, min(1, (float) $data['sentiment_score'])) : 0.0;

        return [
            'submissionType'  => $type,
            'category'        => $category,
            'priority'        => $priority,
            'summary'         => mb_substr((string) ($data['summary'] ?? ''), 0, 480),
            'confidence'      => round($confidence, 3),
            'reasoning'       => mb_substr((string) ($data['reasoning'] ?? 'Automated classification.'), 0, 480),
            'sentiment'       => $sentiment,
            'sentiment_score' => round($sentimentScore, 3),
            // Missing/unclear → treat as readable: only an explicit false flags the item.
            'readable'        => !in_array($data['readable'] ?? true, [false, 'false', 0, '0'], true),
        ];
    }

    /**
     * Safe classification wrapper used at submission time: guarantees a
     * usable result (safe defaults) even if everything else fails.
     */
    public static function classifySafely(string $subject, string $description): array
    {
        try {
            return self::classify($subject, $description);
        } catch (Throwable $e) {
            logger()->warning('[CEPFMS] AI classification totally failed, using safe defaults: ' . $e->getMessage());
            return [
                'submissionType'  => 'feedback',
                'category'        => 'Other',
                'priority'        => 'Medium',
                'summary'         => mb_substr($description, 0, 140),
                'confidence'      => 0.0,
                'reasoning'       => 'Classification unavailable; safe defaults applied.',
                'sentiment'       => 'Neutral',
                'sentiment_score' => 0.0,
                'readable'        => true,
            ];
        }
    }
}
