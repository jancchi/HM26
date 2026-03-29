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

    $contextJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($contextJson === false) {
        $contextJson = '{}';
    }

    $prompt = "Analyze the following community support request data and classify it.\n"
        . "Return ONLY valid JSON with exactly these keys: category, urgency, summary, recommended_member_profile.\n"
        . "Rules:\n"
        . "- category: string\n"
        . "- urgency: integer from 1 to 5\n"
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
        if ($urgencyInt >= 1 && $urgencyInt <= 5) {
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
