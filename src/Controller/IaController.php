<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\PetRepository;
use App\Repository\AgendamentoRepository;
use App\Service\IaGeminiService;

// <- AQUI muda

class IaController extends AbstractController
{
    private $petRepository;
    private $agendamentoRepository;
    private $iaGeminiService;

    public function __construct(
        PetRepository         $petRepository,
        AgendamentoRepository $agendamentoRepository,
        IaGeminiService       $iaGeminiService // <- AQUI muda
    )
    {
        $this->petRepository = $petRepository;
        $this->agendamentoRepository = $agendamentoRepository;
        $this->iaGeminiService = $iaGeminiService;
    }

    /**
     * @Route("/ia/perguntar", name="ia_perguntar", methods={"POST"})
     */
    public function perguntar(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $pergunta = $data['pergunta'] ?? '';

        $session = $request->getSession();
        $baseId = $this->getIdBase();
        $usuario = $session->get('user');

        $pet = $this->petRepository->buscarUltimoPorUsuario($baseId, $usuario);
        $agendamento = $this->agendamentoRepository->buscarUltimoPorPet($baseId, $pet['id'] ?? 0);

        $contexto = "Você é uma IA veterinária de apoio clínico. Pet: {$pet['nome']} ({$pet['especie']}), {$pet['idade']} anos, raça {$pet['raca']}. Histórico: {$pet['observacoes']}. Último serviço: " . ($agendamento['servico'] ?? 'N/A');

        try {
            $resposta = $this->iaGeminiService->conversar($pergunta, $contexto);

            file_put_contents(__DIR__ . '/../../var/log/ia_' . date('Ymd') . '.log',
                "[" . date('Y-m-d H:i:s') . "] $usuario\nPERGUNTA: $pergunta\nRESPOSTA: $resposta\n\n",
                FILE_APPEND
            );

            return new JsonResponse(['resposta' => $resposta]);
        } catch (\Throwable $e) {
            return new JsonResponse(['resposta' => 'Erro: ' . $e->getMessage()], 500);
        }
    }
}
