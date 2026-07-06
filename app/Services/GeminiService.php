<?php

namespace App\Services;

class GeminiService
{
    private const SYSTEM_PROMPT = <<<'EOT'
Eres un asistente experto en registrar partes de horas en Odoo.
El usuario te dictará una nota de audio describiendo el trabajo que realizó.
Debes extraer la información y responder ÚNICAMENTE con un JSON válido con este esquema:

{
  "entries": [
    {
      "date": "YYYY-MM-DD",
      "concept": "Descripción clara y concisa del trabajo realizado",
      "hours": 2.5
    }
  ]
}

Reglas:
- Si el usuario menciona una fecha específica, úsala. Si no, usa la fecha actual.
- Si menciona varias tareas o días, crea una entrada por cada una.
- Si no menciona horas específicas, asume 1 hora por entrada.
- Responde SOLO con el JSON, sin texto adicional ni markdown.
EOT;

    public static function transcribeTimesheetAudio(
        string $audioBase64,
        string $mimeType,
        string $apiKey
    ): array {
        if (empty($apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY not configured');
        }

        $model = env('GEMINI_MODEL', 'gemini-2.5-flash');
        $today = date('Y-m-d');

        $body = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => "Hoy es {$today}. " . self::SYSTEM_PROMPT],
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data' => $audioBase64,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 1024,
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("Gemini API error ({$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Extract JSON from response
        preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $jsonMatch);
        if (!$jsonMatch) {
            preg_match('/{[\s\S]*}/', $text, $jsonMatch);
        }
        $jsonStr = $jsonMatch ? trim($jsonMatch[1] ?? $jsonMatch[0]) : $text;

        $parsed = json_decode($jsonStr, true);
        if (!$parsed) {
            throw new \RuntimeException('Failed to parse Gemini response as JSON');
        }

        $entries = $parsed['entries'] ?? [$parsed];
        $result = [];
        foreach ($entries as $e) {
            $date = $e['date'] ?? $today;
            // Ensure date format
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $result[] = [
                    'date' => $date,
                    'concept' => $e['concept'] ?? '',
                    'hours' => (float) ($e['hours'] ?? 1),
                ];
            }
        }

        return $result;
    }
}
