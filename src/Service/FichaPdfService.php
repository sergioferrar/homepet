<?php

namespace App\Service;

use App\Entity\Consulta;
use App\Entity\Pet;
use Doctrine\ORM\EntityManagerInterface;

class FichaPdfService
{
    private QuillDeltaConverterService $deltaConverter;
    private GeradorpdfService $gerador;

    /**
     * Dados da clínica exibidos no cabeçalho do documento.
     * ALTERE os valores abaixo com os dados reais do estabelecimento.
     */
    private array $configClinica = [
        'nome'     => 'CLÍNICA VETERINÁRIA [ALTERAR]',
        'cnpj'     => '',
        'endereco' => '',
        'telefone' => '',
    ];

    public function __construct(
        QuillDeltaConverterService $deltaConverter,
        GeradorpdfService $gerador
    ) {
        $this->deltaConverter = $deltaConverter;
        $this->gerador = $gerador;
    }

    /**
     * Gera o PDF da ficha completa do pet com todo o histórico de atendimentos.
     *
     * @param int $baseId ID da clínica/base (multi-tenant)
     * @param int $petId  ID do pet
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function gerarFichaPet(int $baseId, int $petId, EntityManagerInterface $em)
    {
        $pet = $em->getRepository(Pet::class)->findPetById($baseId, $petId);

        if (!$pet) {
            throw new \Exception('Pet não encontrado');
        }

        // findAllByPetId já traz veterinario_nome e veterinario_crmv via JOIN.
        $consultas = $em->getRepository(Consulta::class)->findAllByPetId($baseId, $petId);

        $emitidoEm  = date('d/m/Y H:i:s');
        $referencia = $this->gerarReferencia($petId);

        $html = $this->montarHtml($pet, $consultas, $referencia, $emitidoEm);

        $this->gerador->configuracaoPagina('A4', 10, 10, 50, 15, 10, 12);
        $this->gerador->setNomeArquivo(
            'Ficha_' . preg_replace('/[^a-zA-Z0-9]/', '_', $pet['nome'] ?? 'Pet') . '_' . date('YmdHis')
        );
        $this->gerador->montaCabecalhoPadrao($this->montarCabecalho($pet, $referencia));
        $this->gerador->setRodape($this->montarRodape($referencia, $emitidoEm));
        $this->gerador->addPagina('P');
        $this->gerador->conteudo($html);

        return $this->gerador->gerar();
    }

    /**
     * Gera um número de referência do documento.
     *
     * ATENÇÃO: esta referência identifica a via impressa, mas NÃO é armazenada
     * em banco. Para que a clínica consiga conferir depois uma via apresentada
     * por terceiros, é preciso persistir a referência (ver documentação).
     */
    private function gerarReferencia(int $petId): string
    {
        return sprintf('%s-%s-%d', date('Ymd'), date('His'), $petId);
    }

    private function montarHtml(array $pet, array $consultas, string $referencia, string $emitidoEm): string
    {
        $html = '<div style="font-family: Arial, sans-serif; color:#333; line-height:1.55; font-size:12px;">';

        $html .= $this->montarSecaoClinica();
        $html .= $this->montarSecaoIdentificacao($referencia, $emitidoEm, count($consultas));
        $html .= $this->montarSecaoPet($pet);
        $html .= $this->montarSecaoTutor($pet);

        if (!empty($consultas)) {
            $html .= $this->montarSecaoConsultas($consultas);
        } else {
            $html .= '<div style="background:#f9f9f9; padding:10px; margin:12px 0;">';
            $html .= '<p style="margin:0; color:#666;"><em>Nenhum atendimento registrado para este pet.</em></p>';
            $html .= '</div>';
        }

        $html .= $this->montarSecaoAssinatura($consultas);
        $html .= '</div>';

        return $html;
    }

