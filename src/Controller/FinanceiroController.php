<?php

namespace App\Controller;

use App\Entity\Financeiro;
use App\Entity\Pet;
use App\Entity\FinanceiroPendente;
use App\Repository\FinanceiroRepository;
use App\Repository\FinanceiroPendenteRepository;
use App\Repository\PetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * @Route("dashboard/financeiro")
 */
class FinanceiroController extends DefaultController
{
    /**
     * @Route("/", name="financeiro_index")
     */
    public function index(Request $request, FinanceiroRepository $financeiroRepo, FinanceiroPendenteRepository $financeiroPendenteRepo): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('estabelecimentoId');     

        // --- Aba Diário ---
        $dataDiario = $request->query->get('data') ? date('Y-m-d', strtotime($request->query->get('data'))) : date('Y-m-d');
        $financeirosDiarios = $financeiroRepo->findTotalByDate($baseId, $dataDiario);
        // $financeirosDiarios = $this->getRepositorio(\App\Entity\Venda::class)->findBy(['origem' => 'clinica']);
        // dd($financeirosDiarios, $dataDiario);
        // 
        // --- Aba Pendente ---
        $financeirosPendentes = $financeiroPendenteRepo->findAllPendentes($baseId);

        // --- Aba Relatório ---
        $mesInicio = $request->query->get('mes_inicio', (new \DateTime('first day of this month'))->format('Y-m'));
        $mesFim = $request->query->get('mes_fim', (new \DateTime('last day of this month'))->format('Y-m'));
        $dataInicio = new \DateTime($mesInicio . '-01');
        $dataFim = (new \DateTime($mesFim . '-01'))->modify('last day of this month');
        $relatorioData = $financeiroRepo->getRelatorioPorPeriodo($baseId, $dataInicio, $dataFim);

        // --- Aba Inativos ---
        $financeirosInativos = $financeiroRepo->findInativos($baseId);

        // --- Aba Fluxo de Caixa ---
        $dataFluxo = $request->query->get('data_fluxo') ? new \DateTime($request->query->get('data_fluxo')) : new \DateTime();
        $fluxoCaixa = $this->getFluxoCaixa($baseId, $dataFluxo);

