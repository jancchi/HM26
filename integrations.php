<?php
function analyzeRequestWithGemini(array $input): array
{
    $nullResult = [
        'category' => null,
        'urgency' => null,
        'summary' => null,
        'recommended_member_profile' => null,
        'raw_json' => null,
    ];

    if (!defined('GEMINI_API_KEY') || trim((string) GEMINI_API_KEY) === '') {
        return $nullResult;
    }

    if (!defined('GEMINI_MODEL') || trim((string) GEMINI_MODEL) === '') {
        return $nullResult;
    }

    if (!function_exists('curl_init')) {
        return $nullResult;
    }

    $contextJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($contextJson === false) {
        $contextJson = '{}';
    }

    $prompt = "Analyze the following community support request data and classify it.\n"
        . "Return ONLY valid JSON with exactly these keys: category, urgency, summary, recommended_member_profile.\n"
        . "Rules:\n"
        . "- category: string\n"
        . "- urgency: integer from 1 to 10\n"
        . "- summary: string, max 140 characters\n"
        . "- recommended_member_profile: string\n"
        . "Do not include markdown, code fences, explanations, or extra keys.\n"
        . "Input JSON:\n"
        . $contextJson;

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode((string) GEMINI_MODEL)
        . ':generateContent?key='
        . rawurlencode((string) GEMINI_API_KEY);

    $payload = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt,
                    ],
                ],
            ],
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
        ],
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return $nullResult;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return $nullResult;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_TIMEOUT => 15,
    ]);

    $responseBody = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErrNo !== 0 || !is_string($responseBody) || $responseBody === '') {
        return $nullResult;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return $nullResult;
    }

    $outer = json_decode($responseBody, true);
    if (!is_array($outer)) {
        return $nullResult;
    }

    $rawText = null;
    if (isset($outer['candidates'][0]['content']['parts'][0]['text']) && is_string($outer['candidates'][0]['content']['parts'][0]['text'])) {
        $rawText = trim($outer['candidates'][0]['content']['parts'][0]['text']);
    }

    if ($rawText === null || $rawText === '') {
        return $nullResult;
    }

    $parsed = json_decode($rawText, true);
    if (!is_array($parsed)) {
        return $nullResult;
    }

    $requiredKeys = ['category', 'urgency', 'summary', 'recommended_member_profile'];
    foreach ($requiredKeys as $key) {
        if (!array_key_exists($key, $parsed)) {
            return $nullResult;
        }
    }

    $category = is_string($parsed['category']) ? trim($parsed['category']) : null;
    if ($category === '') {
        $category = null;
    }

    $urgency = null;
    if (is_int($parsed['urgency']) || (is_string($parsed['urgency']) && preg_match('/^-?\d+$/', $parsed['urgency']) === 1)) {
        $urgencyInt = (int) $parsed['urgency'];
        if ($urgencyInt >= 1 && $urgencyInt <= 10) {
            $urgency = $urgencyInt;
        }
    }

    $summary = is_string($parsed['summary']) ? trim($parsed['summary']) : null;
    if ($summary === '') {
        $summary = null;
    } elseif (mb_strlen($summary, 'UTF-8') > 140) {
        $summary = mb_substr($summary, 0, 140, 'UTF-8');
    }

    $recommendedMemberProfile = is_string($parsed['recommended_member_profile'])
        ? trim($parsed['recommended_member_profile'])
        : null;
    if ($recommendedMemberProfile === '') {
        $recommendedMemberProfile = null;
    }

    return [
        'category' => $category,
        'urgency' => $urgency,
        'summary' => $summary,
        'recommended_member_profile' => $recommendedMemberProfile,
        'raw_json' => $rawText,
    ];
}

