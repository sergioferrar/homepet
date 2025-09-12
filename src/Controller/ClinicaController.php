<?php

namespace App\Controller;

use App\Entity\Cliente;
use App\Entity\Consulta;
use App\Entity\Pet;
use App\Entity\Internacao;
use App\Service\PdfService;
use App\Entity\DocumentoModelo;
use App\Repository\ConsultaRepository;
use App\Repository\DocumentoModeloRepository;
use App\Repository\InternacaoRepository;
use App\Repository\FinanceiroRepository;
use App\Repository\FinanceiroPendenteRepository;
use App\Entity\FinanceiroPendente;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\VeterinarioRepository;
use App\Entity\Veterinario;
use App\Repository\BoxRepository;
use App\Entity\Financeiro;
use App\Entity\Servico;
use App\Entity\Medicamento;
use App\Entity\InternacaoPrescricao;
use App\Entity\InternacaoExecucao;

/**
 * @Route("/clinica")
 */
class ClinicaController extends DefaultController
{
   /**
     * @Route("/dashboard", name="clinica_dashboard", methods={"GET"})
     */
    public function dashboard(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repoPet = $this->getRepositorio(\App\Entity\Pet::class);
        $repoCliente = $this->getRepositorio(\App\Entity\Cliente::class);
        $repoFinanceiro = $this->getRepositorio(\App\Entity\FinanceiroPendente::class);
        $repoConsulta = $this->getRepositorio(\App\Entity\Consulta::class);

        $repoInternacao = new InternacaoRepository($this->managerRegistry);
        $internacoes = $repoInternacao->listarInternacoesAtivas($baseId);
        $totalPets = $repoPet->countTotalPets($baseId);
        $totalDono = $repoCliente->countTotalDono($baseId);
        $debitosCliente = $repoFinanceiro->somarDebitosPendentes($baseId);
        $media = $repoConsulta->calcularMediaConsultas($baseId);
        $atendimentos = $repoConsulta->listarUltimosAtendimentos($baseId);
        $animaisCadastrados = method_exists($repoPet, 'listarPetsRecentes')
            ? $repoPet->listarPetsRecentes($baseId, 5)
            : [];

        $vacinasVencidas = method_exists($repoPet, 'listarVacinasPendentes')
            ? $repoPet->listarVacinasPendentes($baseId)
            : [];
        $vacinasProgramadas = method_exists($repoPet, 'listarVacinasProgramadas')
            ? $repoPet->listarVacinasProgramadas($baseId)
            : [];

        // 🔍 pesquisa
        $termo = $request->query->get('q');
        $pets = [];
        if ($termo) {
            $pets = $repoPet->pesquisarPetsOuTutor($baseId, $termo);
        }

        return $this->render('clinica/dashboard.html.twig', [
            'total_pets' => $totalPets,
            'debitos_cliente' => $debitosCliente,
            'media_atendimento' => $media,
            'atendimentos' => $atendimentos,
            'internados' => $internacoes,
            'vacinas_programadas' => $vacinasProgramadas,
            'vacinas_vencidas' => $vacinasVencidas,
            'totaldono' => $totalDono,
            'animais_cadastrados' => $animaisCadastrados,
            'pets' => $pets,
            'termo' => $termo,
        ]);
    }


