<?php

namespace App\Service\NotaFiscal;

use App\Entity\Cliente;
use App\Entity\Config;
use App\Entity\Estabelecimento;
use App\Entity\NotaFiscal;
use App\Entity\Venda;
use App\Entity\VendaItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use App\Service\DynamicConnectionManager;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Integração HomePet ↔ Asaas para emissão de NFS-e (Nota Fiscal de Serviço Eletrônica).
 *
 * Fluxo completo:
 *  1. Busca/cria o cliente no Asaas via POST /v3/customers
 *  2. Agenda a nota via POST /v3/invoices
 *  3. Opcionalmente força emissão imediata via POST /v3/invoices/{id}/authorize
 *  4. Cancela via PUT /v3/invoices/{id}/cancel
 *  5. Consulta via GET /v3/invoices/{id}
 *
 * Chaves de API por estabelecimento ficam em Config (tipo='asaas'):
 *   - asaas_api_key      → access_token
 *   - asaas_environment  → 'sandbox' | 'production'
 *   - asaas_municipal_service_id   → ID do serviço municipal no Asaas
 *   - asaas_municipal_service_code → Código (fallback quando não há ID)
 *   - asaas_municipal_service_name → Nome do serviço
 *   - asaas_iss          → alíquota ISS (%)
 *   - asaas_retain_iss   → '1' ou '0'
 *   - asaas_cofins       → alíquota COFINS
 *   - asaas_csll         → alíquota CSLL
 *   - asaas_pis          → alíquota PIS
 *   - asaas_ir           → alíquota IR
 *   - asaas_inss         → alíquota INSS
 *
 * Fallback global via .env:
 *   ASAAS_API_KEY, ASAAS_ENVIRONMENT
 */
class AsaasNotaFiscalService implements NotaFiscalServiceInterface
{
    private const URL_SANDBOX    = 'https://sandbox.asaas.com/api/v3';
    private const URL_PRODUCTION = 'https://api.asaas.com/v3';

    private HttpClientInterface $http;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private ManagerRegistry $managerRegistry;

    public function __construct(
        HttpClientInterface $http,
        EntityManagerInterface $em,
        LoggerInterface $logger,
        ManagerRegistry $managerRegistry
    ) {
        $this->http            = $http;
        $this->em              = $em;
        $this->logger          = $logger;
        $this->managerRegistry = $managerRegistry;
    }

    private function switchToTenant(string $dbName): void
    {
        (new DynamicConnectionManager($this->managerRegistry))->switchDatabase($dbName, $dbName);
    }

    private function switchToPrincipal(): void
    {
        (new DynamicConnectionManager($this->managerRegistry))->restoreOriginal();
    }

    // ═══════════════════════════════════════════════════════════════
    // INTERFACE PÚBLICA
    // ═══════════════════════════════════════════════════════════════

    /**
     * Emite nota fiscal para uma venda.
     * Busca os dados do cliente e dos itens automaticamente.
     */
    public function emitir(Venda $venda): array
    {
        $eid = $venda->getEstabelecimentoId();
        $cfg = $this->getConfig($eid);

        if (!$cfg['api_key']) {
            throw new NotaFiscalException(
                'Chave de API Asaas não configurada para este estabelecimento.',
                ['estabelecimento_id' => $eid]
            );
        }

        // 1. Resolve cliente e itens — ambos no banco tenant
        $tenantDb = 'homepet_' . $venda->getEstabelecimentoId();
        $this->switchToTenant($tenantDb);
        $cliente = $this->resolverCliente($venda, $cfg);
        $itens   = $this->em->getRepository(VendaItem::class)->findBy(['vendaId' => $venda->getId()]);

        // Volta ao banco principal antes de chamar a API e persistir
        $this->switchToPrincipal();
        $asaasCustomer = $this->sincronizarClienteAsaas($cliente, $cfg);
        $descricaoServico = $this->montarDescricaoServico($venda, $itens);

        // 3. Monta payload da nota
        $payload = $this->montarPayloadNota($venda, $asaasCustomer['id'], $descricaoServico, $cfg);

        // 4. Chama API Asaas
        $response = $this->post($cfg, '/v3/invoices', $payload);

        if (!isset($response['id'])) {
            $erro = $response['errors'][0]['description'] ?? json_encode($response);
            throw new NotaFiscalException('Erro ao agendar nota no Asaas: ' . $erro, $response);
        }

        // 5. Persiste registro local
        $nota = $this->persistirNota($venda, $cliente, $asaasCustomer['id'], $response, $cfg);

        $this->logger->info('NFS-e agendada com sucesso.', [
            'venda_id'        => $venda->getId(),
            'asaas_invoice_id'=> $response['id'],
            'status'          => $response['status'],
        ]);

        return [
            'sucesso'          => true,
            'asaas_invoice_id' => $response['id'],
            'status'           => $response['status'],
            'nota_id'          => $nota->getId(),
            'numero'           => $response['number'] ?? null,
            'pdf_url'          => $response['pdfUrl'] ?? null,
        ];
    }

