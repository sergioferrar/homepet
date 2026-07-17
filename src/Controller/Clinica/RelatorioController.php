<?php

namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use App\Entity\Consulta;
use App\Entity\Venda;
use App\Entity\Veterinario;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/clinica")
 */
class RelatorioController extends DefaultController
{
    /**
     * Relatório de comissionamento dos veterinários.
     *
     * Lista as fichas CONCLUÍDAS (status "atendido") do período agrupadas por
     * veterinário, além das vendas feitas na clínica (dentro das fichas dos
     * pets) no mesmo período — cada venda é atribuída ao veterinário do
     * atendimento do pet no dia da venda.
     *
     * O valor de cada atendimento vem do valor editado no próprio relatório
     * (coluna consulta.valor) e, na ausência dele, do serviço da clínica de
     * mesmo nome do tipo do atendimento. Os valores podem ser ajustados na
     * tela e são gravados no banco. O percentual de comissão é informado na
     * tela e o repasse é calculado dinamicamente (JS), com opção de impressão.
     *
     * @Route("/relatorios/comissoes", name="clinica_relatorio_comissoes", methods={"GET"})
     */
    public function comissoes(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // --- Filtros (padrão: mês atual) ---
        $hoje = new \DateTime();
        $inicio = $request->query->get('inicio')
            ? new \DateTime($request->query->get('inicio'))
            : (clone $hoje)->modify('first day of this month');
        $fim = $request->query->get('fim')
            ? new \DateTime($request->query->get('fim'))
            : clone $hoje;

        $vetFiltro = $request->query->get('veterinario_id');
        $vetFiltro = ($vetFiltro !== null && $vetFiltro !== '') ? (int) $vetFiltro : null;

        // --- Dados ---
        $consultaRepo = $this->getRepositorio(Consulta::class);
        $consultas = $consultaRepo->listarConsultasComissao($baseId, $inicio, $fim, $vetFiltro);
        $consultasSemVet = $consultaRepo->contarConsultasSemVeterinario($baseId, $inicio, $fim);

        $vendas = $this->getRepositorio(Venda::class)
            ->listarVendasClinicaComissao($baseId, $inicio, $fim);

        $veterinarios = $this->getRepositorio(Veterinario::class)
            ->findByEstabelecimento($baseId);

        // Mapa id => dados do veterinário (para vendas de vets sem consulta no período)
        $vetsPorId = [];
        foreach ($veterinarios as $v) {
            $vetsPorId[(int) $v['id']] = $v;
        }

        // --- Agrupamento por veterinário: fichas concluídas ---
        $relatorio = [];
        foreach ($consultas as $c) {
            $vetId = (int) $c['veterinario_id'];
            if (!isset($relatorio[$vetId])) {
                $relatorio[$vetId] = [
                    'veterinario_id' => $vetId,
                    'nome' => $c['veterinario_nome'],
                    'crmv' => $c['veterinario_crmv'],
                    'quantidade' => 0,
                    'total' => 0.0,
                    'total_vendas' => 0.0,
                    'consultas' => [],
                    'vendas' => [],
                ];
            }

            // Valor editado no relatório tem prioridade; senão, valor do serviço.
            $valorEditado = $c['valor_editado'] !== null ? (float) $c['valor_editado'] : null;
            $valorServico = $c['valor_servico'] !== null ? (float) $c['valor_servico'] : null;
            $valor = $valorEditado ?? $valorServico ?? 0.0;

            $relatorio[$vetId]['quantidade']++;
            $relatorio[$vetId]['total'] += $valor;
            $relatorio[$vetId]['consultas'][] = [
                'id' => $c['id'],
                'data' => new \DateTime($c['data'] . ' ' . $c['hora']),
                'tipo' => $c['tipo'] ?? 'Consulta',
                'pet' => $c['pet_nome'],
                'cliente' => $c['cliente_nome'],
                'valor' => $valor,
                'valor_cadastrado' => $valorEditado !== null || $valorServico !== null,
                'valor_editado' => $valorEditado !== null,
            ];
        }

        // --- Vendas da clínica (dentro das fichas) ---
        $vendasSemVet = [];
        foreach ($vendas as $venda) {
            $vetId = $venda['veterinario_id'] !== null ? (int) $venda['veterinario_id'] : null;

            // Respeita o filtro de veterinário também nas vendas
            if ($vetFiltro !== null && $vetId !== $vetFiltro) {
                continue;
            }

            $item = [
                'id' => $venda['id'],
                'data' => new \DateTime($venda['data']),
                'pet' => $venda['pet_nome'] ?? '—',
                'cliente' => $venda['cliente_nome'] ?? '—',
                'valor' => (float) $venda['total'],
                'metodo_pagamento' => $venda['metodo_pagamento'],
                'status' => $venda['status'],
            ];

            if ($vetId === null) {
                $vendasSemVet[] = $item;
                continue;
            }

            // Veterinário só com vendas no período (sem fichas concluídas)
            if (!isset($relatorio[$vetId])) {
                $vet = $vetsPorId[$vetId] ?? null;
                $relatorio[$vetId] = [
                    'veterinario_id' => $vetId,
                    'nome' => $vet['nome'] ?? 'Veterinário #' . $vetId,
                    'crmv' => $vet['crmv'] ?? null,
                    'quantidade' => 0,
                    'total' => 0.0,
                    'total_vendas' => 0.0,
                    'consultas' => [],
                    'vendas' => [],
                ];
            }

            $relatorio[$vetId]['total_vendas'] += $item['valor'];
            $relatorio[$vetId]['vendas'][] = $item;
        }

        // Ordena por nome do veterinário (vendas podem ter criado grupos novos)
        $relatorio = array_values($relatorio);
        usort($relatorio, fn ($a, $b) => strcasecmp($a['nome'], $b['nome']));

        return $this->render('clinica/relatorio_comissoes.html.twig', [
            'relatorio' => $relatorio,
            'veterinarios' => $veterinarios,
            'inicio' => $inicio,
            'fim' => $fim,
            'vet_filtro' => $vetFiltro,
            'consultas_sem_vet' => $consultasSemVet,
            'vendas_sem_vet' => $vendasSemVet,
        ]);
    }

    /**
     * Grava o valor editado de um atendimento no relatório de comissões.
     *
     * Recebe JSON { "valor": 123.45 }. Valor vazio/null limpa o ajuste manual
     * (o relatório volta a usar o valor do serviço de mesmo nome).
     *
     * @Route("/relatorios/comissoes/consulta/{id}/valor", name="clinica_relatorio_comissoes_valor", methods={"POST"})
     */
    public function salvarValorComissao(Request $request, int $id): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $dados = json_decode($request->getContent(), true) ?? [];
        $valor = $dados['valor'] ?? $request->get('valor');

        if ($valor === '' || $valor === null) {
            $valor = null;
        } else {
            $valor = (float) str_replace(',', '.', (string) $valor);
            if ($valor < 0) {
                return $this->json([
                    'status' => 'error',
                    'mensagem' => 'O valor não pode ser negativo.',
                ], 400);
            }
        }

        try {
            $this->getRepositorio(Consulta::class)
                ->atualizarValorComissao($baseId, $id, $valor);

            return $this->json([
                'status' => 'success',
                'valor' => $valor,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'mensagem' => 'Erro ao salvar o valor: ' . $e->getMessage(),
            ], 500);
        }
    }
}
