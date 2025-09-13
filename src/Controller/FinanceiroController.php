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
 * @Route("/financeiro")
 */
class FinanceiroController extends DefaultController
{
    /**
     * @Route("/", name="financeiro_index")
     */
    public function index(Request $request, FinanceiroRepository $financeiroRepo, FinanceiroPendenteRepository $financeiroPendenteRepo): Response {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        // --- Aba Diário ---
        $dataDiario = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();
        $financeirosDiarios = $financeiroRepo->findTotalByDate($baseId, $dataDiario);
        
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

        return $this->render('financeiro/index.html.twig', [
            'financeiros' => $financeirosDiarios,
            'data'        => $dataDiario,
            'pendentes'   => $financeirosPendentes,
            'mes_inicio'  => $mesInicio,
            'mes_fim'     => $mesFim,
            'relatorio'   => $relatorioData,
            'inativos'    => $financeirosInativos,
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
            $financeiro->setValor((float) $request->request->get('valor'));
            $financeiro->setData(new \DateTime($request->request->get('data')));
            $financeiro->setPetId($request->request->get('pet_id') !== '' ? (int) $request->request->get('pet_id') : null);

            $financeiroRepo->update($baseId, $financeiro);
            return $this->redirectToRoute('financeiro_index');
        }

        return $this->render('financeiro/editar.html.twig', [
            'financeiro' => $financeiro,
            'pets'       => $petRepo->findAllPets($baseId)
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
}