    /**
     * Emite nota avulsa (sem venda vinculada) passando dados manualmente.
     */
    public function emitirAvulsa(int $estabelecimentoId, array $dados): array
    {
        $cfg = $this->getConfig($estabelecimentoId);

        if (!$cfg['api_key']) {
            throw new NotaFiscalException('Chave de API Asaas não configurada.');
        }

        // Cria ou busca cliente no Asaas
        $asaasCustomer = $this->criarOuBuscarClienteAsaas([
            'name'     => $dados['cliente_nome'],
            'cpfCnpj'  => preg_replace('/\D/', '', $dados['cliente_cpf_cnpj'] ?? ''),
            'email'    => $dados['cliente_email'] ?? null,
            'phone'    => $dados['cliente_telefone'] ?? null,
            'postalCode'   => $dados['cliente_cep'] ?? null,
            'addressNumber'=> $dados['cliente_numero'] ?? null,
        ], $cfg);

        $payload = array_filter([
            'customer'           => $asaasCustomer['id'],
            'serviceDescription' => $dados['descricao_servico'],
            'observations'       => $dados['observacoes'] ?? null,
            'value'              => (float) $dados['valor'],
            'deductions'         => (float) ($dados['deducoes'] ?? 0),
            'effectiveDate'      => $dados['data_emissao'] ?? date('Y-m-d'),
            'municipalServiceId'   => $cfg['municipal_service_id'] ?: null,
            'municipalServiceCode' => !$cfg['municipal_service_id'] ? $cfg['municipal_service_code'] : null,
            'municipalServiceName' => $cfg['municipal_service_name'] ?? null,
            'taxes'              => $this->montarImpostos($cfg),
        ]);

        $response = $this->post($cfg, '/v3/invoices', $payload);

        if (!isset($response['id'])) {
            $erro = $response['errors'][0]['description'] ?? json_encode($response);
            throw new NotaFiscalException('Erro ao emitir nota avulsa: ' . $erro, $response);
        }

        // Persiste localmente
        $nota = new NotaFiscal();
        $nota->setEstabelecimentoId($estabelecimentoId);
        $nota->setOrigem('avulsa');
        $nota->setClienteNome($dados['cliente_nome']);
        $nota->setClienteCpfCnpj($dados['cliente_cpf_cnpj'] ?? null);
        $nota->setAsaasCustomerId($asaasCustomer['id']);
        $nota->setAsaasInvoiceId($response['id']);
        $nota->setValor((string) $dados['valor']);
        $nota->setDescricaoServico($dados['descricao_servico']);
        $nota->setStatus($response['status'] ?? NotaFiscal::STATUS_AGENDADA);
        $nota->setDataEmissao(new \DateTime($dados['data_emissao'] ?? 'now'));
        $nota->setMunicipalServiceId($cfg['municipal_service_id'] ?? null);
        $nota->setMunicipalServiceCode($cfg['municipal_service_code'] ?? null);
        $nota->setMunicipalServiceName($cfg['municipal_service_name'] ?? null);
        $nota->setImpostos($this->montarImpostos($cfg));
        $this->em->persist($nota);
        $this->em->flush();

        return [
            'sucesso'          => true,
            'asaas_invoice_id' => $response['id'],
            'status'           => $response['status'],
            'nota_id'          => $nota->getId(),
        ];
    }