    /**
     * @Route("/pet/{id}", name="clinica_detalhes_pet", methods={"GET"})
     */
    public function detalhesPet(
        int $id,
        ConsultaRepository $consultaRepo,
        DocumentoModeloRepository $documentoRepo,
        FinanceiroRepository $financeiroRepo,
        FinanceiroPendenteRepository $financeiroPendenteRepo,
        InternacaoRepository $internacaoRepo
    ): Response {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(\App\Entity\Pet::class)->findPetById($baseId, $id);
        if (!$pet) {
            throw $this->createNotFoundException('O pet não foi encontrado.');
        }

        $consultas = $consultaRepo->findAllByPetId($baseId, $id);
        $documentos = $documentoRepo->listarDocumentos($baseId);
        $financeiro = $financeiroRepo->buscarPorPet($baseId, $pet['id']);
        $financeiroPendente = $financeiroPendenteRepo->findBy(['petId' => $id]);
        $receitas = $this->getRepositorio(\App\Entity\Receita::class)->listarPorPet($baseId, $pet['id']);
        $internacaoAtivaId = $internacaoRepo->findAtivaIdByPet($baseId, $pet['id']);
        $ultimaInternacaoId  = $internacaoRepo->findUltimaIdByPet($baseId, $pet['id']);
        $internacoesPet = $internacaoRepo->listarInternacoesPorPet($baseId, $pet['id']);


        // --- BUSCA TODOS OS SERVIÇOS DA CLÍNICA ---
        $servicosClinica = $this->getRepositorio(\App\Entity\Servico::class)->findBy([
            'estabelecimentoId' => $baseId
        ]);

        $timeline_items = [];

        foreach ($consultas as $item) {
            $timeline_items[] = [
                'data' => new \DateTime($item['data'] . ' ' . $item['hora']),
                'tipo' => $item['tipo'] ?? 'Consulta',
                'observacoes' => $item['observacoes'],
                'anamnese' => $item['anamnese'] ?? null,
            ];
        }

        foreach ($receitas as $r) {
            $timeline_items[] = [
                'data' => new \DateTime($r['data']),
                'tipo' => 'Receita',
                'resumo' => $r['resumo'],
                'receita_cabecalho' => $r['cabecalho'], 
                'receita_conteudo' => $r['conteudo'],
                'receita_rodape' => $r['rodape'],
            ];
        }

        //  Agrupa por tipo
        $agrupado = [];
        foreach ($timeline_items as $item) {
            $tipo = $item['tipo'];
            if (!isset($agrupado[$tipo])) {
                $agrupado[$tipo] = [];
            }
            $agrupado[$tipo][] = $item;
        }

        //  Total de débitos PENDENTES
        $totalDebitos = 0;
        foreach ($financeiroPendente as $itemFinanceiro) {
            // Acessa o valor com o método getter do objeto
            $totalDebitos += $itemFinanceiro->getValor();
        }

        return $this->render('clinica/detalhes_pet.html.twig', [
            'pet' => $pet,
            'timeline_items' => $timeline_items,
            'timeline_agrupado' => $agrupado,
            'documentos' => $documentoRepo->listarDocumentos($baseId),
            'financeiro' => $financeiroRepo->buscarPorPet($baseId, $pet['id']),
            'financeiroPendente' => $financeiroPendenteRepo->findBy(['petId' => $id]),
            'consultas' => $consultas,
            'total_debitos' => $totalDebitos,
            'servicos_clinica' => $servicosClinica,
            'internacao_ativa_id' => $internacaoAtivaId,
            'ultima_internacao_id' => $ultimaInternacaoId,
            'internacoes_pet' => $internacoesPet,
        ]);
    }


    /**
     * @Route("/pet/{petId}/internacao/nova", name="clinica_nova_internacao", methods={"GET", "POST"})
     */
    public function novaInternacao(
        Request $request,
        int $petId,
        InternacaoRepository $internacaoRepo,
        VeterinarioRepository $veterinarioRepo,
        EntityManagerInterface $entityManager
    ): Response {
        $this->switchDB();
        $baseId = $this->getIdBase();
        
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        // lista para o formulário
        $veterinarios = $veterinarioRepo->findBy(['estabelecimentoId' => $baseId]);
        $boxes = [
            ['id' => 1, 'nome' => 'Box 1'],
            ['id' => 2, 'nome' => 'Box 2'],
            ['id' => 3, 'nome' => 'Box 3'],
        ];

        // POST: salva e redireciona para a ficha criada
        if ($request->isMethod('POST')) {
            $internacao = new Internacao();

            $donoId = $pet['dono_id'] ?? null;

            $internacao->setPetId($petId);
            $internacao->setDonoId($donoId);
            $internacao->setEstabelecimentoId($baseId);
            $internacao->setDataInicio(new \DateTime());
            $internacao->setStatus('ativa');
            $internacao->setMotivo((string) $request->request->get('queixa'));

            // Campos adicionais
            $internacao->setSituacao((string) $request->request->get('situacao'));
            $internacao->setRisco((string) $request->request->get('risco'));
            $internacao->setVeterinarioId((int) $request->request->get('veterinario_id'));
            $internacao->setBox((string) $request->request->get('box'));

            // Alta prevista (opcional)
            $altaPrevistaStr = trim((string) $request->request->get('alta_prevista'));
            if ($altaPrevistaStr !== '') {
                try {
                    $internacao->setAltaPrevista(new \DateTime($altaPrevistaStr));
                } catch (\Exception $e) {
                    $internacao->setAltaPrevista(null);
                }
            }

            $internacao->setDiagnostico((string) $request->request->get('diagnostico'));
            $internacao->setPrognostico((string) $request->request->get('prognostico'));
            $internacao->setAnotacoes((string) $request->request->get('alergias_marcacoes'));

            // Salva e obtém o ID gerado
            $novoId = $internacaoRepo->inserirInternacao($baseId, $internacao);

            // Cria um evento inicial na timeline da internação
            $internacaoRepo->inserirEvento(
                $baseId,
                $novoId,
                $petId,
                'Internação',
                'Internação iniciada',
                sprintf(
                    'Motivo: %s | Situação: %s | Risco: %s | Box: %s',
                    (string) $internacao->getMotivo(),
                    (string) $internacao->getSituacao(),
                    (string) $internacao->getRisco(),
                    (string) $internacao->getBox()
                ),
                new \DateTime()
            );

            $this->addFlash('success', 'Internação registrada com sucesso!');
            return $this->redirectToRoute('clinica_ficha_internacao', ['id' => $novoId]);
        }

        // GET: exibe o formulário
        return $this->render('clinica/nova_internacao.html.twig', [
            'pet' => $pet,
            'veterinarios' => $veterinarios,
            'boxes' => $boxes,
        ]);
    }


