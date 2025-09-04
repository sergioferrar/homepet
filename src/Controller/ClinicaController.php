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
use App\Repository\BoxRepository;
use App\Entity\Financeiro;
use App\Entity\Servico;

/**
 * @Route("/clinica")
 */
class ClinicaController extends DefaultController
{
    /**
     * @Route("/dashboard", name="clinica_dashboard", methods={"GET"})
     */
    public function dashboard(): Response
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

        $vacinasVencidas = method_exists($repoPet, 'listarVacinasPendentes') ? $repoPet->listarVacinasPendentes($baseId) : [];
        $vacinasProgramadas = method_exists($repoPet, 'listarVacinasProgramadas') ? $repoPet->listarVacinasProgramadas($baseId) : [];

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
            'documentos' => $documentos,
            'financeiro' => $financeiro,
            'financeiroPendente' => $financeiroPendente,
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
     * @Route("/pet/{petId}/receita/nova", name="clinica_nova_receita", methods={"POST"})
     */
    public function novaReceita(Request $request, int $petId): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $pet = $this->getRepositorio(Pet::class)->findPetById($baseId, $petId);
        if (!$pet) {
            throw $this->createNotFoundException('Pet não encontrado.');
        }

        $receita = new \App\Entity\Receita();
        $receita->setEstabelecimentoId($baseId);
        $receita->setPetId($petId);
        $receita->setData(new \DateTime());
        $receita->setCabecalho($request->get('cabecalho_delta'));
        $receita->setConteudo($request->get('conteudo_delta'));
        $receita->setRodape($request->get('rodape_delta'));
        $receita->setResumo('Receita registrada manualmente');
        $conteudo = json_decode($request->get('cabecalho_delta'));
        // dd($conteudo->ops[0]->insert);
        $this->getRepositorio(\App\Entity\Receita::class)->salvar($receita);

        $gerarPDF = new \App\Service\GeradorpdfService($this->tempDirManager, $this->requestStack);

        $gerarPDF->configuracaoPagina('A4', 10, 10, 35, 6, 5, 3);##$orientacao, $margEsquerda, $margDireita, $margTop do conteudo, $margBottom, $margCabecalho, $margRodape
        $gerarPDF->setNomeArquivo('Receita_manual');
        $gerarPDF->setRodape("Gerado em: {DATE j/m/Y H:i}   Receita Manual - System Home Pet: Seu CRM para clínicas e pet shops");

        $gerarPDF->montaCabecalhoPadrao('Receita Veterinária');
        $gerarPDF->addPagina('P');
        $gerarPDF->conteudo($conteudo->ops[0]->insert);
        $gerarPDF->gerar();

        // $this->addFlash('success', 'Receita salva com sucesso!');

        // return $this->redirectToRoute('clinica_detalhes_pet', ['id' => $petId]);
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

    /**
     * @Route("/receita", name="clinica_receita", methods={"GET"})
     */
    public function receita(): Response
    {
        $this->switchDB();
        return $this->render('clinica/receita.html.twig');
    }

    /**
     * @Route("/receita/pdf", name="clinica_receita_pdf", methods={"POST"})
     */
    public function gerarReceitaPdf(Request $request, PdfService $pdfService): Response
    {
        $this->switchDB();

        return $pdfService->gerarPdf(
            'clinica/receita_pdf_backend.html.twig',
            [
                'cabecalho' => $request->get('cabecalho', ''),
                'conteudo'  => $request->get('conteudo', ''),
                'rodape'    => $request->get('rodape', '')
            ],
            'receita-medica.pdf'
        );
    }

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
        $valor     = (float) $request->request->get('valor');
        $data      = $request->request->get('data') ? new \DateTime($request->request->get('data')) : new \DateTime();
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
    public function fichaInternacao(int $id, InternacaoRepository $internacaoRepo): Response
    {
        $this->switchDB();
        $baseId = $this->getIdBase();

        $internacao = $internacaoRepo->findInternacaoCompleta($baseId, $id);
        if (!$internacao) {
            throw $this->createNotFoundException('A ficha de internação não foi encontrada.');
        }

        // <<< SOMENTE EVENTOS DA INTERNAÇÃO >>>
        $rows = $internacaoRepo->listarEventosPorInternacao($baseId, $id);

        // Normaliza p/ o Twig
        $timeline = array_map(function(array $r) {
            try {
                $r['data_hora'] = !empty($r['data_hora']) ? new \DateTime($r['data_hora']) : new \DateTime();
            } catch (\Exception $e) {
                $r['data_hora'] = new \DateTime();
            }
            $r['tipo']      = (string)($r['tipo'] ?? 'internacao');
            $r['titulo']    = $r['titulo'] ?? '—';
            $r['descricao'] = $r['descricao'] ?? '';
            return $r;
        }, $rows);

        // Ordem desc por data/hora (e id já vem ordenado no repo)
        usort($timeline, fn($a,$b) => $b['data_hora'] <=> $a['data_hora']);

        return $this->render('clinica/ficha_internacao.html.twig', [
            'internacao' => $internacao,
            'timeline'   => $timeline,
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
}