    /**
     * Força emissão imediata de uma nota agendada.
     */
    public function autorizar(string $asaasInvoiceId, int $estabelecimentoId): array
    {
        $cfg      = $this->getConfig($estabelecimentoId);
        $response = $this->post($cfg, "/v3/invoices/{$asaasInvoiceId}/authorize", []);

        $nota = $this->em->getRepository(NotaFiscal::class)->findByAsaasId($asaasInvoiceId);
        if ($nota) {
            $nota->setStatus($response['status'] ?? NotaFiscal::STATUS_AUTORIZADA);
            $nota->setNumeroNota($response['number'] ?? null);
            $nota->setPdfUrl($response['pdfUrl'] ?? null);
            $nota->setXmlUrl($response['xmlUrl'] ?? null);
            $nota->setRpsNumero($response['rpsNumber'] ?? null);
            $nota->setAtualizadoEm(new \DateTime());
            $this->em->flush();
        }

        return ['sucesso' => true, 'status' => $response['status'] ?? null];
    }

    /** Cancela nota fiscal no Asaas */
    public function cancelar(string $asaasInvoiceId, string $motivo = 'Cancelado pelo sistema'): bool
    {
        // O Asaas não exige motivo na rota de cancelamento — apenas o POST no endpoint
        $nota = $this->em->getRepository(NotaFiscal::class)->findByAsaasId($asaasInvoiceId);
        if (!$nota) {
            throw new NotaFiscalException('Nota não encontrada localmente: ' . $asaasInvoiceId);
        }

        $cfg = $this->getConfig($nota->getEstabelecimentoId());
        $response = $this->post($cfg, "/v3/invoices/{$asaasInvoiceId}/cancel", []);

        $nota->setStatus(NotaFiscal::STATUS_CANCELADA);
        $nota->setObservacoes(($nota->getObservacoes() ?? '') . "\nCancelado: $motivo");
        $nota->setAtualizadoEm(new \DateTime());
        $this->em->flush();

        return true;
    }

    /** Consulta status da nota no Asaas e sincroniza localmente */
    public function consultar(string $asaasInvoiceId): array
    {
        $nota = $this->em->getRepository(NotaFiscal::class)->findByAsaasId($asaasInvoiceId);
        if (!$nota) {
            throw new NotaFiscalException('Nota não encontrada: ' . $asaasInvoiceId);
        }

        $cfg      = $this->getConfig($nota->getEstabelecimentoId());
        $response = $this->get($cfg, "/v3/invoices/{$asaasInvoiceId}");

        // Sincroniza campos
        $nota->setStatus($response['status'] ?? $nota->getStatus());
        $nota->setNumeroNota($response['number'] ?? $nota->getNumeroNota());
        $nota->setPdfUrl($response['pdfUrl'] ?? $nota->getPdfUrl());
        $nota->setXmlUrl($response['xmlUrl'] ?? $nota->getXmlUrl());
        $nota->setRpsNumero($response['rpsNumber'] ?? $nota->getRpsNumero());
        $nota->setAtualizadoEm(new \DateTime());
        $this->em->flush();

        return $response;
    }

    public function isDisponivel(): bool
    {
        return true;
    }

    // ═══════════════════════════════════════════════════════════════
    // CLIENTE: criar / buscar no Asaas
    // ═══════════════════════════════════════════════════════════════

