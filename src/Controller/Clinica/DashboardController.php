<?php
namespace App\Controller\Clinica;

use App\Controller\DefaultController;
use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\DocumentoModelo;
use App\Entity\Financeiro;
use App\Entity\FinanceiroPendente;
use App\Entity\Internacao;
use App\Entity\InternacaoExecucao;
use App\Entity\Pet;
use App\Entity\Servico;
use App\Entity\Vacina;
use App\Repository\FinanceiroRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/clinica")
 */
class DashboardController extends DefaultController
{
    /**
     * @Route("/administrativo", name="clinica_dashboard", methods={"GET"})
     */
    public function dashboard(Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repoPet        = $this->getRepositorio(Pet::class);
        $repoCliente    = $this->getRepositorio(Cliente::class);
        $repoFinanceiro = $this->getRepositorio(FinanceiroPendente::class);
        $repoConsulta   = $this->getRepositorio(Consulta::class);
        $repoInternacao = $this->getRepositorio(Internacao::class);

        $internacoes    = $repoInternacao->listarInternacoesAtivas($baseId);
        $totalPets      = $repoPet->countTotalPets($baseId);
        $totalDono      = $repoCliente->countTotalDono($baseId);
        $debitosCliente = $repoFinanceiro->somarDebitosPendentes($baseId);
        $media          = $repoConsulta->calcularMediaConsultas($baseId);
        $atendimentos   = $repoConsulta->listarUltimosAtendimentos($baseId);

        $animaisCadastrados = method_exists($repoPet, 'listarPetsRecentes') ? $repoPet->listarPetsRecentes($baseId, 5) : [];
        $vacinasVencidas    = method_exists($repoPet, 'listarVacinasPendentes') ? $repoPet->listarVacinasPendentes($baseId) : [];
        $vacinasProgramadas = method_exists($repoPet, 'listarVacinasProgramadas') ? $repoPet->listarVacinasProgramadas($baseId) : [];

        // 🔍 pesquisa
        $termo = $request->query->get('q');
        $pets  = [];

        if ($termo) {
            $pets = $repoPet->pesquisarPetsOuTutor($baseId, $termo);
        }

        // 🔹 BUSCA TODAS AS PRESCRIÇÕES DE TODAS AS INTERNAÇÕES ATIVAS (OTIMIZADO)
        $prescricoesGerais          = [];
        $calendarioPrescricoesGeral = [];

        // Coleta todos os IDs de internações ativas
        $internacaoIds = array_filter(array_column($internacoes, 'id'));

        if (! empty($internacaoIds)) {
            // Busca TODOS os eventos de prescrição de uma vez
            $eventos = $em->getRepository(\App\Entity\InternacaoEvento::class)
                ->createQueryBuilder('e')
                ->where('e.internacaoId IN (:internacaoIds)')
                ->andWhere('e.tipo = :tipo')
                ->setParameter('internacaoIds', $internacaoIds)
                ->setParameter('tipo', 'prescricao')
                ->getQuery()
                ->getResult();

            // Busca TODAS as execuções de uma vez
            $eventoIds = array_map(fn($e) => $e->getId(), $eventos);
            $execucoes = [];
            if (! empty($eventoIds)) {
                $execucoesResult = $em->getRepository(\App\Entity\InternacaoExecucao::class)
                    ->createQueryBuilder('ex')
                    ->where('ex.prescricaoId IN (:eventoIds)')
                    ->setParameter('eventoIds', $eventoIds)
                    ->getQuery()
                    ->getResult();

                // Indexa por prescricaoId para acesso rápido
                foreach ($execucoesResult as $exec) {
                    $execucoes[$exec->getPrescricaoId()] = $exec;
                }
            }

            // Indexa internações por ID para acesso rápido
            $internacoesMap = [];
            foreach ($internacoes as $int) {
                $internacoesMap[$int['id']] = $int;
            }

            // Processa eventos
            foreach ($eventos as $evento) {
                $internacaoId = $evento->getInternacaoId();
                $internacao   = $internacoesMap[$internacaoId] ?? null;

                if (! $internacao) {
                    continue;
                }

                $execucao = $execucoes[$evento->getId()] ?? null;

                $cor    = '#667eea'; // roxo
                $status = 'pendente';
                if ($execucao && $execucao->getStatus() == 'confirmado') {
                    $cor    = '#10b981'; // verde
                    $status = 'confirmado';
                }

                $calendarioPrescricoesGeral[] = [
                    'title'         => ($internacao['pet_nome'] ?? 'Pet') . ' - ' . $evento->getTitulo(),
                    'start'         => $evento->getDataHora()->format(\DateTime::ATOM),
                    'end'           => (clone $evento->getDataHora())->modify('+30 minutes')->format(\DateTime::ATOM),
                    'color'         => $cor,
                    'evento_id'     => $evento->getId(),
                    'internacao_id' => $internacaoId,
                    'pet_nome'      => $internacao['pet_nome'] ?? 'Pet',
                    'dono_nome'     => $internacao['dono_nome'] ?? '',
                    'status'        => $status,
                    'descricao'     => $evento->getDescricao(),
                ];
            }
        }

        return $this->render('clinica/dashboard.html.twig', [
            'total_pets'                   => $totalPets,
            'debitos_cliente'              => $debitosCliente,
            'media_atendimento'            => $media,
            'atendimentos'                 => $atendimentos,
            'internados'                   => $internacoes,
            'vacinas_programadas'          => $vacinasProgramadas,
            'vacinas_vencidas'             => $vacinasVencidas,
            'totaldono'                    => $totalDono,
            'animais_cadastrados'          => $animaisCadastrados,
            'pets'                         => $pets,
            'termo'                        => $termo,
            'calendario_prescricoes_geral' => $calendarioPrescricoesGeral,
        ]);
    }

