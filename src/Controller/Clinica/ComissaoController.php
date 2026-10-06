<?php

namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Cadastro de % de comissão por serviço e produto.
 * O relatório de comissões usa esses percentuais como padrão nos itens de
 * venda, mas eles podem ser sobrescritos no detalhe (venda a venda).
 *
 * @Route("dashboard/clinica/comissoes")
 */
class ComissaoController extends DefaultController
{
    /**
     * @Route("/cadastro", name="clinica_comissoes_cadastro", methods={"GET"})
     */
    public function cadastro(): Response
    {
        if ($resp = $this->negarSeNaoFinanceiro()) {
            return $resp;
        }

        $this->switchDB();
        $baseId = $this->getIdBase();
        $conn = $this->entityManager->getConnection();

        $servicos = $conn->fetchAllAssociative(
            "SELECT id, nome, tipo, valor, comissao_percentual
             FROM homepet_{$baseId}.servico
             WHERE estabelecimento_id = :estab
             ORDER BY tipo, nome",
            ['estab' => $baseId]
        );

        $produtos = $conn->fetchAllAssociative(
            "SELECT id, nome, categoria, preco_venda, comissao_percentual
             FROM homepet_{$baseId}.produto
             WHERE estabelecimento_id = :estab
             ORDER BY categoria, nome",
            ['estab' => $baseId]
        );

        return $this->render('clinica/comissoes_cadastro.html.twig', [
            'servicos' => $servicos,
            'produtos' => $produtos,
        ]);
    }

    /**
     * @Route("/servico/{id}", name="clinica_comissao_servico_salvar", methods={"POST"})
     */
    public function salvarServico(int $id, Request $request): JsonResponse
    {
        return $this->persistirPercentual('servico', $id, $request);
    }

    /**
     * @Route("/produto/{id}", name="clinica_comissao_produto_salvar", methods={"POST"})
     */
    public function salvarProduto(int $id, Request $request): JsonResponse
    {
        return $this->persistirPercentual('produto', $id, $request);
    }

    private function persistirPercentual(string $tabela, int $id, Request $request): JsonResponse
    {
        if ($resp = $this->negarSeNaoFinanceiro()) {
            return $this->json(['status' => 'error', 'mensagem' => 'Sem permissão.'], 403);
        }

        $this->switchDB();
        $baseId = $this->getIdBase();

        $dados = json_decode($request->getContent(), true) ?? [];
        $pct = $dados['percentual'] ?? null;

        if ($pct === '' || $pct === null) {
            $pct = null;
        } else {
            $pct = (float) str_replace(',', '.', (string) $pct);
            if ($pct < 0 || $pct > 100) {
                return $this->json(['status' => 'error', 'mensagem' => 'Percentual inválido (0 a 100).'], 400);
            }
        }

        $this->entityManager->getConnection()->executeStatement(
            "UPDATE homepet_{$baseId}.{$tabela}
             SET comissao_percentual = :pct
             WHERE id = :id AND estabelecimento_id = :estab",
            ['pct' => $pct, 'id' => $id, 'estab' => $baseId]
        );

        return $this->json(['status' => 'success', 'percentual' => $pct]);
    }
}