    /**
     * Verifica se o cliente já existe no Asaas pelo CPF/CNPJ.
     * Se não existir, cria. Retorna o objeto cliente do Asaas.
     */
    public function sincronizarClienteAsaas(Cliente $cliente, array $cfg): array
    {
        $cpfCnpj = preg_replace('/\D/', '', $cliente->getCpf() ?? '');

        return $this->criarOuBuscarClienteAsaas([
            'name'         => $cliente->getNome(),
            'cpfCnpj'      => $cpfCnpj ?: null,
            'email'        => $cliente->getEmail(),
            'phone'        => preg_replace('/\D/', '', $cliente->getTelefone() ?? ''),
            'mobilePhone'  => preg_replace('/\D/', '', $cliente->getWhatsapp() ?? ''),
            'address'      => $cliente->getRua(),
            'addressNumber'=> (string) ($cliente->getNumero() ?? 'S/N'),
            'complement'   => $cliente->getComplemento(),
            'province'     => $cliente->getBairro(),
            'postalCode'   => (string) $cliente->getCep(),
            'externalReference' => 'homepet_cliente_' . $cliente->getId(),
        ], $cfg);
    }

    private function criarOuBuscarClienteAsaas(array $dados, array $cfg): array
    {
        // Tenta buscar pelo CPF/CNPJ se existir
        $cpfCnpj = $dados['cpfCnpj'] ?? null;

        if ($cpfCnpj) {
            $lista = $this->get($cfg, '/v3/customers?cpfCnpj=' . urlencode($cpfCnpj));
            if (!empty($lista['data'])) {
                return $lista['data'][0];
            }
        }

        // Tenta buscar pela referência externa
        $extRef = $dados['externalReference'] ?? null;
        if ($extRef) {
            $lista = $this->get($cfg, '/v3/customers?externalReference=' . urlencode($extRef));
            if (!empty($lista['data'])) {
                return $lista['data'][0];
            }
        }

        // Cria novo cliente
        $payload = array_filter($dados, fn($v) => $v !== null && $v !== '');
        $payload['name'] = $dados['name']; // garante obrigatório

        return $this->post($cfg, '/v3/customers', $payload);
    }

