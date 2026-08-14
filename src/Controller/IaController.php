<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use App\Entity\Pet;
use App\Entity\Agendamento;
use App\Service\CaixaService;
use App\Service\DatabaseBkp;
use App\Service\IaGeminiService;
use App\Service\PdvService;
use App\Service\TempDirManager;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

class IaController extends DefaultController
{
    private $iaGeminiService;

    public function __construct(
        ?Security $security,
        ManagerRegistry $managerRegistry,
        RequestStack $request,
        TempDirManager $tempDirManager,
        DatabaseBkp $databaseBkp,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        PdvService $pdvService,
        CaixaService $caixaService,
        EntityManagerInterface $em,
        TenantContext $tenantContext,
        IaGeminiService $iaGeminiService
    )
    {
        parent::__construct(
            $security,
            $managerRegistry,
            $request,
            $tempDirManager,
            $databaseBkp,
            $entityManager,
            $logger,
            $pdvService,
            $caixaService,
            $em,
            $tenantContext
        );
        $this->iaGeminiService = $iaGeminiService;
    }

    /**
     * @Route("/ia/perguntar", name="ia_perguntar", methods={"POST"})
     */
    public function perguntar(Request $request)
    {
        $petRepository = $this->getRepositorio(Pet::class);
        $agendamentoRepository = $this->getRepositorio(Agendamento::class);

        $data = json_decode($request->getContent(), true);
        $pergunta = $data['pergunta'] ?? '';

        $session = $request->getSession();
        $baseId = $this->getIdBase();
        $usuario = $session->get('user');

        $pet = $petRepository->buscarUltimoPorUsuario($baseId, $usuario);
        $agendamento = $agendamentoRepository->buscarUltimoPorPet($baseId, $pet['id'] ?? 0);

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