    /**
     * @Route("/pet/{id}", name="clinica_detalhes_pet", methods={"GET"})
     */
    public function detalhesPet(Request $request, int $id): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // --- Repositórios ---
        $consultaRepo   = $this->getRepositorio(Consulta::class);
        $documentoRepo  = $this->getRepositorio(DocumentoModelo::class);
        $internacaoRepo = $this->getRepositorio(Internacao::class);
        $vacinaRepo     = $this->getRepositorio(Vacina::class);
        $vendasRepo     = $this->getRepositorio(\App\Entity\Venda::class);
        $vendaItemRepo  = $this->getRepositorio(\App\Entity\VendaItem::class);
        $servicoRepo    = $this->getRepositorio(\App\Entity\Servico::class);

        // --- Pet (agora retorna array) ---
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $id);
        if (! $pet) {
            throw $this->createNotFoundException('O pet não foi encontrado.');
        }

        // --- Dados básicos ---
        $consultas  = $consultaRepo->findAllByPetId($baseId, $pet['id']);
        $documentos = $documentoRepo->listarDocumentos($baseId);
        $receitas   = $this->getRepositorio(\App\Entity\Receita::class)->listarPorPet($baseId, $pet['id']);
        $vacinas    = $vacinaRepo->listarPorPet($baseId, $pet['id']);

        $internacaoAtivaId  = $internacaoRepo->findAtivaIdByPet($baseId, $pet['id']);
        $ultimaInternacaoId = $internacaoRepo->findUltimaIdByPet($baseId, $pet['id']);
        $internacoesPet     = $internacaoRepo->listarInternacoesPorPet($baseId, $pet['id']);

        // --- Serviços da clínica (já retorna array) ---
        $servicosClinica = $servicoRepo->findAllService($baseId, 'clinica');

        // --- VENDAS (agora com método SQL otimizado) ---
        $vendasPagas     = $vendasRepo->findByPet($baseId, $pet['id']);
        $vendasPendentes = $vendasRepo->findBy([
            'estabelecimentoId' => $baseId,
            'petId'             => $pet['id'],
            'status'            => 'Pendente',
        ]);
        $vendasCarrinho = $vendasRepo->findBy([
            'estabelecimentoId' => $baseId,
            'petId'             => $pet['id'],
            'status'            => 'Aberta',
        ]);

        // --- TIMELINE ---
        $timeline_items = [];

        // Consultas
        foreach ($consultas as $item) {
            $anamnese = json_decode($item['anamnese'], true)['ops'] ?? [];
            $resumo   = '';

            foreach ($anamnese as $row) {
                if (! empty($row['insert'])) {
                    $texto = $row['insert'];
                    if (! empty($row['attributes']['bold'])) {
                        $texto = "<b>{$texto}</b>";
                    }
                    $resumo .= "{$texto} ";
                }
            }

            $timeline_items[] = [
                'data'        => new \DateTime($item['data'] . ' ' . $item['hora']),
                'tipo'        => $item['tipo'] ?? 'Consulta',
                'observacoes' => $item['observacoes'],
                'resumo'      => trim($resumo),
                'anamnese'    => $item['anamnese'] ?? null,
            ];
        }

        // Receitas
        foreach ($receitas as $r) {
            $timeline_items[] = [
                'data'              => new \DateTime($r['data']),
                'tipo'              => 'Receita',
                'resumo'            => $r['resumo'] ?? '',
                'observacoes'       => $r['resumo'] ?? '',
                'receita_cabecalho' => $r['cabecalho'],
                'receita_conteudo'  => $r['conteudo'],
                'receita_rodape'    => $r['rodape'],
            ];
        }

        // Vacinas
        foreach ($vacinas as $v) {
            $timeline_items[] = [
                'data'   => isset($v['data_aplicacao']) ? new \DateTime($v['data_aplicacao']) : new \DateTime(),
                'tipo'   => 'Vacina',
                'resumo' => sprintf(
                    '%s — Lote: %s | Validade: %s',
                    strtoupper($v['tipo'] ?? 'VACINA'),
                    $v['lote'] ?? '—',
                    isset($v['data_validade'])
                        ? (new \DateTime($v['data_validade']))->format('d/m/Y')
                        : '—'
                ),
                'cor'    => '#FFD700',
            ];
        }

        // --- VENDAS PAGAS (COM ITENS) ---
        $resumoVentaItem = [];
        foreach ($vendasPagas as $venda) {

            $itensVenda  = [];
            $resumoItens = [];

            // Se venda é objeto entity, adaptar
            if (is_object($venda) && method_exists($venda, 'getId')) {
                $vendaId = $venda->getId();
                $vendaTotal = $venda->getTotal();
                $vendaData = $venda->getData();
            } else {
                // Se é array do novo repository
                $vendaId = $venda['id'];
                $vendaTotal = $venda['total'];
                $vendaData = new \DateTime($venda['data']);
            }

            $itens = $vendaItemRepo->findBy(['vendaId' => $vendaId]);

            foreach ($itens as $item) {
                // Todos os itens agora são sempre objetos VendaItem
                $produtoId     = $item->getProdutoId();
                $quantidade    = $item->getQuantidade();
                $valorUnitario = $item->getValorUnitario();
                $snapshotNome = $item->getProduto(); // nome gravado no momento da venda

                // Dados legados: campo produto continha o ID numérico em vez do nome.
                // Detecta e busca o nome real no cadastro.
                if ($snapshotNome !== null && is_numeric(trim($snapshotNome))) {
                    $idBusca = $produtoId ?? (int)trim($snapshotNome);
                    if ($item->getTipo() === 'produto') {
                        $entidade = $em->getRepository(\App\Entity\Produto::class)->find($idBusca);
                    } else {
                        $entidade = $servicoRepo->find($idBusca);
                    }
                    $descricao = $entidade ? $entidade->getNome() : "Item #{$idBusca}";
                } elseif ($produtoId && $item->getTipo() === 'servico') {
                    // Sempre tenta enriquecer serviços com nome atualizado do cadastro
                    $servico = $servicoRepo->find($produtoId);
                    $descricao = ($servico && method_exists($servico, 'getNome'))
                        ? $servico->getNome()
                        : ($snapshotNome ?: 'Serviço');
                } else {
                    $descricao = $snapshotNome ?: 'Item sem descrição';
                }

                $itensVenda[] = [
                    'descricao'      => $descricao,
                    'quantidade'     => $quantidade,
                    'valor_unitario' => $valorUnitario,
                    'subtotal'       => $quantidade * $valorUnitario,
                ];

                $resumoVentaItem[$vendaId][] = [
                    'item'       => $descricao,
                    'valor'      => $valorUnitario,
                    'quantidade' => $quantidade,
                    'subtotal'   => $quantidade * $valorUnitario,
                ];

                $resumoItens[] = $descricao;
            }

            $timeline_items[] = [
                'data'        => $vendaData,
                'tipo'        => 'Venda',
                'resumo'      => implode(' + ', $resumoItens),
                'observacoes' => 'Venda concluída',
                'valor'       => $vendaTotal,
                'status'      => 'paga',
                'venda_itens' => $itensVenda,
                'venda_id'    => $vendaId,
            ];
        }

        // --- VENDAS PENDENTES ---
        foreach ($vendasPendentes as $venda) {
            // Adaptar para objetos entity
            if (is_object($venda)) {
                $timeline_items[] = [
                    'data'        => $venda->getData(),
                    'tipo'        => 'Débito',
                    'resumo'      => 'Venda pendente',
                    'observacoes' => 'Pagamento pendente',
                    'valor'       => $venda->getTotal(),
                    'status'      => 'pendente',
                    'cor'         => '#dc3545',
                ];
            }
        }

        // --- AGRUPA TIMELINE ---
        $timelineAgrupado = [];
        foreach ($timeline_items as $item) {
            $timelineAgrupado[$item['tipo']][] = $item;
        }

        // --- TOTAL DE DÉBITOS ---
        $totalDebitos = 0;
        foreach ($vendasPendentes as $venda) {
            $totalDebitos += is_object($venda) ? $venda->getTotal() : ($venda['total'] ?? 0);
        }

        // --- DATA PARA VIEW ---

        $data                         = [];
        $data['pet']                  = $pet;
        $data['timeline_items']       = $timeline_items;
        $data['timeline_agrupado']    = $timelineAgrupado;
        $data['documentos']           = $documentos;
        $data['consultas']            = $consultas;
        $data['total_debitos']        = $totalDebitos;
        $data['servicos_clinica']     = $servicosClinica;
        $data['internacao_ativa_id']  = $internacaoAtivaId;
        $data['ultima_internacao_id'] = $ultimaInternacaoId;
        $data['internacoes_pet']      = $internacoesPet;
        $data['vacinas']              = $vacinas;
        $data['vendas_pagas']         = $vendasPagas;
        $data['vendas_pendentes']     = $vendasPendentes;
        $data['vendas_carrinho']      = $vendasCarrinho;
        $data['vendas_items']         = $resumoVentaItem;
        
        return $this->render('clinica/detalhes_pet.html.twig', $data);
    }

    /**
     * @Route("/financeiro", name="financeiro_dashboard", methods={"GET"})
     */
    public function financeirodash(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repoFinanceiro = new FinanceiroRepository($this->managerRegistry);

        $hoje          = new \DateTime();
        $inicioMes     = (clone $hoje)->modify('first day of this month')->setTime(0, 0);
        $semanaPassada = (clone $hoje)->modify('-7 days');

        // Métodos retornam arrays agora
        $financeiroHoje   = $repoFinanceiro->findByDate($baseId, $hoje);
        $financeiroSemana = $repoFinanceiro->getRelatorioPorPeriodo($baseId, $semanaPassada, $hoje);
        $financeiroMes    = $repoFinanceiro->getRelatorioPorPeriodo($baseId, $inicioMes, $hoje);

        $inicioMes    = (clone $hoje)->modify('first day of this month');
        $totalReceita = $repoFinanceiro->somarPorDescricao($baseId, 'Receita', $inicioMes, $hoje);
        $totalDespesa = $repoFinanceiro->somarPorDescricao($baseId, 'Pagamento', $inicioMes, $hoje);

        $totalGeral = $totalReceita - $totalDespesa;
        $dataAtual  = $request->query->get('data')
            ? new \DateTime($request->query->get('data'))
            : new \DateTime();

        return $this->render('clinica/financeirodash.html.twig', [
            'financeiro_hoje'   => $financeiroHoje,
            'financeiro_semana' => $financeiroSemana,
            'financeiro_mes'    => $financeiroMes,
            'total_receita'     => $totalReceita,
            'total_despesa'     => $totalDespesa,
            'saldo_geral'       => $totalGeral,
            'dataAtual'         => $dataAtual,
        ]);
    }

    /**
     * @Route("/buscar", name="clinica_buscar", methods={"GET"})
     */
    public function buscar(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();

        $baseId     = $this->getIdBase();
        $resultados = [];
        $query      = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return new JsonResponse(['resultados' => []]);
        }

        try {

            // Buscar pets
            $pets = $em->getRepository(\App\Entity\Pet::class)
                ->createQueryBuilder('p')
                ->where('LOWER(p.nome) LIKE :query')
                ->andWhere('p.estabelecimentoId = :estab')
                ->setParameter('query', '%' . strtolower($query) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            foreach ($pets as $pet) {
                $resultados[] = [
                    'nome' => $pet->getNome(),
                    'tipo' => 'Pet' . ($pet->getEspecie() ? ' - ' . $pet->getEspecie() : ''),
                    'url'  => $this->generateUrl('clinica_detalhes_pet', ['id' => $pet->getId()]),
                    'icon' => 'bi-heart-fill',
                ];
            }

            // Buscar clientes
            $clientes = $em->getRepository(\App\Entity\Cliente::class)
                ->createQueryBuilder('c')
                ->where('LOWER(c.nome) LIKE :query')
                ->andWhere('c.estabelecimentoId = :estab')
                ->setParameter('query', '%' . strtolower($query) . '%')
                ->setParameter('estab', $baseId)
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            foreach ($clientes as $cliente) {
                $resultados[] = [
                    'nome' => $cliente->getNome(),
                    'tipo' => 'Cliente' . ($cliente->getTelefone() ? ' - ' . $cliente->getTelefone() : ''),
                    'url'  => '/homepet/public/clinica/cliente/' . $cliente->getId(),
                    'icon' => 'bi-person-fill',
                ];
            }

            return new JsonResponse(['resultados' => $resultados]);

        } catch (\Exception $e) {
            return new JsonResponse(['resultados' => [], 'error' => $e->getMessage()]);
        }
    }
}