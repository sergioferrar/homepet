<?php
namespace App\Service;

use Orhanerday\OpenAi\OpenAi;

class OpenAiService
{
    private $openAi;

    public function __construct(string $apiKey)
    {
        $this->openAi = new OpenAi($apiKey);
    }

    public function conversar(string $pergunta, string $contexto = ''): string
    {
        $resposta = $this->openAi->chat([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => $contexto ?: 'Você é uma IA veterinária de apoio clínico.'],
                ['role' => 'user', 'content' => $pergunta],
            ],
            'temperature' => 0.7,
            'max_tokens' => 500,
        ]);

        $dados = json_decode($resposta, true);
        return $dados['choices'][0]['message']['content'] ?? 'Não consegui entender sua pergunta.';
    }
}