    /**
     * @Route("/pet/{petId}/atendimento/novo", name="clinica_novo_atendimento", methods={"POST"})
     */
    public function novoAtendimento(Request $request, int $petId, ConsultaRepository $consultaRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        $consulta = new Consulta();
        $consulta->setEstabelecimentoId($baseId);
        $consulta->setClienteId((int) $request->request->get('cliente_id'));
        $consulta->setPetId($petId);
        $consulta->setData(new \DateTime($request->request->get('data')));
        $consulta->setHora(new \DateTime($request->request->get('hora')));
        $consulta->setObservacoes($request->request->get('observacoes'));
        
        $consulta->setAnamnese($request->request->get('anamnese_delta'));
        
        $consulta->setTipo($request->request->get('tipo'));
        $consulta->setStatus('atendido');
        $consulta->setCriadoEm(new \DateTime());

        $consultaRepo->salvarConsulta($consulta);

        $this->addFlash('success', 'Atendimento salvo com sucesso!');
        return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
    }

    /**
     * @Route("/pet/{petId}/receita", name="clinica_nova_receita", methods={"GET","POST"})
     */
    public function receita(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        $cliente = $this->getRepositorio(Cliente::class)->find($pet['dono_id']);

        $this->restauraLoginDB();
        $clinica = $this->getRepositorio(\App\Entity\Estabelecimento::class)->find($baseId);

        $this->switchDB();
        // Busca o veterinário
        $vet = $this->getRepositorio(Veterinario::class)->findOneBy(['estabelecimentoId' => $baseId]);
        if (!$vet) {
            // Tratar caso em que o veterinário não é encontrado
            throw $this->createNotFoundException('Veterinário não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $conteudoDelta = $request->get('conteudo');
            $resumo = $request->get('resumo');

            $conteudoHtml = $this->quillDeltaToHtml($conteudoDelta);

            $cabecalhoHtml = "
                <div style='text-align:center; font-size:14px; font-weight:bold;'>
                    ".($clinica->getRazaoSocial() ?? 'Clínica Veterinária')." <br>
                    CNPJ: ".($clinica->getCnpj() ?? '')." <br>
                    {$clinica->getRua()}, {$clinica->getNumero()} - {$clinica->getBairro()}, {$clinica->getCidade()} - CEP: {$clinica->getCep()}
                </div>
                <hr>
                <div style='font-size:12px;'>
                    <strong>Tutor:</strong> {$cliente->getNome()} <br>
                    <strong>Pet:</strong> {$pet['nome']} ({$pet['especie']} - {$pet['raca']}, {$pet['idade']} anos) <br>
                    <strong>Sexo:</strong> {$pet['sexo']}
                </div>
                <hr>
            ";

            // --- NOVO: Cria o rodapé HTML fixo diretamente no PHP ---
            $rodapeHtml = "
                <div style='text-align:center; font-size:12px; margin-top: 20px;'>
                    <hr style='border: 1px dashed black; width: 50%; margin: 10px auto;'>
                    <p>Assinatura do Veterinário</p>
                    <p>
                        <strong>{$vet->getNome()}</strong> <br>
                        <strong>CRMV:</strong> {$vet->getCrmv()}
                    </p>
                </div>
                <div style='text-align:center; font-size:10px; margin-top: 10px;'>
                    <span>Documento emitido em: ".date('d/m/Y H:i:s')."</span>
                </div>
            ";

            $receita = new \App\Entity\Receita();
            $receita->setEstabelecimentoId($baseId);
            $receita->setPetId($petId);
            $receita->setData(new \DateTime());
            $receita->setCabecalho($cabecalhoHtml);
            $receita->setConteudo($conteudoDelta);
            $receita->setRodape($rodapeHtml); // <-- Agora salva o HTML fixo, não o delta do formulário
            $receita->setResumo($resumo);

            $this->getRepositorio(\App\Entity\Receita::class)->salvar($receita);

            $gerarPDF = new \App\Service\GeradorpdfService($this->tempDirManager, $this->requestStack);
            $gerarPDF->configuracaoPagina('A4', 10, 10, 50, 6, 10, 3);
            $gerarPDF->setNomeArquivo('Receita_'.$pet['nome'].'_'.date('YmdHis'));
            $gerarPDF->setRodape($rodapeHtml); // Usa o novo rodapé fixo
            $gerarPDF->montaCabecalhoPadrao($cabecalhoHtml);
            $gerarPDF->addPagina('P');
            $gerarPDF->conteudo($conteudoHtml);

            $this->addFlash('success', 'Receita registrada e PDF gerado com sucesso!');
            return $gerarPDF->gerar();
        }

        return $this->render('clinica/detalhes_pet.html.twig', [
            'pet' => $pet,
            'clinica' => $clinica,
            'veterinario' => $vet, 
        ]);
    }

    private function quillDeltaToHtml(?string $deltaJson): string
    {
        if (!$deltaJson) {
            return '';
        }

        $delta = json_decode($deltaJson, true);
        if (!$delta || !isset($delta['ops'])) {
            return '';
        }

        $html = '';
        foreach ($delta['ops'] as $op) {
            $insert = $op['insert'] ?? '';
            $attributes = $op['attributes'] ?? [];

            if (isset($attributes['bold'])) {
                $insert = "<strong>{$insert}</strong>";
            }
            if (isset($attributes['italic'])) {
                $insert = "<em>{$insert}</em>";
            }
            if (isset($attributes['underline'])) {
                $insert = "<u>{$insert}</u>";
            }
            if (isset($attributes['header'])) {
                $level = $attributes['header'];
                $insert = "<h{$level}>{$insert}</h{$level}>";
            }
            if (isset($attributes['list'])) {
                $listType = $attributes['list'] === 'ordered' ? 'ol' : 'ul';
                $insert = "<li>{$insert}</li>";
                $html .= "<{$listType}>{$insert}</{$listType}>";
                continue;
            }
            $html .= nl2br($insert);
        }

        return $html;
    }

    
    /**
     * @Route("/pet/{petId}/peso/novo", name="clinica_novo_peso", methods={"GET", "POST"})
     */
    public function novoPeso(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);

        if ($request->isMethod('POST')) {
            // Lógica para salvar o novo registro de peso
            $this->addFlash('success', 'Peso registrado com sucesso!');
            return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
        }

        return $this->render('clinica/novo_peso.html.twig', ['pet' => $pet]);
    }

