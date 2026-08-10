<?php

namespace App\Controller;

use App\Service\ImportacaoExcelService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
     * Página principal de importação
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
     * Download do arquivo modelo
     * @Route("/download-modelo", name="importacao_download_modelo", methods={"GET"})
     */
    public function downloadModelo(): StreamedResponse
    {
        $this->switchDB();
        $idBase = $this->getIdBase();

        // Gera o arquivo modelo
        $spreadsheet = $this->importacaoService->gerarArquivoModelo($idBase);

        // Prepara o arquivo para download
        $fileName = 'modelo_importacao_' . date('Y-m-d-His') . '.xlsx';

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Processa o upload e importação dos dados
     * @Route("/processar", name="importacao_processar", methods={"POST"})
     */
    public function processar(Request $request): JsonResponse
    {
        $this->switchDB();
        $idBase = $this->getIdBase();

        // Valida CSRF token
        if (!$this->isCsrfTokenValid('importacao', $request->request->get('_token'))) {
            return new JsonResponse([
                'sucesso' => false,
                'erro' => 'Token de segurança inválido',
            ], Response::HTTP_FORBIDDEN);
        }

        // Obtém o arquivo enviado
        $file = $request->files->get('arquivo');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse([
                'sucesso' => false,
                'erro' => 'Nenhum arquivo foi enviado',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Valida extensão do arquivo
        $mimeType = $file->getMimeType();
        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/octet-stream',
        ];

        if (!in_array($mimeType, $allowedMimes)) {
            return new JsonResponse([
                'sucesso' => false,
                'erro' => 'Formato de arquivo inválido. Use apenas .xlsx ou .xls',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Valida tamanho do arquivo (máximo 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return new JsonResponse([
                'sucesso' => false,
                'erro' => 'Arquivo muito grande. Tamanho máximo: 5MB',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Processa a importação
            $resultado = $this->importacaoService->importarDados($file, $idBase);

            return new JsonResponse($resultado);

        } catch (\Exception $e) {
            return new JsonResponse([
                'sucesso' => false,
                'erro' => 'Erro ao processar arquivo: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * API para validar arquivo antes da importação
     * @Route("/validar", name="importacao_validar", methods={"POST"})
     */
    public function validar(Request $request): JsonResponse
    {
        $this->switchDB();

        $file = $request->files->get('arquivo');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse([
                'valido' => false,
                'erro' => 'Nenhum arquivo foi enviado',
            ]);
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $abas = $spreadsheet->getSheetNames();

            // Verifica quais abas estão presentes
            $abas_presentes = [
                'Clientes' => in_array('Clientes', $abas),
                'Pets' => in_array('Pets', $abas),
                'Produtos' => in_array('Produtos', $abas),
                'Serviços' => in_array('Serviços', $abas),
            ];

            // Conta registros em cada aba (aproximado)
            $registros = [];
            foreach (['Clientes', 'Pets', 'Produtos', 'Serviços'] as $aba) {
                if (in_array($aba, $abas)) {
                    $sheet = $spreadsheet->getSheetByName($aba);
                    $registros[$aba] = max(0, $sheet->getHighestRow() - 1);
                } else {
                    $registros[$aba] = 0;
                }
            }

            return new JsonResponse([
                'valido' => true,
                'abas_presentes' => $abas_presentes,
                'total_registros' => array_sum($registros),
                'registros' => $registros,
                'arquivo_nome' => $file->getClientOriginalName(),
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'valido' => false,
                'erro' => 'Erro ao validar arquivo: ' . $e->getMessage(),
            ]);
        }
    }
}
