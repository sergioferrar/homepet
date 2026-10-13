<?php

namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use App\Entity\Cliente;
use App\Entity\ClienteCreditoMovimento;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/clinica/credito")
 */
class CreditoController extends DefaultController
{
    /**
     * @Route("/cliente/{clienteId}/saldo", name="clinica_credito_saldo", methods={"GET"})
     */
    public function saldo(int $clienteId): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $saldo = $this->getRepositorio(ClienteCreditoMovimento::class)
            ->saldoDoCliente($baseId, $clienteId);

        return $this->json(['saldo' => round($saldo, 2)]);
    }

    /**
     * @Route("/cliente/{clienteId}/historico", name="clinica_credito_historico", methods={"GET"})
     */
    public function historico(int $clienteId): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $hist = $this->getRepositorio(ClienteCreditoMovimento::class)
            ->historicoDoCliente($baseId, $clienteId);

        return $this->json(['historico' => $hist]);
    }

    /**
     * Adiciona crédito avulso (ex: pagamento adiantado ou acerto).
     *
     * @Route("/cliente/{clienteId}/adicionar", name="clinica_credito_adicionar", methods={"POST"})
     */
    public function adicionar(int $clienteId, Request $request): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $cliente = $this->getRepositorio(Cliente::class)->localizaTodosClientePorID($baseId, $clienteId);
        if (!$cliente) {
            return $this->json(['status' => 'error', 'mensagem' => 'Cliente não encontrado.'], 404);
        }

        $dados = json_decode($request->getContent(), true) ?? $request->request->all();
        $valor = (float) str_replace(',', '.', (string) ($dados['valor'] ?? 0));

        if ($valor <= 0) {
            return $this->json(['status' => 'error', 'mensagem' => 'Valor inválido.'], 400);
        }

        $this->getRepositorio(ClienteCreditoMovimento::class)->inserir(
            $baseId,
            $clienteId,
            isset($dados['pet_id']) && $dados['pet_id'] !== '' ? (int) $dados['pet_id'] : null,
            'entrada',
            $valor,
            $dados['descricao'] ?? 'Crédito avulso',
            'manual',
            null,
            $this->getUserId()
        );

        $novoSaldo = $this->getRepositorio(ClienteCreditoMovimento::class)
            ->saldoDoCliente($baseId, $clienteId);

        return $this->json(['status' => 'success', 'saldo' => round($novoSaldo, 2)]);
    }

    private function getUserId(): ?int
    {
        $u = $this->getUser();
        return $u && method_exists($u, 'getId') ? (int) $u->getId() : null;
    }
}
