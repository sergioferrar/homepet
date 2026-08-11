<?php

namespace App\Controller;

use App\Service\ImportacaoExcelService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

/**
 * @Route("/admin/importacao")
 * @IsGranted("ROLE_ADMIN")
 */
class ImportacaoController extends DefaultController
{
    private $importacaoService;

    public function __construct(ImportacaoExcelService $importacaoService)
    {
        $this->importacaoService = $importacaoService;
    }

    /**
     * @Route("/", name="importacao_index", methods={"GET"})
     */
    public function index(): Response
    {
        $this->switchDB();
        
        return $this->render('admin/importacao/index.html.twig', [
            'pageTitle' => 'Importação de Dados',
        ]);
    }

    /**
     * @Route("/download-modelo", name="importacao_download_modelo", methods={"GET"})
     */
    public function downloadModelo(): StreamedResponse
    {
        $this->switchDB();

        $arquivoExcel = $this->importacaoService->gerarArquivoModelo();

        $response = new StreamedResponse(function () use ($arquivoExcel) {
            echo $arquivoExcel;
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="Modelo_Importacao_HomePet.xlsx"');
        $response->headers->set('Cache-Control', 'public, max-age=0');

        return $response;
    }

    /**
     * @Route("/validar", name="importacao_validar", methods={"POST"})
     */
    public function validar(Request $request): JsonResponse
    {
        $this->switchDB();

        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('importacao', $csrfToken)) {
            return new JsonResponse([
                'sucesso' => false,
                'mensagem' => 'Token CSRF inválido. Tente novamente.',
            ], 400);
        }

        $file = $request->files->get('arquivo');

        if (!$file) {
            return new JsonResponse([
                'sucesso' => false,
                'mensagem' => 'Nenhum arquivo foi enviado.',
            ], 400);
        }

        $mimeType = $file->getMimeType();
        $nomearquivo = $file->getClientOriginalName();
        $extensao = pathinfo($nomearquivo, PATHINFO_EXTENSION);

        if (!in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/x-tika-ooxml',
        ])) {
            return new JsonResponse([
                'sucesso' => false,
                'mensagem' => 'Formato de arquivo inválido. Use .xlsx ou .xls',
            ], 400);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse([
                'sucesso' => false,
                'mensagem' => 'Arquivo muito grande. Máximo 5MB.',
            ], 400);
        }

        try {
            $resultado = $this->importacaoService->validarArquivo($file);
            
            return new JsonResponse([
                'sucesso' => true,
                'abas' => $resultado['abas'] ?? [],
                'registros' => $resultado['registros'] ?? [],
                'mensagem' => 'Arquivo válido!',
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'sucesso' => false,
                'mensagem' => 'Erro ao validar arquivo: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * @Route("/processar", name="importacao_processar", methods={"POST"})
     */
    public function processar(Request $request): JsonResponse
    {
        $this->switchDB();

        $csrfToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('importacao', $csrfToken)) {
            return new JsonResponse([
                'sucesso' => false,
                'mensagem' => 'Token CSRF inválido. Tente novamente.',
            ], 400);
        }

        $file = $request->files->get('arquivo');

        if (!$file) {
            return new JsonResponse([
                'sucesso' => false,
                'mensagem' => 'Nenhum arquivo foi enviado.',
            ], 400);
        }

        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/x-tika-ooxml',
        ])) {
            return new JsonResponse([
                'sucesso' => false,
                'mensagem' => 'Formato de arquivo inválido. Use .xlsx ou .xls',
            ], 400);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse([
                'sucesso' => false,
                'mensagem' => 'Arquivo muito grande. Máximo 5MB.',
            ], 400);
        }

        try {
            $resultado = $this->importacaoService->importarDados($file);

            return new JsonResponse([
                'sucesso' => $resultado['sucesso'] ?? false,
                'mensagens_sucesso' => $resultado['mensagens_sucesso'] ?? [],
                'erros' => $resultado['erros'] ?? [],
                'avisos' => $resultado['avisos'] ?? [],
                'total_processado' => $resultado['total_processado'] ?? 0,
                'total_importado' => $resultado['total_importado'] ?? 0,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'sucesso' => false,
                'erros' => ['Erro ao processar importação: ' . $e->getMessage()],
            ], 400);
        }
    }
}