        return $this->render('financeiro/index.html.twig', [
            'financeiros' => $financeirosDiarios,
            'data' => $dataDiario,
            'pendentes' => $financeirosPendentes,
            'mes_inicio' => $mesInicio,
            'mes_fim' => $mesFim,
            'relatorio' => $relatorioData,
            'inativos' => $financeirosInativos,
            'fluxo_caixa' => $fluxoCaixa,
            'data_fluxo' => $dataFluxo,
        ]);
    }

    /**
     * @Route("/novo", name="financeiro_novo")
     */
    public function novo(Request $request, FinanceiroRepository $financeiroRepo, PetRepository $petRepo): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        if ($request->isMethod('POST')) {
            $financeiro = new Financeiro();
            $financeiro->setDescricao($request->request->get('descricao'));
            $financeiro->setValor((float)$request->request->get('valor'));
            $financeiro->setData(new \DateTime($request->request->get('data')));
            $financeiro->setPetId($request->request->get('pet_id') !== '' ? (int)$request->request->get('pet_id') : null);
            $financeiro->setEstabelecimentoId($baseId);

            $financeiroRepo->save($baseId, $financeiro);
            return $this->redirectToRoute('financeiro_index');
        }

        return $this->render('financeiro/novo.html.twig', [
            'pets' => $petRepo->findAllPets($baseId)
        ]);
    }

    /**
     * @Route("/editar/{id}", name="financeiro_editar")
     */
    public function editar(Request $request, int $id, FinanceiroRepository $financeiroRepo, PetRepository $petRepo): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');
        $financeiro = $financeiroRepo->findFinanceiro($baseId, $id);

        if (!$financeiro) {
            throw $this->createNotFoundException('O registro financeiro não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $financeiro->setDescricao($request->request->get('descricao'));
            $financeiro->setValor((float)$request->request->get('valor'));
            $financeiro->setData(new \DateTime($request->request->get('data')));
            $financeiro->setPetId($request->request->get('pet_id') !== '' ? (int)$request->request->get('pet_id') : null);

            $financeiroRepo->update($baseId, $financeiro);
            return $this->redirectToRoute('financeiro_index');
        }

        return $this->render('financeiro/editar.html.twig', [
            'financeiro' => $financeiro,
            'pets' => $petRepo->findAllPets($baseId)
        ]);
    }

    /**
     * @Route("/deletar/{id}", name="financeiro_deletar")
     */
    public function deletar(int $id, FinanceiroRepository $financeiroRepo): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $financeiro = $financeiroRepo->findFinanceiro($baseId, $id);

        if (!$financeiro) {
            throw $this->createNotFoundException('O registro financeiro não foi encontrado');
        }

        $financeiroRepo->delete($baseId, $id);
        return $this->redirectToRoute('financeiro_index');
    }

    /**
     * @Route("/pendente/confirmar/{id}", name="financeiro_confirmar_pagamento", methods={"POST"})
     */
    public function confirmarPagamento(int $id, FinanceiroPendenteRepository $financeiroPendenteRepository, FinanceiroRepository $financeiroRepo): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $financeiroPendente = $financeiroPendenteRepository->findPendenteById($baseId, $id);

        if (!$financeiroPendente) {
            throw $this->createNotFoundException('Registro financeiro pendente não encontrado.');
        }

        $financeiro = new Financeiro();
        $financeiro->setEstabelecimentoId($baseId);
        $financeiro->setDescricao($financeiroPendente['descricao']);
        $financeiro->setValor($financeiroPendente['valor']);
        $financeiro->setData(new \DateTime());
        $financeiro->setPetId($financeiroPendente['pet_id']);

        $financeiroRepo->save($baseId, $financeiro);

        $financeiroPendenteRepository->deletePendente($baseId, $id);

        $this->addFlash('success', 'Pagamento confirmado e movido para o Financeiro Diário.');

        return $this->redirectToRoute('financeiro_index', ['aba' => 'pendente']);
    }

    /**
     * @Route("/pendente/deletar/{id}", name="financeiro_deletar_pendente", methods={"POST"})
     */
    public function deletarPendente(int $id, FinanceiroPendenteRepository $financeiroPendenteRepository): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $financeiroPendente = $financeiroPendenteRepository->findPendenteById($baseId, $id);

        if (!$financeiroPendente) {
            throw $this->createNotFoundException('Registro financeiro pendente não encontrado.');
        }

        $financeiroPendenteRepository->deletePendente($baseId, $id);

        $this->addFlash('success', 'Registro pendente excluído com sucesso.');

        return $this->redirectToRoute('financeiro_index', ['aba' => 'pendente']);
    }

    /**
     * @Route("/relatorio/export", name="financeiro_relatorio_export")
     */
    public function exportRelatorioExcel(Request $request, FinanceiroRepository $financeiroRepo): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $mesInicio = $request->query->get('mes_inicio');
        $mesFim = $request->query->get('mes_fim');

        $dataInicio = $mesInicio ? new \DateTime($mesInicio . '-01') : new \DateTime('first day of this month');
        $dataFim = $mesFim ? new \DateTime($mesFim . '-01') : new \DateTime('last day of this month');
        $dataFim->modify('last day of this month');

        $relatorio = $financeiroRepo->getRelatorioPorPeriodo($baseId, $dataInicio, $dataFim);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Data');
        $sheet->setCellValue('B1', 'Total');

        $row = 2;
        foreach ($relatorio as $item) {
            $sheet->setCellValue('A' . $row, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime($item['data'])));
            $sheet->getStyle('A' . $row)->getNumberFormat()->setFormatCode('DD/MM/YYYY');
            $sheet->setCellValue('B' . $row, $item['total']);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'relatorio_financeiro.xlsx';
        $temp_file = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($temp_file);

        return $this->file($temp_file, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    /**
     * @Route("/fluxo/debug", name="financeiro_fluxo_debug")
     */
    public function debugFluxo(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');
        $data = new \DateTime();

        $fluxo = $this->getFluxoCaixa($baseId, $data);

        return new JsonResponse([
            'total_entradas' => $fluxo['total_entradas'],
            'total_saidas' => $fluxo['total_saidas'],
            'saldo' => $fluxo['saldo'],
            'quantidade_movimentos' => count($fluxo['movimentos']),
            'movimentos' => $fluxo['movimentos']
        ]);
    }

    /**
     * Método auxiliar para buscar o fluxo de caixa consolidado
     */
    private function getFluxoCaixa(int $baseId, \DateTime $data): array
    {
        $inicioDia = (clone $data)->setTime(0, 0, 0);
        $fimDia = (clone $data)->setTime(23, 59, 59);

        $em = $this->getDoctrine()->getManager();

        // 🔹 1. Busca ENTRADAS do Financeiro (tipo ENTRADA ou NULL para compatibilidade)
        $entradasFinanceiro = $em->getRepository(Financeiro::class)
            ->createQueryBuilder('f')
            ->where('f.estabelecimentoId = :estab')
            ->andWhere('f.data BETWEEN :inicio AND :fim')
            ->andWhere('f.inativar = :inativo')
            ->andWhere('(f.tipo = :tipo OR f.tipo IS NULL OR f.tipo = :vazio)')
            ->setParameter('estab', $baseId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->setParameter('tipo', 'ENTRADA')
            ->setParameter('vazio', '')
            ->setParameter('inativo', false)
            ->orderBy('f.data', 'ASC')
            ->getQuery()
            ->getResult();

        // 🔹 2. Busca SAÍDAS do Financeiro (tipo SAIDA)
        $saidasFinanceiro = $em->getRepository(Financeiro::class)
            ->createQueryBuilder('f')
            ->where('f.estabelecimentoId = :estab')
            ->andWhere('f.data BETWEEN :inicio AND :fim')
            ->andWhere('f.tipo = :tipo')
            ->setParameter('estab', $baseId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->setParameter('tipo', 'SAIDA')
            ->orderBy('f.data', 'ASC')
            ->getQuery()
            ->getResult();

        // 🔹 3. Busca movimentos do Caixa (PDV) - APENAS SAÍDAS
        // As vendas (entradas) já estão no Financeiro, então buscamos apenas saídas manuais
        $movimentosCaixa = $em->getRepository(\App\Entity\CaixaMovimento::class)
            ->createQueryBuilder('c')
            ->where('c.estabelecimentoId = :estab')
            ->andWhere('c.data BETWEEN :inicio AND :fim')
            ->andWhere('c.tipo = :tipo')
            ->setParameter('estab', $baseId)
            ->setParameter('inicio', $inicioDia)
            ->setParameter('fim', $fimDia)
            ->setParameter('tipo', 'SAIDA')
            ->orderBy('c.data', 'ASC')
            ->getQuery()
            ->getResult();

        // 🔹 4. Consolida tudo em um array único
        $movimentos = [];
        $totalEntradas = 0;
        $totalSaidas = 0;

        // Adiciona entradas do financeiro
        foreach ($entradasFinanceiro as $f) {
            $valor = floatval($f->getValor());
            if ($valor > 0) {
                $totalEntradas += $valor;
                $movimentos[] = [
                    'data' => $f->getData(),
                    'descricao' => $f->getDescricao(),
                    'origem' => $f->getOrigem() ?? 'Financeiro',
                    'metodo' => $f->getMetodoPagamento() ?? '-',
                    'tipo' => 'ENTRADA',
                    'valor' => $valor
                ];
            }
        }

        // Adiciona saídas do financeiro
        foreach ($saidasFinanceiro as $f) {
            $valor = floatval($f->getValor());
            if ($valor > 0) {
                $totalSaidas += $valor;
                $movimentos[] = [
                    'data' => $f->getData(),
                    'descricao' => $f->getDescricao(),
                    'origem' => $f->getOrigem() ?? 'Financeiro',
                    'metodo' => $f->getMetodoPagamento() ?? '-',
                    'tipo' => 'SAIDA',
                    'valor' => $valor
                ];
            }
        }

        // Adiciona movimentos do caixa (PDV) - Apenas saídas manuais
        foreach ($movimentosCaixa as $c) {
            $valor = floatval($c->getValor());
            if ($valor > 0) {
                $totalSaidas += $valor;
                $movimentos[] = [
                    'data' => $c->getData(),
                    'descricao' => $c->getDescricao(),
                    'origem' => 'PDV - Saída Manual',
                    'metodo' => 'Caixa',
                    'tipo' => 'SAIDA',
                    'valor' => $valor
                ];
            }
        }

        // 🔹 5. Ordena por data
        usort($movimentos, function ($a, $b) {
            return $a['data'] <=> $b['data'];
        });

        return [
            'movimentos' => $movimentos,
            'total_entradas' => $totalEntradas,
            'total_saidas' => $totalSaidas,
            'saldo' => $totalEntradas - $totalSaidas
        ];
    }
}