<?php
namespace App\Service;

class IaGeminiService
{
    public function __construct(private readonly string $apiKey)
    {
    }

    public function conversar(string $pergunta, string $contexto = ''): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=" . $this->apiKey;

        $mensagens = [
            ['role' => 'user', 'parts' => [['text' => $contexto . "\n\n" . $pergunta]]]
        ];

        $body = json_encode(['contents' => $mensagens]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body
        ]);

        $resposta = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resposta, true);

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Não consegui responder.';
    }
}
