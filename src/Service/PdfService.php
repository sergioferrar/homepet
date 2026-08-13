<?php

namespace App\Service;

use Mpdf\Mpdf;
use Twig\Environment;
use Symfony\Component\HttpFoundation\Response;

class PdfService
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function gerarPdf(string $template, array $dados = [], string $nomeArquivo = 'documento.pdf', string $modo = 'I'): Response
    {
        $html = $this->twig->render($template, $dados);

        $mpdf = new Mpdf([
            'default_font' => 'sans-serif',
            'margin_top' => 15,
            'margin_bottom' => 20,
            'margin_left' => 15,
            'margin_right' => 15
        ]);

        $mpdf->WriteHTML($html);

        return new Response($mpdf->Output($nomeArquivo, $modo), \Symfony\Component\HttpFoundation\Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
