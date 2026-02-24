<?php

namespace App\Service;

use App\Entity\Produto;
use App\Entity\EstoqueMovimento;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Serviço centralizado para gerenciamento de estoque
 * Garante rastreabilidade e isolamento por estabelecimento
 */
class EstoqueService
{
    private EntityManagerInterface $em;
    private TenantContext $tenantContext;

    public function __construct(
        EntityManagerInterface $em,
        TenantContext $tenantContext
    ) {
        $this->em = $em;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Valida disponibilidade de estoque para múltiplos produtos
     * 
     * @param array $itens Array com estrutura: [['produto_id' => int, 'quantidade' => int, 'nome' => string], ...]
     * @return array Array de produtos validados indexados por ID
     * @throws \RuntimeException Se estoque insuficiente
     */
    public function validarEstoque(array $itens): array
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();
        $produtosValidados = [];

        foreach ($itens as $item) {
            // Apenas produtos físicos consomem estoque — serviços são ignorados
            if (strtolower($item['tipo']) !== 'produto') {
                continue;
            }

            // Resolve ID: prioriza produto_id explícito, depois extrai do prefixo 'prod_X'
            $produtoId = isset($item['produto_id']) && $item['produto_id']
                ? (int)$item['produto_id']
                : $this->extrairProdutoId($item['id'] ?? '');

            // ID inválido (dados legados ou item avulso) — pula validação de estoque
            // sem travar a venda. O item ainda é registrado, apenas sem baixa de estoque.
            if (!$produtoId) {
                continue;
            }

            $produto = $this->em->getRepository(Produto::class)->findOneBy([
                'id'                => $produtoId,
                'estabelecimentoId' => $estabelecimentoId,
            ]);

            // Produto não encontrado no cadastro — pula sem estourar a venda
            if (!$produto) {
                continue;
            }

            $estoqueAtual = $produto->getEstoqueAtual() ?? 0;

            if ($estoqueAtual < $item['quantidade']) {
                throw new \RuntimeException(
                    "Estoque insuficiente para '{$produto->getNome()}'. " .
                    "Disponível: {$estoqueAtual} | Solicitado: {$item['quantidade']}"
                );
            }

            $produtosValidados[$produtoId] = $produto;
        }

        return $produtosValidados;
    }

    /**
     * Baixa estoque de múltiplos produtos com rastreamento
     * 
     * @param array $itens Itens da venda
     * @param array $produtosValidados Produtos já validados
     * @param int $vendaId ID da venda
     * @param string $clienteNome Nome do cliente
     */
    public function baixarEstoque(
        array $itens,
        array $produtosValidados,
        int $vendaId,
        string $clienteNome
    ): void {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        foreach ($itens as $item) {
            if (strtolower($item['tipo']) !== 'produto') {
                continue;
            }

            $produtoId = $this->extrairProdutoId($item['id'] ?? '');
            if (!$produtoId) {
                continue; // ID inválido — pula sem travar
            }

            if (!isset($produtosValidados[$produtoId])) {
                continue;
            }

            $produto = $produtosValidados[$produtoId];
            $estoqueAnterior = $produto->getEstoqueAtual() ?? 0;
            $novoEstoque = max(0, $estoqueAnterior - $item['quantidade']);
            
            $produto->setEstoqueAtual($novoEstoque);

            // Registra movimento de estoque com rastreamento completo
            $movimento = new EstoqueMovimento();
            $movimento->setProduto($produto);
            $movimento->setEstabelecimentoId($estabelecimentoId);
            $movimento->setTipo('SAIDA');
            $movimento->setOrigem("Venda PDV #{$vendaId}");
            $movimento->setQuantidade($item['quantidade']);
            $movimento->setData(new \DateTime());
            $movimento->setObservacao(
                "Venda para: {$clienteNome} | " .
                "Estoque anterior: {$estoqueAnterior} | " .
                "Novo estoque: {$novoEstoque}"
            );

            $this->em->persist($produto);
            $this->em->persist($movimento);
        }
    }

    /**
     * Extrai ID numérico do produto removendo prefixo
     */
    private function extrairProdutoId(string $id): int
    {
        return (int)str_replace('prod_', '', $id);
    }

    /**
     * Retorna disponibilidade de estoque de um produto específico
     */
    public function getEstoqueDisponivel(int $produtoId): ?int
    {
        $estabelecimentoId = $this->tenantContext->getEstabelecimentoId();

        $produto = $this->em->getRepository(Produto::class)->findOneBy([
            'id' => $produtoId,
            'estabelecimentoId' => $estabelecimentoId
        ]);

        return $produto ? ($produto->getEstoqueAtual() ?? 0) : null;
    }
}