    /**
     * @Route("/pet/{petId}/documento/novo", name="clinica_novo_documento_pet", methods={"GET"})
     */
    public function novoDocumentoPet(int $petId): Response
    {
        // Redireciona para a tela de documentos, ou implementa uma lógica específica aqui
        return $this->redirectToRoute('clinica_documentos');
    }

    /**
     * @Route("/pet/{petId}/exame/novo", name="clinica_novo_exame", methods={"GET", "POST"})
     */
    public function novoExame(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        // Lógica para exames
        return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Exame']);
    }

    /**
     * @Route("/pet/{petId}/fotos/nova", name="clinica_novas_fotos", methods={"GET", "POST"})
     */
    public function novasFotos(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        // Lógica para fotos
        return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Fotos']);
    }

    /**
     * @Route("/pet/{petId}/vacina/nova", name="clinica_nova_vacina", methods={"GET", "POST"})
     */
    public function novaVacina(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();
        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        // Lógica para vacinas
        return $this->render('clinica/placeholder.html.twig', ['pet' => $pet, 'feature' => 'Vacina']);
    }

    // --- ROTAS ORIGINAIS (MANTIDAS E FUNCIONAIS) ---
    
    /**
     * @Route("/consulta/nova", name="clinica_nova_consulta", methods={"GET", "POST"})
     */
    public function novaConsulta(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $clientes = $this->getRepositorio(Cliente::class)->localizaTodosCliente($baseId);

        $petNome = $request->query->get('pet_nome');
        $dataFiltro = $request->query->get('data') ?: (new \DateTime())->format('Y-m-d');

        $consultas = $this->getRepositorio(Consulta::class)->listarConsultasDoDia($baseId, new \DateTime($dataFiltro), $petNome);

        if ($request->isMethod('POST')) {
            $consulta = new Consulta();
            $consulta->setEstabelecimentoId($baseId);
            $consulta->setClienteId((int) $request->get('cliente_id'));
            $consulta->setPetId((int) $request->get('pet_id'));
            $consulta->setData(new \DateTime($request->get('data')));
            $consulta->setHora(new \DateTime($request->get('hora')));
            $consulta->setObservacoes($request->get('observacoes'));
            $consulta->setStatus('aguardando');

            $this->getRepositorio(Consulta::class)->salvarConsulta($baseId, $consulta);
            $this->addFlash('success', 'Consulta marcada com sucesso!');
            return $this->redirectToRoute('clinica_nova_consulta');
        }

        return $this->render('clinica/nova_consulta.html.twig', [
            'clientes' => $clientes,
            'consultas' => $consultas,
        ]);
    }

    /**
     * @Route("/consulta/{id}/status/{status}", name="clinica_consulta_status", methods={"POST"})
     */
    public function atualizarStatusConsulta(int $id, string $status): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $statusPermitidos = ['aguardando', 'atendido', 'cancelado'];
        if (!in_array($status, $statusPermitidos)) {
            return $this->json(['erro' => 'Status inválido'], 400);
        }

