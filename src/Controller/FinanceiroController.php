<?php

namespace App\Controller;

use App\Entity\Financeiro;
use App\Entity\Pet;
use App\Repository\FinanceiroRepository;
use App\Repository\FinanceiroPendenteRepository;
use App\Repository\PetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * @Route("/financeiro")
 */
class FinanceiroController extends DefaultController
{

    /**
     * @Route("/", name="financeiro_index", methods={"GET"})
     */
    public function index(Request $request): Response
    {
        $this->switchDB();
        $data = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();
        $financeiros = $this->getRepositorio(Financeiro::class)->findByDate($this->session->get('userId'), $data);

        return $this->render('financeiro/index.html.twig', [
            'financeiros' => $financeiros,
            'data' => $data,
        ]);
    }

    /**
     * @Route("/novo", name="financeiro_novo", methods={"GET", "POST"})
     */
    public function novo(Request $request): Response
    {
        $this->switchDB();
        if ($request->isMethod('POST')) {
            $financeiro = new Financeiro();
            $financeiro->setDescricao($request->request->get('descricao'));
            $financeiro->setValor((float)$request->request->get('valor'));
            $financeiro->setData(new \DateTime($request->request->get('data')));
            $financeiro->setPetId($request->request->get('pet_id') !== '' ? (int)$request->request->get('pet_id') : null);

            $this->getRepositorio(Financeiro::class)->save($this->session->get('userId'), $financeiro);
            return $this->redirectToRoute('financeiro_index');
        }

        return $this->render('financeiro/novo.html.twig', [
            'pets' => $this->getRepositorio(Pet::class)->findAllPets($this->session->get('userId'))
        ]);
    }

    /**
     * @Route("/editar/{id}", name="financeiro_editar", methods={"GET", "POST"})
     */
    public function editar(Request $request, int $id): Response
    {
        $this->switchDB();
        $repo = $this->getRepositorio(Financeiro::class);
        $financeiro = $repo->findFinanceiro($this->session->get('userId'), $id);

        if (!$financeiro) {
            throw $this->createNotFoundException('O registro financeiro não foi encontrado');
        }

        if ($request->isMethod('POST')) {
            $financeiro->setDescricao($request->request->get('descricao'));
            $financeiro->setValor((float) $request->request->get('valor'));
            $financeiro->setData(new \DateTime($request->request->get('data')));
            $financeiro->setPetId($request->request->get('pet_id') !== '' ? (int) $request->request->get('pet_id') : null);

            $repo->update($this->session->get('userId'), $financeiro);
            return $this->redirectToRoute('financeiro_index');
        }

        return $this->render('financeiro/editar.html.twig', [
            'financeiro' => $financeiro,
            'pets' => $this->getRepositorio(Pet::class)->findAllPets($this->session->get('userId'))
        ]);
    }




    /**
     * @Route("/deletar/{id}", name="financeiro_deletar", methods={"POST"})
     */
    public function deletar(Request $request, int $id): Response
    {
        $this->switchDB();
        $financeiro = $this->getRepositorio(Financeiro::class)->findAllFinanceiro($this->session->get('userId'), $id); // Corrigido

        if (!$financeiro) {
            throw $this->createNotFoundException('O registro financeiro não foi encontrado');
        }

        $this->getRepositorio(Financeiro::class)->delete($this->session->get('userId'), $id);
        return $this->redirectToRoute('financeiro_index');
    }


    /**
     * @Route("/relatorio", name="financeiro_relatorio", methods={"GET"})
     */
    public function relatorio(Request $request): Response
    {
        $this->switchDB();
        // Obtém os parâmetros do formulário
        $mesInicio = $request->query->get('mes_inicio');
        $mesFim = $request->query->get('mes_fim');

        // Define os períodos padrão para o mês atual, se nenhum for fornecido
        $dataInicio = $mesInicio ? new \DateTime($mesInicio . '-01') : new \DateTime('first day of this month');
        $dataFim = $mesFim ? new \DateTime($mesFim . '-01') : new \DateTime('last day of this month');

        // Ajusta a data final para o último dia do mês
        $dataFim->modify('last day of this month');

        // Busca os dados no repositório filtrados pelo período
        $relatorio = $this->getRepositorio(Financeiro::class)->getRelatorioPorPeriodo($this->session->get('userId'), $dataInicio, $dataFim);

        return $this->render('financeiro/relatorio.html.twig', [
            'relatorio' => $relatorio,
            'mes_inicio' => $dataInicio->format('Y-m'),
            'mes_fim' => $dataFim->format('Y-m')
        ]);
    }

    /**
     * @Route("/pendente", name="financeiro_pendente", methods={"GET"})
     */
    public function financeiroPendente(Request $request, FinanceiroPendenteRepository $financeiroPendenteRepository): Response
    {
        $this->switchDB();
        $data = $request->query->get('data') ? new \DateTime($request->query->get('data')) : new \DateTime();
        $financeirosPendentes = $financeiroPendenteRepository->findByDate($this->session->get('userId'), $data);

        return $this->render('financeiro/pendente.html.twig', [
            'financeiros' => $financeirosPendentes,
            'data' => $data,
        ]);
    }