function matchRequestToOffers(array $request, array $offers): array
{
    if (empty($offers)) return [];
    if (!defined('GEMINI_API_KEY') || trim((string) GEMINI_API_KEY) === '') return [];
    if (!defined('GEMINI_MODEL') || trim((string) GEMINI_MODEL) === '') return [];
    if (!function_exists('curl_init')) return [];

    $offersSafe = array_map(fn($o) => [
        'offer_id' => (int) ($o['id'] ?? 0),
        'full_name' => (string) ($o['full_name'] ?? ''),
        'role' => (string) ($o['role'] ?? ''),
        'skills_description' => (string) ($o['skills_description'] ?? ''),
        'category' => (string) ($o['category'] ?? ''),
        'city' => (string) ($o['city'] ?? ''),
    ], $offers);

    $prompt = "You are a matchmaking engine for a startup/investor community.\n"
        . "Given a REQUEST and COMMUNITY OFFERS, return the top 3 best-matching offers.\n"
        . "Return ONLY a valid JSON array. Each element: offer_id (int), score (int 1-10), reason (string max 100 chars).\n"
        . "No markdown, no extra keys. If no match, return [].\n\n"
        . "REQUEST:\n" . json_encode($request, JSON_UNESCAPED_UNICODE) . "\n\n"
        . "COMMUNITY OFFERS:\n" . json_encode($offersSafe, JSON_UNESCAPED_UNICODE);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode((string) GEMINI_MODEL) . ':generateContent?key=' . rawurlencode((string) GEMINI_API_KEY);

    $payload = [
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => ['responseMimeType' => 'application/json'],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || !is_string($body) || $httpCode < 200 || $httpCode >= 300) return [];

    $outer = json_decode($body, true);
    $text = $outer['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$text) return [];

    $parsed = json_decode(trim($text), true);
    if (!is_array($parsed)) return [];

    $validIds = array_column($offersSafe, 'offer_id');
    $result = [];
    foreach ($parsed as $item) {
        if (!isset($item['offer_id'], $item['score'], $item['reason'])) continue;
        if (!in_array((int) $item['offer_id'], $validIds, true)) continue;
        $result[] = [
            'offer_id' => (int) $item['offer_id'],
            'score' => max(1, min(10, (int) $item['score'])),
            'reason' => mb_substr(trim((string) $item['reason']), 0, 100, 'UTF-8'),
        ];
    }
    return array_slice($result, 0, 3);
}

function generateWarmIntroEmail(array $request, array $offer): string
{
    $requestFullName = trim((string) ($request['full_name'] ?? 'Requester'));
    $offerFullName = trim((string) ($offer['full_name'] ?? 'Community Member'));
    $requestTitle = trim((string) ($request['title'] ?? 'Support request'));
    $requestDescription = trim((string) ($request['description'] ?? ''));
    $offerSkills = trim((string) ($offer['skills_description'] ?? 'relevant community experience'));

    $extractFirstName = static function (string $fullName): string {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return 'there';
        }
        $parts = preg_split('/\s+/', $fullName);
        if (!is_array($parts) || empty($parts[0])) {
            return 'there';
        }
        return $parts[0];
    };

    $requestFirstName = $extractFirstName($requestFullName);
    $offerFirstName = $extractFirstName($offerFullName);

    $requestDescriptionShort = $requestDescription !== ''
        ? mb_substr($requestDescription, 0, 180, 'UTF-8')
        : 'their current request details';
    $offerSkillsShort = $offerSkills !== ''
        ? mb_substr($offerSkills, 0, 140, 'UTF-8')
        : 'relevant community support experience';

    $fallback = "Hi {$requestFirstName} and {$offerFirstName},\n\n"
        . "I would like to connect you through the 0100 Ecosystem. {$requestFirstName} is currently looking for support with \"{$requestTitle}\" and shared context around {$requestDescriptionShort}. "
        . "{$offerFirstName} has relevant experience in {$offerSkillsShort}, which seems strongly aligned with this request.\n\n"
        . "If you are both open to it, please continue directly and coordinate next steps.\n\n"
        . "Best regards,\n"
        . "0100 Ecosystem Team";

    if (!defined('GEMINI_API_KEY') || trim((string) GEMINI_API_KEY) === '') {
        return $fallback;
    }
    if (!defined('GEMINI_MODEL') || trim((string) GEMINI_MODEL) === '') {
        return $fallback;
    }
    if (!function_exists('curl_init')) {
        return $fallback;
    }

    $requestContext = [
        'full_name' => $requestFullName,
        'title' => $requestTitle,
        'description' => $requestDescription,
        'category' => trim((string) ($request['category'] ?? '')),
        'city' => trim((string) ($request['city'] ?? '')),
    ];
    $offerContext = [
        'full_name' => $offerFullName,
        'skills_description' => $offerSkills,
        'category' => trim((string) ($offer['category'] ?? '')),
        'city' => trim((string) ($offer['city'] ?? '')),
    ];

    $requestJson = json_encode($requestContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $offerJson = json_encode($offerContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($requestJson === false || $offerJson === false) {
        return $fallback;
    }

    $prompt = "You are writing a warm professional introduction email for a startup/investor community.\n\n"
        . "Task:\n"
        . "Write ONE plain-text email body (no subject line) introducing two people to each other.\n\n"
        . "Requirements:\n"
        . "- Max 160 words.\n"
        . "- No markdown.\n"
        . "- No bullet points.\n"
        . "- Tone: warm, professional, concise.\n"
        . "- Address both people by first name.\n"
        . "- Mention the requester's need using the request title and short context from description.\n"
        . "- Mention the offer person's relevant skill/background from skills_description.\n"
        . "- End with a short call to connect and sign exactly:\n"
        . "0100 Ecosystem Team\n\n"
        . "Return ONLY the email body text.\n\n"
        . "REQUEST:\n"
        . $requestJson
        . "\n\nOFFER:\n"
        . $offerJson;

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
        . rawurlencode((string) GEMINI_MODEL)
        . ':generateContent?key='
        . rawurlencode((string) GEMINI_API_KEY);

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
    ];

    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payloadJson === false) {
        return $fallback;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return $fallback;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_TIMEOUT => 15,
    ]);

    $responseBody = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErrNo !== 0 || !is_string($responseBody) || $responseBody === '' || $httpCode < 200 || $httpCode >= 300) {
        return $fallback;
    }

    $outer = json_decode($responseBody, true);
    if (!is_array($outer)) {
        return $fallback;
    }

    $text = $outer['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!is_string($text)) {
        return $fallback;
    }

    $text = trim($text);
    if ($text === '') {
        return $fallback;
    }

    if (mb_strlen($text, 'UTF-8') > 1200) {
        $text = mb_substr($text, 0, 1200, 'UTF-8');
    }

    return $text;
}