    // ═══════════════════════════════════════════════════════════════
    // SERVIÇOS MUNICIPAIS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Lista os serviços municipais disponíveis no Asaas para o estabelecimento.
     * Útil para popular o select de configuração.
     */
    public function listarServicosMunicipais(int $estabelecimentoId, ?string $filtro = null): array
    {
        $cfg = $this->getConfig($estabelecimentoId);
        $url = '/v3/invoices/municipalServices';
        if ($filtro) {
            $url .= '?description=' . urlencode($filtro);
        }

        try {
            $response = $this->get($cfg, $url);
            return $response['data'] ?? [];
        } catch (\Exception $e) {
            $this->logger->warning('Não foi possível listar serviços municipais: ' . $e->getMessage());
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // WEBHOOK
    // ═══════════════════════════════════════════════════════════════

    /**
     * Processa evento de webhook recebido do Asaas sobre notas fiscais.
     * Chamado pelo NotaFiscalController::webhook()
     */
    public function processarWebhook(array $payload): void
    {
        $event      = $payload['event'] ?? null;
        $invoiceData = $payload['invoice'] ?? null;

        if (!$event || !$invoiceData) {
            return;
        }

        $asaasId = $invoiceData['id'] ?? null;
        if (!$asaasId) return;

        $nota = $this->em->getRepository(NotaFiscal::class)->findByAsaasId($asaasId);
        if (!$nota) {
            $this->logger->warning('Webhook NF: nota não encontrada localmente.', ['asaas_id' => $asaasId]);
            return;
        }

        $nota->setStatus($invoiceData['status'] ?? $nota->getStatus());
        $nota->setNumeroNota($invoiceData['number'] ?? $nota->getNumeroNota());
        $nota->setPdfUrl($invoiceData['pdfUrl'] ?? $nota->getPdfUrl());
        $nota->setXmlUrl($invoiceData['xmlUrl'] ?? $nota->getXmlUrl());
        $nota->setRpsNumero($invoiceData['rpsNumber'] ?? $nota->getRpsNumero());
        $nota->setAtualizadoEm(new \DateTime());

        if ($event === 'INVOICE_ERROR') {
            $nota->setStatus(NotaFiscal::STATUS_ERRO);
            $nota->setObservacoes(
                ($nota->getObservacoes() ?? '') .
                "\n[ERRO Asaas] " . ($invoiceData['statusDescription'] ?? 'Erro desconhecido')
            );
        }

        $this->em->flush();

        $this->logger->info("Webhook NF processado: {$event}", [
            'asaas_id' => $asaasId,
            'status'   => $nota->getStatus(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ═══════════════════════════════════════════════════════════════

    private function resolverCliente(Venda $venda, array $cfg): Cliente
    {
        // Tenta achar o cliente pelo nome registrado na venda
        $clienteNome = $venda->getCliente();
        $cliente = $this->em->getRepository(Cliente::class)->findOneBy([
            'nome'              => $clienteNome,
            'estabelecimentoId' => $venda->getEstabelecimentoId(),
        ]);

        if (!$cliente) {
            // Cria um cliente temporário apenas com o nome disponível
            $cliente = new Cliente();
            $cliente->setNome($clienteNome);
            $cliente->setEstabelecimentoId($venda->getEstabelecimentoId());
        }

        return $cliente;
    }

    private function montarDescricaoServico(Venda $venda, array $itens): string
    {
        $linhas = ["Venda #{$venda->getId()} — {$venda->getData()->format('d/m/Y')}"];

        foreach ($itens as $item) {
            /** @var VendaItem $item */
            $linhas[] = sprintf(
                '- %s x %d = R$ %s',
                $item->getProdutoNome(),
                $item->getQuantidade(),
                number_format((float) $item->getSubtotal(), 2, ',', '.')
            );
        }

        $linhas[] = sprintf('Total: R$ %s', number_format($venda->getTotal(), 2, ',', '.'));

        return implode("\n", $linhas);
    }

    private function montarPayloadNota(
        Venda $venda,
        string $asaasCustomerId,
        string $descricao,
        array $cfg
    ): array {
        $payload = [
            'customer'           => $asaasCustomerId,
            'serviceDescription' => $descricao,
            'value'              => round($venda->getTotal(), 2),
            'deductions'         => 0,
            'effectiveDate'      => $venda->getData()->format('Y-m-d'),
            'taxes'              => $this->montarImpostos($cfg),
        ];

        if ($cfg['municipal_service_id']) {
            $payload['municipalServiceId']   = $cfg['municipal_service_id'];
            $payload['municipalServiceName'] = $cfg['municipal_service_name'] ?? null;
        } elseif ($cfg['municipal_service_code']) {
            $payload['municipalServiceCode'] = $cfg['municipal_service_code'];
            $payload['municipalServiceName'] = $cfg['municipal_service_name'] ?? null;
        }

        return array_filter($payload, fn($v) => $v !== null);
    }

    private function montarImpostos(array $cfg): array
    {
        return [
            'retainIss' => ($cfg['retain_iss'] ?? '0') === '1',
            'iss'       => (float) ($cfg['iss'] ?? 0),
            'cofins'    => (float) ($cfg['cofins'] ?? 0),
            'csll'      => (float) ($cfg['csll'] ?? 0),
            'inss'      => (float) ($cfg['inss'] ?? 0),
            'ir'        => (float) ($cfg['ir'] ?? 0),
            'pis'       => (float) ($cfg['pis'] ?? 0),
        ];
    }

    private function persistirNota(
        Venda $venda,
        Cliente $cliente,
        string $asaasCustomerId,
        array $response,
        array $cfg
    ): NotaFiscal {
        $nota = new NotaFiscal();
        $nota->setEstabelecimentoId($venda->getEstabelecimentoId());
        $nota->setOrigem('venda');
        $nota->setVendaId($venda->getId());
        $nota->setClienteId($cliente->getId());
        $nota->setClienteNome($cliente->getNome() ?? $venda->getCliente());
        $nota->setClienteCpfCnpj(preg_replace('/\D/', '', $cliente->getCpf() ?? ''));
        $nota->setAsaasCustomerId($asaasCustomerId);
        $nota->setAsaasInvoiceId($response['id']);
        $nota->setValor((string) $venda->getTotal());
        $nota->setDescricaoServico($response['serviceDescription'] ?? '');
        $nota->setStatus($response['status'] ?? NotaFiscal::STATUS_AGENDADA);
        $nota->setDataEmissao(new \DateTime($response['effectiveDate'] ?? 'now'));
        $nota->setNumeroNota($response['number'] ?? null);
        $nota->setPdfUrl($response['pdfUrl'] ?? null);
        $nota->setXmlUrl($response['xmlUrl'] ?? null);
        $nota->setMunicipalServiceId($cfg['municipal_service_id'] ?? null);
        $nota->setMunicipalServiceCode($cfg['municipal_service_code'] ?? null);
        $nota->setMunicipalServiceName($cfg['municipal_service_name'] ?? null);
        $nota->setImpostos($this->montarImpostos($cfg));

        $this->em->persist($nota);
        $this->em->flush();

        return $nota;
    }

    // ═══════════════════════════════════════════════════════════════
    // CONFIGURAÇÃO
    // ═══════════════════════════════════════════════════════════════

    /**
     * Lê as configurações do estabelecimento da tabela Config.
     * Faz fallback para variáveis de ambiente globais (.env).
     */
    public function getConfig(int $estabelecimentoId): array
    {
        $configs = $this->em->getRepository(Config::class)->findBy([
            'estabelecimento_id' => $estabelecimentoId,
            'tipo'               => 'asaas',
        ]);

        // Indexa por chave
        $map = [];
        foreach ($configs as $c) {
            $map[$c->getChave()] = $c->getValor();
        }

        // Fallback .env
        $apiKey = $map['asaas_api_key'] ?? ($_ENV['ASAAS_API_KEY'] ?? null);
        $env    = $map['asaas_environment'] ?? ($_ENV['ASAAS_ENVIRONMENT'] ?? 'sandbox');

        return [
            'api_key'              => $apiKey,
            'environment'          => $env,
            'base_url'             => $env === 'production' ? self::URL_PRODUCTION : self::URL_SANDBOX,
            'municipal_service_id'   => $map['asaas_municipal_service_id']   ?? null,
            'municipal_service_code' => $map['asaas_municipal_service_code'] ?? null,
            'municipal_service_name' => $map['asaas_municipal_service_name'] ?? null,
            'iss'       => $map['asaas_iss']        ?? '0',
            'cofins'    => $map['asaas_cofins']     ?? '0',
            'csll'      => $map['asaas_csll']       ?? '0',
            'pis'       => $map['asaas_pis']        ?? '0',
            'ir'        => $map['asaas_ir']         ?? '0',
            'inss'      => $map['asaas_inss']       ?? '0',
            'retain_iss'=> $map['asaas_retain_iss'] ?? '0',
        ];
    }

    // ═══════════════════════════════════════════════════════════════
    // HTTP HELPERS
    // ═══════════════════════════════════════════════════════════════

    private function headers(array $cfg): array
    {
        return [
            'access_token' => $cfg['api_key'],
            'Content-Type' => 'application/json',
            'User-Agent'   => 'HomePet/1.0',
        ];
    }

    private function post(array $cfg, string $path, array $body): array
    {
        try {
            $resp = $this->http->request('POST', $cfg['base_url'] . $path, [
                'headers' => $this->headers($cfg),
                'json'    => $body,
            ]);
            return $resp->toArray(false);
        } catch (\Exception $e) {
            $this->logger->error('Asaas POST error: ' . $e->getMessage(), ['path' => $path]);
            throw new NotaFiscalException('Erro de comunicação com Asaas: ' . $e->getMessage());
        }
    }

    private function get(array $cfg, string $path): array
    {
        try {
            $resp = $this->http->request('GET', $cfg['base_url'] . $path, [
                'headers' => $this->headers($cfg),
            ]);
            return $resp->toArray(false);
        } catch (\Exception $e) {
            $this->logger->error('Asaas GET error: ' . $e->getMessage(), ['path' => $path]);
            throw new NotaFiscalException('Erro de comunicação com Asaas: ' . $e->getMessage());
        }
    }
}