<?php

namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use App\Entity\Consulta;
use App\Entity\Veterinario;
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
     * Lista as consultas do período agrupadas por veterinário, com o valor de
     * cada consulta pré-carregado a partir do serviço da clínica de mesmo nome
     * do tipo do atendimento. O percentual de comissão é informado na tela e o
     * repasse é calculado dinamicamente (JS), com opção de impressão.
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

        $veterinarios = $this->getRepositorio(Veterinario::class)
            ->findByEstabelecimento($baseId);

        // --- Agrupamento por veterinário ---
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
                    'consultas' => [],
                ];
            }

            $valor = $c['valor_servico'] !== null ? (float) $c['valor_servico'] : 0.0;

            $relatorio[$vetId]['quantidade']++;
            $relatorio[$vetId]['total'] += $valor;
            $relatorio[$vetId]['consultas'][] = [
                'id' => $c['id'],
                'data' => new \DateTime($c['data'] . ' ' . $c['hora']),
                'tipo' => $c['tipo'] ?? 'Consulta',
                'pet' => $c['pet_nome'],
                'cliente' => $c['cliente_nome'],
                'valor' => $valor,
                'valor_cadastrado' => $c['valor_servico'] !== null,
            ];
        }

        return $this->render('clinica/relatorio_comissoes.html.twig', [
            'relatorio' => array_values($relatorio),
            'veterinarios' => $veterinarios,
            'inicio' => $inicio,
            'fim' => $fim,
            'vet_filtro' => $vetFiltro,
            'consultas_sem_vet' => $consultasSemVet,
        ]);
    }
}
