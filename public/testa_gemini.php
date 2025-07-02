<?php

$apiKey = 'AIzaSyA67q9TOwM0rBJxMClUswHRrsH8e-mk0qA'; // coloca aqui a API que gerou no Google Cloud
$pergunta = 'Qual a dose de dipirona para um cão de 10kg?';

$dados = [
    'prompt' => [
        'messages' => [
            [
                'author' => 'user',
                'content' => $pergunta
            ]
        ]
    ],
    'temperature' => 0.7,
    'candidateCount' => 1
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://generativelanguage.googleapis.com/v1beta1/models/chat-bison-001:generateMessage?key=' . $apiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$resposta = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'Erro CURL: ' . curl_error($ch);
} else {
    echo "Resposta:\n" . $resposta;
}

curl_close($ch);
