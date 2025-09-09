<?php

//('BASEPATH') or exit('No direct script access allowed');
//ini_set('display_errors', 0);
// //ini_set('memory_limit', '2G');
// //ini_set('max_execution_time', '9999');
////require_once FCPATH . '/vendor/autoload.php';

namespace App\Service;

use App\Service\TempDirManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Mpdf\Mpdf;

class GeradorpdfService
{

    /** @var TempDirManager*/
    private $tempDirManager;

    /** @var RequestStack*/
    protected $request;

    private $pdf;
    private $css;
    private $nomeArquivo;
    private $cabecalhoTabelaPadrao;
    private $cabecalho;
    private $conteudo;
    private $rodape;
    private $config = [
        'mode' => '',
        'format' => 'A4',
        'default_font_size' => 0,
        'default_font' => '',
        'margin_left' => 5,
        'margin_right' => 5,
        'margin_top' => 5,
        'margin_bottom' => 5,
        'margin_header' => 5,
        'margin_footer' => 5,
        'orientation' => 'P',
        //'tempDir' => 'FCPATH' . './arquivos/tmp',
    ];

    public function __construct(TempDirManager $tempDirManager, RequestStack $request)
    {
        $this->tempDirManager = $tempDirManager;
        $this->request = $request->getCurrentRequest();

        $folderPath = dirname(__DIR__, 2) . '/public/';

        // //require("mpdf/mpdf.php");
        $this->pdf = new \Mpdf\Mpdf($this->config);
//        //$this->ci = &get_instance();
        // $this->pdf = new mPDF('c', 'A4', '', '', 5, 5, 5, 5, 5, 5); ##A4-L orientação paisagem ## parametros de numeros: margem esquer, margem direita, margem topo conteudo, bottom,topo cabecalho
        $this->pdf->SetFooter('<div class="text-left">Gerado em: ' . date('d/m/Y H:i') . ' System Home Pet - Seu CRM para clínicas e pet shops</div>'); ## o "||" aumenta o espaço entre  as frases
        //$this->pdf->showImageErrors = true;
        //$this->pdf->allow_charset_conversion = true;
        //$this->pdf->charset_in = 'UTF-8';
        $this->pdf->list_indent_first_level = 1;
        $this->css = '';//file_get_contents($folderPath . 'assets/css/style.css'); ## caminho para o css
        $this->nomeArquivo = date('d/m/Y') . 'pdf';
    }

    public function getTempDirManager()
    {
        return $this->tempDirManager;
    }

    public function getConfig()
    {
        return $this->config;
    }

 public function configuracaoPagina($orientacao, $margEsquerda, $margDireita, $margTop, $margBottom, $margCabecalho, $margRodape)
    {
        // Use a propriedade tMargin e bMargin para definir a margem do conteúdo
        $this->pdf->tMargin = $margTop;
        $this->pdf->bMargin = $margBottom;

        // Use a propriedade margin_header e margin_footer para definir a margem do cabeçalho e rodapé
        $this->pdf->margin_header = $margCabecalho;
        $this->pdf->margin_footer = $margRodape;

        $this->pdf->DefOrientation = $orientacao;
        $this->pdf->DeflMargin = $margEsquerda;
        $this->pdf->DefrMargin = $margDireita;
    }

    public function montaCabecalhoPadrao($cabecalho)
    {
        $this->setCabecalho($cabecalho);
    }
    public function setCabecalho($html)
    {
        $this->pdf->SetHTMLHeader($html, '', true);
    }
    public function addPagina($orientacao)
    {
        $this->pdf->AddPage($orientacao);
    }
    public function setRodape($html)
    {
        $this->pdf->SetFooter($html);
    }
    public function setNomeArquivo($nomeArquivo)
    {
        $this->nomeArquivo = $nomeArquivo . '.pdf';
    }
    public function getNomeArquivo()
    {
        return $this->nomeArquivo;
    }
    public function setCss($css = false)
    {
        if ($css) {
            $this->css = file_get_contents('./css/' . $css); ## caminho para o css
        }
    }
    public function getCss()
    {
        return $this->css;
    }
    public function conteudo($html)
    {

        $this->pdf->WriteHTML($this->css, 1);
        $this->pdf->WriteHTML($html, 2);
    }
    public function gerar($gravar = false)
    {
        if ($gravar) {
            $this->tempDirManager->init();
            $this->config['tempDir'] = $this->tempDirManager->diretorioBase();

            $caminho = $this->tempDirManager->diretorioBase();
            
            $this->pdf->Output($caminho . '/' . $this->nomeArquivo, 'F');
            unset($this->pdf);
            $this->pdf = new \Mpdf\Mpdf($this->config);
            // $this->pdf = new mPDF('c', 'A4', '', '', 5, 5, 5, 5, 5, 5); ##A4-L orientação paisagem ## parametros de numeros: margem esquer, margem direita, margem topo conteudo, bottom,topo cabecalho
        } else {
            $this->pdf->Output($this->nomeArquivo, 'D');
        }
    }
    ## parametros para o output ####
    #
    #  I: envia o arquivo interno para o navegador. O plug-in é usado se estiver disponível. O nome dado pelo nome do arquivo é usado quando alguém escolhe a opção "Salvar como" opção no link gerar o PDF.
    #  D: enviar para o browser e forçar o download do arquivo com o nome dado pelo nome do arquivo.
    #  F: salvar em um arquivo local com o nome dado pelo nome do arquivo (pode incluir um caminho).
    #  S: devolver o documento como uma string. filename é ignorada.
}