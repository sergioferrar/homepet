<?php
namespace App\Service;

class TempDirManager
{
    public $diretorioProjeto;
    public $diretorioEspecifico;

    public function __construct(string $diretorioProjeto)
    {
        $this->diretorioProjeto = $diretorioProjeto;
    }

    public function init()
    {
        $dataHoje = date('dmY');

        $diretorioTemp = $_SERVER["PASTA_PROJETO_TEMPORARIOS"] ?? ($this->diretorioProjeto . "/var/temp");
        
        // Cria o diretório base se não existir (com permissão recursiva)
        if (!is_dir($diretorioTemp)) {
            mkdir($diretorioTemp, 0777, true);
        }

        $nanosegundos = (int) (microtime(true) * 1000000000);
        $hora = date("H_i_s");
        $this->diretorioEspecifico = $diretorioTemp . "/" . ($dataHoje . "_" . $hora . "__" . $nanosegundos . "/");
        mkdir($this->diretorioEspecifico, 0777, true);
    }

    public function diretorioBase(): string
    {
        return $this->diretorioEspecifico;
    }

    public function obterCaminho(string $filename): string
    {
        return $this->diretorioEspecifico . $filename;
    }

    public function deletarArquivo(string $filename)
    {
        $path = $this->obterCaminho($filename);
        is_dir($path) ? $this->rrmdir($path) : unlink($path);
    }

    public function limparDiretorio()
    {
        $di = new \RecursiveDirectoryIterator($this->diretorioEspecifico, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            $file->isDir() ? $this->rrmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
    }

    public function deletarDiretorio()
    {
        $this->rrmdir($this->diretorioEspecifico);
    }

    public function rrmdir($source, $removeOnlyChildren = false)
    {
        if (empty($source) || file_exists($source) === false) {
            return false;
        }

        if (is_file($source) || is_link($source)) {
            return unlink($source);
        }

        $files = new \RecursiveIteratorIterator
            (
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                if ($this->rrmdir($fileinfo->getRealPath()) === false) {
                    return false;
                }
            } else {
                if (unlink($fileinfo->getRealPath()) === false) {
                    return false;
                }
            }
        }

        if ($removeOnlyChildren === false) {
            return rmdir($source);
        }

        return true;
    }
}