        $this->getRepositorio(Consulta::class)->atualizarStatusConsulta($baseId, $id, $status);
        return $this->json(['success' => true]);
    }

    // /**
    //  * @Route("/receita", name="clinica_receita", methods={"GET"})
    //  */
    // public function receita(): Response
    // {
    //     $this->switchDB();
    //     return $this->render('clinica/receita.html.twig');
    // }

    // /**
    //  * @Route("/receita/pdf", name="clinica_receita_pdf", methods={"POST"})
    //  */
    // public function gerarReceitaPdf(Request $request, PdfService $pdfService): Response
    // {
    //     $this->switchDB();

    //     return $pdfService->gerarPdf(
    //         'clinica/receita_pdf_backend.html.twig',
    //         [
    //             'cabecalho' => $request->get('cabecalho', ''),
    //             'conteudo'  => $request->get('conteudo', ''),
    //             'rodape'    => $request->get('rodape', '')
    //         ],
    //         'receita-medica.pdf'
    //     );
    // }

    /**
     * @Route("/documentos", name="clinica_documentos", methods={"GET", "POST"})
     */
    public function documentos(Request $request, DocumentoModeloRepository $repoDoc): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        if ($request->isMethod('POST')) {
            $doc = new DocumentoModelo();
            $doc->setTitulo($request->get('titulo'));
            $doc->setCabecalho($request->get('cabecalho'));
            $doc->setConteudo($request->get('conteudo'));
            $doc->setRodape($request->get('rodape'));
            $doc->setCriadoEm(new \DateTime());

            $repoDoc->salvarDocumentoCompleto($baseId, $doc);

            $this->addFlash('success', 'Novo documento salvo com sucesso!');
            return $this->redirectToRoute('clinica_documentos');
        }

        $documentos = $repoDoc->listarDocumentos($baseId);

        return $this->render('clinica/documentos.html.twig', [
            'documentos' => $documentos
        ]);
    }

    /**
     * @Route("/documento/{id}/editar", name="clinica_documento_editar", methods={"GET", "POST"})
     */
    public function editarDocumento(int $id, Request $request, DocumentoModeloRepository $repoDoc): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');
        $documento = $repoDoc->buscarPorId($baseId, $id);

        if (!$documento) {
            throw $this->createNotFoundException('Documento não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $documento->setTitulo($request->get('titulo'));
            $documento->setCabecalho($request->get('cabecalho'));
            $documento->setConteudo($request->get('conteudo'));
            $documento->setRodape($request->get('rodape'));

            $repoDoc->atualizarDocumentoCompleto($baseId, $documento);

            $this->addFlash('success', 'Documento atualizado com sucesso!');
            return $this->redirectToRoute('clinica_documento_editar', ['id' => $id]);
        }

        return $this->render('clinica/documento_editar.html.twig', [
            'documento' => $documento
        ]);
    }

    /**
     * @Route("/documento/{id}/excluir", name="clinica_documento_excluir", methods={"POST"})
     */
    public function excluirDocumento(int $id, Request $request, DocumentoModeloRepository $repoDoc): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $documento = $repoDoc->buscarPorId($baseId, $id);

        if (!$documento) {
            $this->addFlash('danger', 'Documento não encontrado.');
        } else {
            $repoDoc->excluirDocumento($baseId, $id);
            $this->addFlash('success', 'Documento excluído com sucesso!');
        }

        return $this->redirectToRoute('clinica_documentos');
    }

    /**
     * @Route("/api/pets/{clienteId}", name="clinica_api_pets", methods={"GET"})
     */
    public function apiPetsPorCliente(int $clienteId): Response
    {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $pets = $this->getRepositorio(Pet::class)->buscarPetsPorCliente($baseId, $clienteId);

        return $this->json(array_map(fn ($pet) => ['id' => $pet['id'], 'nome' => $pet['nome']], $pets));
    }

    /**
     * @Route("/financeiro", name="financeiro_dashboard", methods={"GET"})
     */
    public function financeirodash(Request $request): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $repoFinanceiro = new FinanceiroRepository($this->managerRegistry);

        $hoje = new \DateTime();
        $inicioMes = (clone $hoje)->modify('first day of this month')->setTime(0, 0);
        $semanaPassada = (clone $hoje)->modify('-7 days');

        $financeiroHoje = $repoFinanceiro->findByDate($baseId, $hoje);
        $financeiroSemana = $repoFinanceiro->getRelatorioPorPeriodo($baseId, $semanaPassada, $hoje);
        $financeiroMes = $repoFinanceiro->getRelatorioPorPeriodo($baseId, $inicioMes, $hoje);

        $inicioMes = (clone $hoje)->modify('first day of this month');
        $totalReceita = $repoFinanceiro->somarPorDescricao($baseId, 'Receita', $inicioMes, $hoje);
        $totalDespesa = $repoFinanceiro->somarPorDescricao($baseId, 'Pagamento', $inicioMes, $hoje);

        $totalGeral = $totalReceita - $totalDespesa;
        $dataAtual = $request->query->get('data')
            ? new \DateTime($request->query->get('data'))
            : new \DateTime();

        return $this->render('clinica/financeirodash.html.twig', [
            'financeiro_hoje' => $financeiroHoje,
            'financeiro_semana' => $financeiroSemana,
            'financeiro_mes' => $financeiroMes,
            'total_receita' => $totalReceita,
            'total_despesa' => $totalDespesa,
            'saldo_geral' => $totalGeral,
            'dataAtual' => $dataAtual,
        ]);
    }

    /**
     * @Route("/consulta/{id}", name="clinica_ver_consulta", methods={"GET"})
     */
    public function verConsulta(int $id, ConsultaRepository $consultaRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Você precisará de um método no seu repositório para buscar uma única consulta com todos os detalhes
        $consulta = $consultaRepo->findConsultaCompletaById($baseId, $id);

        if (!$consulta) {
            throw $this->createNotFoundException('Atendimento não encontrado.');
        }

        return $this->render('clinica/ver_consulta.html.twig', [
            'consulta' => $consulta,
        ]);
    }

    /**
     * @Route("/pet/{petId}/venda/concluir", name="clinica_concluir_venda", methods={"POST"})
     */
    public function concluirVenda(Request $request, int $petId, EntityManagerInterface $entityManager): JsonResponse
    {

        $this->switchDB();
        $baseId = $this->getIdBase();

        // Pegando todos os campos do POST
        $servicoId = $request->request->get('servico_id');
        $descricao = $request->request->get('descricao');
        $valor   = (float) $request->request->get('valor');
        $data    = $request->request->get('data') ? new \DateTime($request->request->get('data')) : new \DateTime();
        $observacao = $request->request->get('observacao');
        $metodoPagamento = $request->request->get('metodo_pagamento');
        $desconto  = (float) $request->request->get('desconto', 0);

        // --- Se vier ID do serviço, busca o valor oficial no banco (anti-gambiarra)
        if ($servicoId) {
            // Use o EntityManager para obter o repositório
            $servico = $entityManager->getRepository(Servico::class)->find($servicoId);
            if (!$servico) {
                return $this->json(['status' => 'error', 'mensagem' => 'Serviço não encontrado!'], 404);
            }
            $descricao = $servico->getNome();
            $valor = (float) $servico->getValor();
        }

        // --- Valor final nunca negativo!
        $valorFinal = max(0, $valor - $desconto);

        // --- Monta descrição final (nome + observação)
        $descricaoFinal = trim($descricao . ($observacao ? ' - ' . $observacao : ''));

        if ($metodoPagamento === 'pendente') {
            // Vai pro Financeiro Pendente
            $financeiroPendente = new FinanceiroPendente();
            $financeiroPendente->setEstabelecimentoId($baseId);
            $financeiroPendente->setPetId($petId);
            $financeiroPendente->setDescricao($descricaoFinal);
            $financeiroPendente->setValor($valorFinal);
            $financeiroPendente->setData($data);
            $financeiroPendente->setStatus('pendente');
            $financeiroPendente->setOrigem('clinica');
            $financeiroPendente->setMetodoPagamento($metodoPagamento);
            
            // Persistir e salvar a entidade
            $entityManager->persist($financeiroPendente);
            $entityManager->flush();

            return $this->json([
                'status' => 'success',
                'mensagem' => 'Lançado como pendente.'
            ]);
        } else {
            // Vai pro Financeiro "Pago"
            $financeiro = new Financeiro();
            $financeiro->setEstabelecimentoId($baseId);
            $financeiro->setPetId($petId);
            $financeiro->setDescricao($descricaoFinal);
            $financeiro->setValor($valorFinal);
            $financeiro->setData($data);
            $financeiro->setOrigem('clinica');
            $financeiro->setStatus('concluido');
            // Linha removida, pois a tabela 'financeiro' não tem a coluna 'metodo_pagamento'.
            // $financeiro->setMetodoPagamento($metodoPagamento);
            
            // Persistir e salvar a entidade
            $entityManager->persist($financeiro);
            $entityManager->flush();

            return $this->json([
                'status' => 'success',
                'mensagem' => 'Pagamento registrado no financeiro!'
            ]);
        }
    }

