<?php

namespace App\Controller;

use App\Service\ImportacaoDadosService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Tela de importação de dados legados (Clientes, Pets, Produtos e Serviços) vindos
 * de outro sistema para dentro da base do estabelecimento (tenant) logado.
 *
 * Acesso restrito a ROLE_ADMIN, seguindo o mesmo padrão de verificação inline
 * já usado em HomeController/MenuController deste projeto.
 *
 * @Route("dashboard/importacao")
 */
class ImportacaoController extends DefaultController
{
    /**
     * @Route("/", name="importacao_index", methods={"GET"})
     */
    public function index(): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Apenas administradores podem acessar a importação de dados.');
            return $this->redirectToRoute('home');
        }

        return $this->render('importacao/index.html.twig');
    }

    /**
     * @Route("/modelo", name="importacao_modelo", methods={"GET"})
     */
    public function baixarModelo(ImportacaoDadosService $importacaoDadosService): StreamedResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Apenas administradores podem acessar a importação de dados.');
            return new StreamedResponse(function () { }, Response::HTTP_FORBIDDEN);
        }

        $spreadsheet = $importacaoDadosService->gerarArquivoModelo();
        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="modelo-importacao-homepet.xlsx"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * @Route("/processar", name="importacao_processar", methods={"POST"})
     */
    public function processar(Request $request, ImportacaoDadosService $importacaoDadosService): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Apenas administradores podem acessar a importação de dados.');
            return $this->redirectToRoute('home');
        }

        $this->switchDB();
        $baseId = $this->getIdBase();

        $arquivo = $request->files->get('arquivo');

        if (!$arquivo) {
            $this->addFlash('error', 'Selecione um arquivo .xlsx ou .xls para importar.');
            return $this->redirectToRoute('importacao_index');
        }

        $extensoesPermitidas = ['xlsx', 'xls', 'csv'];
        if (!in_array(strtolower($arquivo->getClientOriginalExtension()), $extensoesPermitidas, true)) {
            $this->addFlash('error', 'Formato de arquivo inválido. Envie um arquivo .xlsx, .xls ou .csv.');
            return $this->redirectToRoute('importacao_index');
        }

        if ($arquivo->getSize() > 5 * 1024 * 1024) {
            $this->addFlash('error', 'O arquivo excede o tamanho máximo permitido (5MB).');
            return $this->redirectToRoute('importacao_index');
        }

        try {
            $resultado = $importacaoDadosService->importarArquivo($arquivo, (string) $baseId);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Não foi possível processar o arquivo: ' . $e->getMessage());
            return $this->redirectToRoute('importacao_index');
        }

        return $this->render('importacao/resultado.html.twig', [
            'resultado' => $resultado,
        ]);
    }
}