    private function montarSecaoClinica(): string
    {
        $c = $this->configClinica;

        $html = '<div style="background:#2a6762; color:#fff; padding:14px 18px; text-align:center; margin-bottom:14px;">';
        $html .= '<div style="font-size:15px; font-weight:bold;">' . htmlspecialchars($c['nome'], ENT_QUOTES) . '</div>';

        if (!empty($c['endereco'])) {
            $html .= '<div style="font-size:10px; margin-top:3px;">' . htmlspecialchars($c['endereco'], ENT_QUOTES) . '</div>';
        }

        $linha = [];
        if (!empty($c['cnpj']))     { $linha[] = 'CNPJ: ' . htmlspecialchars($c['cnpj'], ENT_QUOTES); }
        if (!empty($c['telefone'])) { $linha[] = 'Tel.: ' . htmlspecialchars($c['telefone'], ENT_QUOTES); }
        if ($linha) {
            $html .= '<div style="font-size:10px; margin-top:2px;">' . implode(' &nbsp;|&nbsp; ', $linha) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function montarSecaoIdentificacao(string $referencia, string $emitidoEm, int $totalConsultas): string
    {
        $html = '<div style="border:1px solid #2a6762; padding:10px 14px; margin-bottom:16px;">';
        $html .= '<div style="font-size:13px; font-weight:bold; color:#2a6762; text-align:center; margin-bottom:8px;">';
        $html .= 'FICHA CLÍNICA — HISTÓRICO DE ATENDIMENTOS';
        $html .= '</div>';

        $html .= '<table style="width:100%; border-collapse:collapse; font-size:11px;">';
        $html .= '<tr>';
        $html .= '<td style="padding:2px 0; width:33%;"><strong>Referência:</strong> ' . htmlspecialchars($referencia, ENT_QUOTES) . '</td>';
        $html .= '<td style="padding:2px 0; width:34%;"><strong>Emitido em:</strong> ' . $emitidoEm . '</td>';
        $html .= '<td style="padding:2px 0; width:33%;"><strong>Atendimentos:</strong> ' . $totalConsultas . '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<div style="margin-top:8px; padding-top:6px; border-top:1px solid #e2e8f0; font-size:10px; color:#555;">';
        $html .= 'Documento emitido a pedido do tutor, contendo o registro dos atendimentos realizados nesta clínica. ';
        $html .= 'Válido mediante assinatura do médico-veterinário responsável e carimbo do estabelecimento.';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    private function montarSecaoPet(array $pet): string
    {
        $castrado = isset($pet['castrado']) && $pet['castrado'] ? 'Sim' : 'Não';
        $peso     = (isset($pet['peso']) && $pet['peso'] !== null && $pet['peso'] !== '') ? $pet['peso'] . ' kg' : '—';

        $nasc = '—';
        if (!empty($pet['dataNascimento'])) {
            $ts = strtotime($pet['dataNascimento']);
            if ($ts) { $nasc = date('d/m/Y', $ts); }
        }

        $linhas = [
            ['Nome',     $pet['nome']    ?? '—', 'Espécie',  $pet['especie'] ?? '—'],
            ['Raça',     $pet['raca']    ?? '—', 'Sexo',     $pet['sexo']    ?? '—'],
            ['Porte',    $pet['porte']   ?? '—', 'Peso',     $peso],
            ['Nascim.',  $nasc,                  'Castrado', $castrado],
        ];

        $html  = $this->tituloSecao('IDENTIFICAÇÃO DO ANIMAL');
        $html .= '<table style="width:100%; border-collapse:collapse; font-size:11.5px; margin-bottom:14px;">';

        foreach ($linhas as $i => $l) {
            $bg = $i % 2 === 0 ? '#f7fbfb' : '#ffffff';
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="padding:5px 8px; font-weight:bold; width:16%; border:1px solid #dde8e7;">' . $l[0] . '</td>';
            $html .= '<td style="padding:5px 8px; width:34%; border:1px solid #dde8e7;">' . htmlspecialchars((string) $l[1], ENT_QUOTES) . '</td>';
            $html .= '<td style="padding:5px 8px; font-weight:bold; width:16%; border:1px solid #dde8e7;">' . $l[2] . '</td>';
            $html .= '<td style="padding:5px 8px; width:34%; border:1px solid #dde8e7;">' . htmlspecialchars((string) $l[3], ENT_QUOTES) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        if (!empty($pet['observacoes'])) {
            $html .= '<div style="font-size:11px; margin:-6px 0 14px 0;">';
            $html .= '<strong>Observações do cadastro:</strong> ' . nl2br(htmlspecialchars((string) $pet['observacoes'], ENT_QUOTES));
            $html .= '</div>';
        }

        return $html;
    }

    private function montarSecaoTutor(array $pet): string
    {
        $endereco = trim((string) ($pet['dono_endereco'] ?? ''));
        $endereco = trim($endereco, " ,-");
        if ($endereco === '') { $endereco = '—'; }

        $linhas = [
            ['Nome',     $pet['dono_nome']     ?? '—'],
            ['Telefone', $pet['dono_telefone'] ?? '—'],
            ['E-mail',   $pet['dono_email']    ?? '—'],
            ['Endereço', $endereco],
        ];

        $html  = $this->tituloSecao('TUTOR RESPONSÁVEL');
        $html .= '<table style="width:100%; border-collapse:collapse; font-size:11.5px; margin-bottom:16px;">';

        foreach ($linhas as $i => $l) {
            $bg = $i % 2 === 0 ? '#f7fbfb' : '#ffffff';
            $html .= '<tr style="background:' . $bg . ';">';
            $html .= '<td style="padding:5px 8px; font-weight:bold; width:16%; border:1px solid #dde8e7;">' . $l[0] . '</td>';
            $html .= '<td style="padding:5px 8px; border:1px solid #dde8e7;">' . htmlspecialchars((string) $l[1], ENT_QUOTES) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }

    private function montarSecaoConsultas(array $consultas): string
    {
        $html = $this->tituloSecao('HISTÓRICO DE ATENDIMENTOS');

        // findAllByPetId ordena DESC (mais recente primeiro). Invertemos para
        // leitura cronológica, que é o esperado num histórico clínico.
        $consultas = array_reverse($consultas);
        $total = count($consultas);

        foreach ($consultas as $index => $consulta) {
            $numero = $index + 1;

            $data = '—';
            if (!empty($consulta['data'])) {
                $ts = strtotime($consulta['data']);
                if ($ts) { $data = date('d/m/Y', $ts); }
            }

            $hora = '';
            if (!empty($consulta['hora'])) {
                $ts = strtotime($consulta['hora']);
                if ($ts) { $hora = ' às ' . date('H:i', $ts); }
            }

            $tipo   = htmlspecialchars((string) ($consulta['tipo'] ?? 'Atendimento'), ENT_QUOTES);
            $status = htmlspecialchars((string) ($consulta['status'] ?? '—'), ENT_QUOTES);

            $vet = trim((string) ($consulta['veterinario_nome'] ?? ''));
            if ($vet !== '' && !empty($consulta['veterinario_crmv'])) {
                $vet .= ' — CRMV ' . $consulta['veterinario_crmv'];
            }
            $vet = $vet !== '' ? htmlspecialchars($vet, ENT_QUOTES) : 'Não informado';

            $html .= '<div style="border:1px solid #dde8e7; margin-bottom:12px; page-break-inside:avoid;">';

            $html .= '<table style="width:100%; border-collapse:collapse; background:#eef6f5;">';
            $html .= '<tr>';
            $html .= '<td style="padding:6px 10px; font-weight:bold; color:#2a6762; font-size:11.5px;">';
            $html .= 'Atendimento ' . $numero . ' de ' . $total . ' &nbsp;&middot;&nbsp; ' . $tipo;
            $html .= '</td>';
            $html .= '<td style="padding:6px 10px; text-align:right; font-size:11px; color:#555;">';
            $html .= $data . $hora . ' &nbsp;&middot;&nbsp; ' . ucfirst($status);
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';

            $html .= '<div style="padding:8px 10px;">';
            $html .= '<div style="font-size:11px; margin-bottom:6px;"><strong>Médico-veterinário:</strong> ' . $vet . '</div>';

            if (!empty($consulta['anamnese'])) {
                $anamnese = $this->deltaConverter->deltaToHtml($consulta['anamnese']);
                if (trim(strip_tags($anamnese)) !== '') {
                    $html .= '<div style="font-size:11px; font-weight:bold; color:#2a6762; margin:8px 0 4px;">ANAMNESE / AVALIAÇÃO CLÍNICA</div>';
                    $html .= '<div style="border-left:3px solid #c5e5e3; padding:6px 10px; background:#fbfdfd; font-size:11.5px;">';
                    $html .= $anamnese;
                    $html .= '</div>';
                }
            }

            if (!empty($consulta['observacoes'])) {
                $html .= '<div style="font-size:11px; font-weight:bold; color:#2a6762; margin:8px 0 4px;">OBSERVAÇÕES</div>';
                $html .= '<div style="border-left:3px solid #e2e8f0; padding:6px 10px; background:#fafafa; font-size:11.5px;">';
                $html .= nl2br(htmlspecialchars((string) $consulta['observacoes'], ENT_QUOTES));
                $html .= '</div>';
            }

            if (!empty($consulta['attachment_original'])) {
                $html .= '<div style="font-size:10px; color:#666; margin-top:6px;">';
                $html .= 'Anexo registrado neste atendimento: ' . htmlspecialchars((string) $consulta['attachment_original'], ENT_QUOTES);
                $html .= ' <em>(arquivo não incluído neste PDF)</em>';
                $html .= '</div>';
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    private function montarSecaoAssinatura(array $consultas): string
    {
        // Se todos os atendimentos foram do mesmo veterinário, já preenchemos o nome.
        $vets = [];
        foreach ($consultas as $c) {
            $nome = trim((string) ($c['veterinario_nome'] ?? ''));
            if ($nome !== '') {
                $vets[$nome] = $c['veterinario_crmv'] ?? '';
            }
        }

        $nomeVet = '__________________________________';
        $crmvVet = '____________________';
        if (count($vets) === 1) {
            $nomeVet = htmlspecialchars((string) array_key_first($vets), ENT_QUOTES);
            $crmv    = reset($vets);
            if (!empty($crmv)) { $crmvVet = htmlspecialchars((string) $crmv, ENT_QUOTES); }
        }

        $html  = '<div style="margin-top:26px; page-break-inside:avoid;">';
        $html .= $this->tituloSecao('ASSINATURA E CARIMBO');

        $html .= '<table style="width:100%; border-collapse:collapse; margin-top:34px;">';
        $html .= '<tr>';

        $html .= '<td style="width:50%; text-align:center; padding:0 14px; vertical-align:bottom;">';
        $html .= '<div style="border-top:1px solid #333; padding-top:5px; font-size:10.5px;">';
        $html .= '<strong>Médico-veterinário responsável</strong><br>';
        $html .= $nomeVet . '<br>CRMV: ' . $crmvVet;
        $html .= '</div>';
        $html .= '</td>';

        $html .= '<td style="width:50%; text-align:center; padding:0 14px;">';
        $html .= '<div style="border:1px dashed #999; padding:22px 10px; font-size:10px; color:#777;">';
        $html .= 'Carimbo do estabelecimento';
        $html .= '</div>';
        $html .= '</td>';

        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    private function tituloSecao(string $texto): string
    {
        return '<div style="font-size:12px; font-weight:bold; color:#2a6762; '
             . 'border-bottom:2px solid #14B8A6; padding-bottom:3px; margin:0 0 8px 0;">'
             . $texto . '</div>';
    }

    private function montarCabecalho(array $pet, string $referencia): string
    {
        $petNome  = htmlspecialchars((string) ($pet['nome'] ?? 'Pet'), ENT_QUOTES);
        $donoNome = htmlspecialchars((string) ($pet['dono_nome'] ?? 'Tutor não vinculado'), ENT_QUOTES);

        $html  = '<table style="width:100%; border-collapse:collapse; font-family:Arial, sans-serif;">';
        $html .= '<tr>';
        $html .= '<td style="padding:4px 0; font-size:11px; color:#2a6762;">';
        $html .= '<strong>Ficha Clínica</strong> &nbsp;&middot;&nbsp; ' . $petNome . ' &nbsp;&middot;&nbsp; Tutor: ' . $donoNome;
        $html .= '</td>';
        $html .= '<td style="padding:4px 0; text-align:right; font-size:9px; color:#94A3B8;">';
        $html .= 'Ref. ' . htmlspecialchars($referencia, ENT_QUOTES);
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '<tr><td colspan="2" style="border-bottom:1.5px solid #14B8A6; padding-bottom:2px;"></td></tr>';
        $html .= '</table>';

        return $html;
    }

    private function montarRodape(string $referencia, string $emitidoEm): string
    {
        $html  = '<table style="width:100%; border-collapse:collapse; font-family:Arial, sans-serif;">';
        $html .= '<tr><td style="text-align:center; font-size:8px; color:#94A3B8; padding-top:4px;">';
        $html .= 'Ref. ' . htmlspecialchars($referencia, ENT_QUOTES) . ' &nbsp;&middot;&nbsp; Emitido em ' . $emitidoEm;
        $html .= ' &nbsp;&middot;&nbsp; Documento com dados pessoais &mdash; tratar conforme a LGPD';
        $html .= '</td></tr>';
        $html .= '</table>';

        return $html;
    }
}