/**
     * @Route("/internacao/{id}/ficha", name="clinica_ficha_internacao", methods={"GET"})
     */
    public function fichaInternacao(int $id, InternacaoRepository $internacaoRepo, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $internacao = $internacaoRepo->findInternacaoCompleta($baseId, $id);
        if (!$internacao) {
            throw $this->createNotFoundException('A ficha de internação não foi encontrada.');
        }

        // Busca todos os eventos da timeline
        $rows = $internacaoRepo->listarEventosPorInternacao($baseId, $id);

        $timeline = array_map(function(array $r) {
            try {
                $r['data_hora'] = !empty($r['data_hora']) ? new \DateTime($r['data_hora']) : new \DateTime();
            } catch (\Exception $e) {
                $r['data_hora'] = new \DateTime();
            }
            $r['tipo']       = (string)($r['tipo'] ?? 'internacao');
            $r['titulo']     = $r['titulo'] ?? '—';
            $r['descricao']  = $r['descricao'] ?? '';
            return $r;
        }, $rows);

        usort($timeline, fn($a,$b) => $b['data_hora'] <=> $a['data_hora']);

        // Busca todos os medicamentos para o modal de prescrição
        $medicamentos = $em->getRepository(Medicamento::class)->findAll();
        
        // CORREÇÃO: Busca as prescrições como objetos completos
        $prescricoes = $em->getRepository(InternacaoPrescricao::class)->findBy(['internacaoId' => $id]);

        return $this->render('clinica/ficha_internacao.html.twig', [
            'internacao'    => $internacao,
            'timeline'      => $timeline,
            'medicamentos'  => $medicamentos,
            'prescricoes'   => $prescricoes,
        ]);
    }


    /**
     * @Route("/internacao/{id}/alta", name="clinica_internacao_alta", methods={"POST"})
     */
    public function altaInternacao(int $id, Request $request, InternacaoRepository $internacaoRepo): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // (Opcional) validar CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('alta_internacao_'.$id, $token)) {
            return $this->json(['ok' => false, 'msg' => 'Token inválido.'], 400);
        }

        // Busca dados da internação para registrar evento
        $internacao = $internacaoRepo->buscarPorId($baseId, $id);
        if (!$internacao) {
            return $this->json(['ok' => false, 'msg' => 'Internação não encontrada.'], 404);
        }

        // Finaliza
        $internacaoRepo->finalizarInternacao($baseId, $id);

        // Evento de alta na timeline
        $internacaoRepo->inserirEvento(
            $baseId,
            $id,
            (int)$internacao['pet_id'],
            'Alta',
            'Alta concedida',
            'Internação finalizada pelo sistema',
            new \DateTime()
        );

        return $this->json(['ok' => true]);
    }

    /**
     * @Route("/internacao/{id}/prescricao/nova", name="clinica_internacao_prescricao_nova", methods={"GET","POST"})
     */
    public function novaPrescricao(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        InternacaoRepository $internacaoRepo
    ): Response {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // 🚑 Se alguém abrir a rota direto no navegador (GET), redireciona pra ficha
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('clinica_ficha_internacao', ['id' => $id]);
        }

        try {
            $internacao = $em->getRepository(Internacao::class)->find($id);
            if (!$internacao) {
                return $this->json(['ok' => false, 'msg' => 'Internação não encontrada.'], 404);
            }

            $medicamentoId = (int) $request->request->get('medicamento_id');
            $dose = trim((string) $request->request->get('dose'));
            $frequenciaHoras = (int) $request->request->get('frequencia_horas');
            $duracaoDias = (int) $request->request->get('duracao_dias');
            $dataHoraPrimeiraDose = $request->request->get('data_hora_primeira_dose');

            if (!$medicamentoId || empty($dose) || $frequenciaHoras <= 0 || $duracaoDias <= 0 || empty($dataHoraPrimeiraDose)) {
                return $this->json(['ok' => false, 'msg' => 'Campos obrigatórios faltando.'], 400);
            }

            $medicamento = $em->getRepository(Medicamento::class)->find($medicamentoId);
            if (!$medicamento) {
                return $this->json(['ok' => false, 'msg' => 'Medicamento não encontrado.'], 404);
            }

            // --- Cria a prescrição ---
            $prescricao = new InternacaoPrescricao();
            $prescricao->setInternacaoId($internacao->getId());
            $prescricao->setMedicamento($medicamento);
            $prescricao->setDescricao($medicamento->getNome());
            $prescricao->setDose($dose);
            $prescricao->setFrequencia(sprintf('a cada %d horas por %d dias', $frequenciaHoras, $duracaoDias));
            $prescricao->setDataHora(new \DateTime($dataHoraPrimeiraDose));
            $prescricao->setCriadoEm(new \DateTime());

            $em->persist($prescricao);
            $em->flush();

            // --- Gera eventos na timeline (mas sem quebrar se falhar) ---
            $petId = method_exists($internacao, 'getPetId')
                ? $internacao->getPetId()
                : ($internacao->getPet() ? $internacao->getPet()->getId() : null);

            if ($petId) {
                try {
                    $numDoses = ($duracaoDias * 24) / $frequenciaHoras;

                    for ($i = 0; $i < $numDoses; $i++) {
                        $dataDose = (new \DateTime($dataHoraPrimeiraDose))->modify('+' . ($i * $frequenciaHoras) . ' hours');

                        $descricaoEvento = sprintf(
                            "Medicamento: %s | Dose: %s | Frequência: %s",
                            $medicamento->getNome(),
                            $dose,
                            $prescricao->getFrequencia()
                        );

                        $internacaoRepo->inserirEvento(
                            $baseId,
                            $id,
                            $petId,
                            'medicacao',
                            'Dose de medicação agendada',
                            $descricaoEvento,
                            $dataDose
                        );
                    }
                } catch (\Exception $e) {
                    // Loga mas não quebra a resposta
                    $this->logger->error('Erro ao inserir eventos da prescrição', [
                        'exception' => $e,
                        'internacao_id' => $id,
                        'pet_id' => $petId
                    ]);
                }
            }

            return $this->json(['ok' => true, 'msg' => 'Prescrição salva com sucesso!']);

        } catch (\Exception $e) {
            return $this->json([
                'ok' => false,
                'msg' => 'Erro interno: '.$e->getMessage()
            ], 500);
        }
    }

    
    /**
     * @Route("/internacao/prescricao/{eventoId}/executar", name="clinica_internacao_prescricao_executar", methods={"POST"})
     */
    public function executarPrescricao(int $eventoId, Request $request, EntityManagerInterface $em, InternacaoRepository $internacaoRepo): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Apenas marca o evento de agendamento como executado
        try {
            $internacaoRepo->marcarMedicacaoComoExecutada($baseId, $eventoId);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'msg' => 'Erro ao registrar a administração: ' . $e->getMessage()]);
        }

        return $this->json(['ok' => true, 'msg' => 'Medicação registrada com sucesso!']);
    }

    /**
     * @Route("/medicamentos", name="clinica_medicamentos", methods={"GET","POST"})
     */
    public function medicamentos(Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        // Cadastro de novo medicamento
        if ($request->isMethod('POST')) {
            $medicamento = new Medicamento();
            $medicamento->setNome($request->get('nome'));
            $medicamento->setVia($request->get('via'));
            $medicamento->setConcentracao($request->get('concentracao'));

            $em->persist($medicamento);
            $em->flush();

            $this->addFlash('success', 'Medicamento cadastrado com sucesso!');
            return $this->redirectToRoute('clinica_medicamentos');
        }

        // Listagem de medicamentos
        $medicamentos = $em->getRepository(Medicamento::class)->findAll();

        return $this->render('clinica/medicamentos.html.twig', [
            'medicamentos' => $medicamentos
        ]);
    }

    /**
     * @Route("/medicamentos/{id}/editar", name="clinica_medicamento_editar", methods={"GET","POST"})
     */
    public function editarMedicamento(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->switchDB();

        $medicamento = $em->getRepository(Medicamento::class)->find($id);
        if (!$medicamento) {
            throw $this->createNotFoundException('Medicamento não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $medicamento->setNome($request->get('nome'));
            $medicamento->setVia($request->get('via'));
            $medicamento->setConcentracao($request->get('concentracao'));

            $em->flush();

            $this->addFlash('success', 'Medicamento atualizado!');
            return $this->redirectToRoute('clinica_medicamentos');
        }

        return $this->render('clinica/medicamento_editar.html.twig', [
            'medicamento' => $medicamento
        ]);
    }

    /**
     * @Route("/medicamentos/{id}/excluir", name="clinica_medicamento_excluir", methods={"POST"})
     */
    public function excluirMedicamento(int $id, EntityManagerInterface $em): Response
    {
        $this->switchDB();

        $medicamento = $em->getRepository(Medicamento::class)->find($id);
        if ($medicamento) {
            $em->remove($medicamento);
            $em->flush();
            $this->addFlash('success', 'Medicamento excluído.');
        }

        return $this->redirectToRoute('clinica_medicamentos');
    }

    /**
     * @Route("/internacao/medicamento/novo", name="clinica_internacao_medicamento_novo", methods={"POST"})
     */
    public function novoMedicamentoViaInternacao(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $nome = trim((string) $request->request->get('nome'));
        $via = trim((string) $request->request->get('via'));
        $concentracao = trim((string) $request->request->get('concentracao'));

        if (empty($nome)) {
            return $this->json(['ok' => false, 'msg' => 'O nome do medicamento é obrigatório.']);
        }

        try {
            $medicamento = new Medicamento();
            $medicamento->setNome($nome);
            $medicamento->setVia($via);
            $medicamento->setConcentracao($concentracao);

            $em->persist($medicamento);
            $em->flush();
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'msg' => 'Erro ao salvar o medicamento: ' . $e->getMessage()]);
        }

        return $this->json(['ok' => true, 'msg' => 'Medicamento cadastrado com sucesso!']);
    }

        /**
     * @Route("/pet/{petId}/venda/{id}/editar", name="clinica_editar_venda", methods={"POST"})
     */
    public function editarVenda(
        Request $request,
        int $petId,
        int $id,
        FinanceiroRepository $financeiroRepo
    ): Response {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $financeiro = $financeiroRepo->findFinanceiro($baseId, $id);
        if (!$financeiro) {
            throw $this->createNotFoundException('Venda não encontrada.');
        }

        $financeiro->setDescricao($request->request->get('descricao', $financeiro->getDescricao()));
        $financeiro->setValor((float) $request->request->get('valor', $financeiro->getValor()));
        $financeiro->setData(new \DateTime($request->request->get('data', $financeiro->getData()->format('Y-m-d'))));

        $financeiroRepo->update($baseId, $financeiro);

        $this->addFlash('success', 'Venda editada com sucesso!');
        return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
    }

    /**
     * @Route("/pet/{petId}/venda/{id}/inativar", name="clinica_inativar_venda", methods={"POST"})
     */
    public function inativarVenda(
        int $petId,
        int $id,
        FinanceiroRepository $financeiroRepo
    ): Response {
        $this->switchDB();
        $baseId = $this->session->get('userId');

        $financeiro = $financeiroRepo->findFinanceiro($baseId, $id);
        if (!$financeiro) {
            throw $this->createNotFoundException('Venda não encontrada.');
        }

        $financeiroRepo->inativar($baseId, $id);

        $this->addFlash('success', 'Venda inativada com sucesso!');
        return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
    }


}