    /**
     * @Route("/pendente/pagar/{id}", name="financeiro_pagar", methods={"POST"})
     */
    public function pagarFinanceiroPendente(int $id): Response
    {
        $this->switchDB();
        // Buscar o registro no FinanceiroPendente
        $financeiroPendente = $this->getRepositorio(FinanceiroPendente::class)->findPendenteById($this->session->get('userId'), $id);

        if (!$financeiroPendente) {
            throw $this->createNotFoundException('Registro financeiro pendente não encontrado.');
        }

        // Criar um novo registro no Financeiro Diário
        $financeiro = new Financeiro();
        $financeiro->setDescricao($financeiroPendente['descricao']);
        $financeiro->setValor($financeiroPendente['valor']);
        $financeiro->setData(new \DateTime()); // Define a data do pagamento como hoje
        $financeiro->setPetId($financeiroPendente['pet_id']);

        // Salvar no Financeiro Diário
        $this->getRepositorio(Financeiro::class)->save($this->session->get('userId'), $financeiro);

        // Remover do Financeiro Pendente
        $this->getRepositorio(FinanceiroPendente::class)->deletePendente($this->session->get('userId'), $id);

        $this->addFlash('success', 'Pagamento confirmado e movido para o Financeiro Diário.');

        return $this->redirectToRoute('financeiro_pendente');
    }


    /**
     * @Route("/pendente/confirmar/{id}", name="financeiro_confirmar_pagamento", methods={"POST"})
     */
    public function confirmarPagamento(int $id, FinanceiroPendenteRepository $financeiroPendenteRepository): Response
    {
        $this->switchDB();
        // Buscar o registro no FinanceiroPendente
        $financeiroPendente = $financeiroPendenteRepository->findPendenteById($this->session->get('userId'), $id);

        if (!$financeiroPendente) {
            throw $this->createNotFoundException('Registro financeiro pendente não encontrado.');
        }

        // Criar um novo registro no Financeiro Diário
        $financeiro = new Financeiro();
        $financeiro->setDescricao($financeiroPendente['descricao']);
        $financeiro->setValor($financeiroPendente['valor']);
        $financeiro->setData(new \DateTime()); // Data do pagamento
        $financeiro->setPetId($financeiroPendente['pet_id']);

        // Salvar no Financeiro Diário
        $this->getRepositorio(Financeiro::class)->save($this->session->get('userId'), $financeiro);

        // Remover do Financeiro Pendente
        $financeiroPendenteRepository->deletePendente($this->session->get('userId'), $id);

        $this->addFlash('success', 'Pagamento confirmado e movido para o Financeiro Diário.');

        return $this->redirectToRoute('financeiro_pendente');
    }

    /**
     * @Route("/executar-acao/{id}", name="financeiro_executar_acao", methods={"POST"})
     */
    public function executarAcao(Request $request, int $id): Response
    {
        $this->switchDB();
        $acao = $request->request->get('acao');
        
        if (!$acao) {
            return $this->redirectToRoute('financeiro_index');
        }

        if ($acao === 'editar') {
            return $this->redirectToRoute('financeiro_editar', ['id' => $id]);
        }

        if ($acao === 'deletar') {
            return $this->redirectToRoute('financeiro_deletar', ['id' => $id]);
        }

        return $this->redirectToRoute('financeiro_index');
    }

    /**
     * @Route("/relatorio/export", name="financeiro_relatorio_export", methods={"GET"})
     */
    public function exportRelatorioExcel(Request $request): Response
    {
        $this->switchDB();
        $mesInicio = $request->query->get('mes_inicio');
        $mesFim = $request->query->get('mes_fim');

        $dataInicio = $mesInicio ? new \DateTime($mesInicio . '-01') : new \DateTime('first day of this month');
        $dataFim = $mesFim ? new \DateTime($mesFim . '-01') : new \DateTime('last day of this month');
        $dataFim->modify('last day of this month');

        $relatorio = $this->getRepositorio(Financeiro::class)->getRelatorioPorPeriodo($this->session->get('userId'), $dataInicio, $dataFim);

        // Criar planilha com PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Cabeçalhos
        $sheet->setCellValue('A1', 'Data');
        $sheet->setCellValue('B1', 'Total');

        // Adicionar os dados
        $row = 2;
        foreach ($relatorio as $item) {
            $sheet->setCellValue('A' . $row, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTime($item['data'])));
            $sheet->getStyle('A' . $row)->getNumberFormat()->setFormatCode('DD/MM/YYYY');
            $sheet->setCellValue('B' . $row, $item['total']);
            $row++;
        }

        // Criar resposta
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'relatorio_financeiro.xlsx';
        $temp_file = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($temp_file);

        return $this->file($temp_file, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

}
