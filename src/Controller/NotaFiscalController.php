<?php

namespace App\Controller;

use App\Entity\Cliente;
use App\Entity\Config;
use App\Entity\NotaFiscal;
use App\Entity\Venda;
use App\Service\NotaFiscal\AsaasNotaFiscalService;
use App\Service\NotaFiscal\NotaFiscalException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("dashboard/nota-fiscal")
 */
class NotaFiscalController extends DefaultController
{
    // ─────────────────────────────────────────────────────────────────
    // LISTAGEM
    // ─────────────────────────────────────────────────────────────────

    /**
     * @Route("", name="nf_lista")
     */
    public function lista(AsaasNotaFiscalService $asaas): Response
    {
        $eid   = $this->getIdBase();
        $notas = $this->em->getRepository(NotaFiscal::class)->findByEstabelecimento($eid);
        $cfg   = $asaas->getConfig($eid);
        $totalMes = $this->em->getRepository(NotaFiscal::class)->countAutorizadasNoMes($eid);

        return $this->render('nota_fiscal/lista.html.twig', [
            'notas'          => $notas,
            'cfg'            => $cfg,
            'total_mes'      => $totalMes,
            'asaas_configurado' => (bool) $cfg['api_key'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // EMITIR A PARTIR DE UMA VENDA
    // ─────────────────────────────────────────────────────────────────

    /**
     * @Route("/emitir-venda/{vendaId}", name="nf_emitir_venda",
     *        methods={"POST"}, requirements={"vendaId"="\d+"})
     */
    public function emitirPorVenda(int $vendaId, AsaasNotaFiscalService $asaas): Response
    {
        $eid  = $this->getIdBase();
        $venda = $this->em->getRepository(Venda::class)->find($vendaId);

        if (!$venda || $venda->getEstabelecimentoId() !== $eid) {
            $this->addFlash('error', 'Venda não encontrada.');
            return $this->redirectToRoute('nf_lista');
        }

        try {
            $resultado = $asaas->emitir($venda);
            $this->addFlash('success', '✅ Nota fiscal agendada com sucesso! ID Asaas: ' . $resultado['asaas_invoice_id']);
        } catch (NotaFiscalException $e) {
            $this->addFlash('error', 'Erro ao emitir nota: ' . $e->getMessage());
        }

        return $this->redirectToRoute('nf_lista');
    }

    // ─────────────────────────────────────────────────────────────────
    // EMITIR NOTA AVULSA
    // ─────────────────────────────────────────────────────────────────

    /**
     * @Route("/avulsa", name="nf_avulsa_form", methods={"GET"})
     */
    public function avulsaForm(AsaasNotaFiscalService $asaas): Response
    {
        $eid     = $this->getIdBase();
        $clientes = $this->em->getRepository(Cliente::class)->findBy(['estabelecimentoId' => $eid], ['nome' => 'ASC']);
        $servicos = $asaas->listarServicosMunicipais($eid);

        return $this->render('nota_fiscal/avulsa.html.twig', [
            'clientes' => $clientes,
            'servicos' => $servicos,
        ]);
    }

    /**
     * @Route("/avulsa", name="nf_avulsa_store", methods={"POST"})
     */
    public function avulsaStore(Request $request, AsaasNotaFiscalService $asaas): Response
    {
        $eid = $this->getIdBase();

        // Se escolheu um cliente cadastrado, pega os dados dele
        $clienteId = $request->get('cliente_id');
        $dadosCliente = [];

        if ($clienteId) {
            $cliente = $this->em->getRepository(Cliente::class)->find((int) $clienteId);
            if ($cliente) {
                $dadosCliente = [
                    'cliente_nome'      => $cliente->getNome(),
                    'cliente_cpf_cnpj'  => $cliente->getCpf(),
                    'cliente_email'     => $cliente->getEmail(),
                    'cliente_telefone'  => $cliente->getTelefone(),
                    'cliente_cep'       => (string) $cliente->getCep(),
                    'cliente_numero'    => (string) $cliente->getNumero(),
                ];
            }
        } else {
            $dadosCliente = [
                'cliente_nome'     => $request->get('cliente_nome'),
                'cliente_cpf_cnpj' => $request->get('cliente_cpf_cnpj'),
                'cliente_email'    => $request->get('cliente_email'),
                'cliente_telefone' => $request->get('cliente_telefone'),
                'cliente_cep'      => $request->get('cliente_cep'),
                'cliente_numero'   => $request->get('cliente_numero'),
            ];
        }

        $dados = array_merge($dadosCliente, [
            'descricao_servico' => $request->get('descricao_servico'),
            'valor'             => str_replace(',', '.', $request->get('valor')),
            'deducoes'          => (float) str_replace(',', '.', $request->get('deducoes', '0')),
            'observacoes'       => $request->get('observacoes'),
            'data_emissao'      => $request->get('data_emissao') ?: date('Y-m-d'),
        ]);

        try {
            $resultado = $asaas->emitirAvulsa($eid, $dados);
            $this->addFlash('success', '✅ Nota fiscal avulsa agendada! ID: ' . $resultado['asaas_invoice_id']);
            return $this->redirectToRoute('nf_lista');
        } catch (NotaFiscalException $e) {
            $this->addFlash('error', 'Erro: ' . $e->getMessage());
            return $this->redirectToRoute('nf_avulsa_form');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AUTORIZAR (forçar emissão imediata)
    // ─────────────────────────────────────────────────────────────────

    /**
     * @Route("/autorizar/{id}", name="nf_autorizar",
     *        methods={"POST"}, requirements={"id"="\d+"})
     */
    public function autorizar(int $id, AsaasNotaFiscalService $asaas): Response
    {
        $eid  = $this->getIdBase();
        $nota = $this->em->getRepository(NotaFiscal::class)->find($id);

        if (!$nota || $nota->getEstabelecimentoId() !== $eid) {
            $this->addFlash('error', 'Nota não encontrada.');
            return $this->redirectToRoute('nf_lista');
        }

        try {
            $asaas->autorizar($nota->getAsaasInvoiceId(), $eid);
            $this->addFlash('success', 'Emissão solicitada. A nota será processada em até 15 minutos.');
        } catch (NotaFiscalException $e) {
            $this->addFlash('error', 'Erro ao autorizar: ' . $e->getMessage());
        }

        return $this->redirectToRoute('nf_lista');
    }

    // ─────────────────────────────────────────────────────────────────
    // CANCELAR
    // ─────────────────────────────────────────────────────────────────

    /**
     * @Route("/cancelar/{id}", name="nf_cancelar",
     *        methods={"POST"}, requirements={"id"="\d+"})
     */
    public function cancelar(int $id, Request $request, AsaasNotaFiscalService $asaas): Response
    {
        $eid  = $this->getIdBase();
        $nota = $this->em->getRepository(NotaFiscal::class)->find($id);

        if (!$nota || $nota->getEstabelecimentoId() !== $eid) {
            $this->addFlash('error', 'Nota não encontrada.');
            return $this->redirectToRoute('nf_lista');
        }

        try {
            $motivo = $request->get('motivo', 'Cancelado pelo administrador.');
            $asaas->cancelar($nota->getAsaasInvoiceId(), $motivo);
            $this->addFlash('success', 'Nota fiscal cancelada com sucesso.');
        } catch (NotaFiscalException $e) {
            $this->addFlash('error', 'Erro ao cancelar: ' . $e->getMessage());
        }

        return $this->redirectToRoute('nf_lista');
    }

    // ─────────────────────────────────────────────────────────────────
    // SINCRONIZAR STATUS
    // ─────────────────────────────────────────────────────────────────

    /**
     * @Route("/sync/{id}", name="nf_sync",
     *        methods={"POST"}, requirements={"id"="\d+"})
     */
    public function sync(int $id, AsaasNotaFiscalService $asaas): JsonResponse
    {
        $eid  = $this->getIdBase();
        $nota = $this->em->getRepository(NotaFiscal::class)->find($id);

        if (!$nota || $nota->getEstabelecimentoId() !== $eid) {
            return $this->json(['ok' => false, 'msg' => 'Nota não encontrada.'], 404);
        }

        try {
            $asaas->consultar($nota->getAsaasInvoiceId());
            return $this->json(['ok' => true, 'status' => $nota->getStatus(), 'label' => $nota->getStatusLabel()]);
        } catch (NotaFiscalException $e) {
            return $this->json(['ok' => false, 'msg' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // WEBHOOK (Asaas → HomePet)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Endpoint público — sem autenticação de sessão, usa token de segurança.
     *
     * @Route("/webhook", name="nf_webhook", methods={"POST"})
     */
    public function webhook(Request $request, AsaasNotaFiscalService $asaas): JsonResponse
    {
        // Valida token de segurança enviado pelo Asaas no header
        $tokenEnviado  = $request->headers->get('asaas-access-token');
        $tokenEsperado = $_ENV['ASAAS_WEBHOOK_TOKEN'] ?? null;

        if ($tokenEsperado && $tokenEnviado !== $tokenEsperado) {
            return new JsonResponse(['ok' => false, 'msg' => 'Unauthorized'], 401);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $asaas->processarWebhook($payload);
        } catch (\Exception $e) {
            $this->logger->error('Erro no webhook NF: ' . $e->getMessage());
        }

        return new JsonResponse(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────────────────
    // CONFIGURAÇÃO ASAAS (aba dentro de Settings)
    // ─────────────────────────────────────────────────────────────────

    /**
     * @Route("/configurar", name="nf_configurar", methods={"GET"})
     */
    public function configurar(AsaasNotaFiscalService $asaas): Response
    {
        $eid  = $this->getIdBase();
        $cfg  = $asaas->getConfig($eid);

        // Serviços municipais para o select (lista ao abrir a tela)
        $servicos = $asaas->listarServicosMunicipais($eid);

        return $this->render('nota_fiscal/configurar.html.twig', [
            'cfg'      => $cfg,
            'servicos' => $servicos,
        ]);
    }

    /**
     * @Route("/configurar", name="nf_configurar_save", methods={"POST"})
     */
    public function configurarSave(Request $request, AsaasNotaFiscalService $asaas): Response
    {
        $eid = $this->getIdBase();

        $campos = [
            'asaas_api_key'              => $request->get('asaas_api_key'),
            'asaas_environment'          => $request->get('asaas_environment', 'sandbox'),
            'asaas_municipal_service_id'   => $request->get('asaas_municipal_service_id'),
            'asaas_municipal_service_code' => $request->get('asaas_municipal_service_code'),
            'asaas_municipal_service_name' => $request->get('asaas_municipal_service_name'),
            'asaas_iss'        => $request->get('asaas_iss', '0'),
            'asaas_cofins'     => $request->get('asaas_cofins', '0'),
            'asaas_csll'       => $request->get('asaas_csll', '0'),
            'asaas_pis'        => $request->get('asaas_pis', '0'),
            'asaas_ir'         => $request->get('asaas_ir', '0'),
            'asaas_inss'       => $request->get('asaas_inss', '0'),
            'asaas_retain_iss' => $request->get('asaas_retain_iss', '0'),
        ];

        foreach ($campos as $chave => $valor) {
            $config = $this->em->getRepository(Config::class)->findOneBy([
                'estabelecimento_id' => $eid,
                'chave'              => $chave,
            ]);

            if (!$config) {
                $config = new Config();
                $config->setEstabelecimentoId($eid);
                $config->setChave($chave);
                $config->setTipo('asaas');
                $config->setObservacao('Configuração Asaas NFS-e');
                $this->em->persist($config);
            }

            $config->setValor($valor);
        }

        $this->em->flush();
        $this->addFlash('success', 'Configurações Asaas salvas com sucesso!');

        return $this->redirectToRoute('nf_configurar');
    }

    // ─────────────────────────────────────────────────────────────────
    // AJAX: busca serviços municipais em tempo real
    // ─────────────────────────────────────────────────────────────────

    /**
     * @Route("/servicos-municipais", name="nf_servicos_municipais", methods={"GET"})
     */
    public function servicosMunicipais(Request $request, AsaasNotaFiscalService $asaas): JsonResponse
    {
        $eid    = $this->getIdBase();
        $filtro = $request->get('q');
        $dados  = $asaas->listarServicosMunicipais($eid, $filtro);

        return $this->json($dados);
    }
}
