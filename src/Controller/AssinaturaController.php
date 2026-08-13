<?php

namespace App\Controller;

use App\Entity\AssinaturaModulo;
use App\Entity\Estabelecimento;
use App\Entity\Fatura;
use App\Entity\Modulo;
use App\Entity\Plano;
use App\Service\InvoiceService;
use App\Service\Payment\PaymentGatewayFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class AssinaturaController extends DefaultController
{
    // IDs de módulos adicionais possíveis (espelha PlanoController)
    private const MODULOS_ADICIONAIS_IDS = [7, 8, 9, 10];
    // Preços-base de cada módulo adicional (R$/mês).
    // Podem ser movidos para banco futuramente.
    private const PRECOS_ADICIONAIS = [
        7 => 49.90, // Banho e Tosa
        8 => 59.90, // Hospedagem de Cães
        9 => 79.90, // Clínica Veterinária
        10 => 95.00, // PDV / Emissão de Notas Fiscais
    ];
    // ──────────────────────────────────────────────────────────────────
    // PAINEL PRINCIPAL (aba "Meu Plano" dentro de Settings)
    // ──────────────────────────────────────────────────────────────────
    #[Route('dashboard/assinatura/painel', name: 'assinatura_painel')]
    public function painel(): Response
    {
        $eid = $this->getIdBase();
        $estabelecimento = $this->em->getRepository(Estabelecimento::class)->find($eid);
        $planoAtual = $this->em->getRepository(Plano::class)->find($estabelecimento->getPlanoId());
        $todosPlanos = $this->em->getRepository(Plano::class)->findBy(['status' => 'Ativo'], ['valor' => 'ASC']);

        // Módulos adicionais
        $modulosAdicionaisDisp = $this->em->getRepository(Modulo::class)->findBy(
            ['id' => self::MODULOS_ADICIONAIS_IDS, 'status' => 'Ativo']
        );

        $assinaturasModulos = $this->em->getRepository(AssinaturaModulo::class)
            ->findByEstabelecimento($eid);

        // Indexa por moduloId para lookup rápido na view
        $assinaturasIdx = [];
        foreach ($assinaturasModulos as $a) {
            $assinaturasIdx[$a->getModuloId()] = $a;
        }

        // Totais
        $totalAdicionais = $this->em->getRepository(AssinaturaModulo::class)->totalMensalAtivos($eid);
        $totalMensal = (float) $planoAtual->getValor() + $totalAdicionais;

        // Fatura mais recente (para exibir status da assinatura principal)
        $faturaAtual = $this->em->getRepository(Fatura::class)->findOneBy(
            ['estabelecimentoId' => $eid],
            ['id' => 'DESC']
        );

        return $this->render('assinatura/painel.html.twig', [
            'estabelecimento' => $estabelecimento,
            'planoAtual' => $planoAtual,
            'todosPlanos' => $todosPlanos,
            'modulosAdicionaisDisp' => $modulosAdicionaisDisp,
            'assinaturasIdx' => $assinaturasIdx,
            'precosAdicionais' => self::PRECOS_ADICIONAIS,
            'totalAdicionais' => $totalAdicionais,
            'totalMensal' => $totalMensal,
            'faturaAtual' => $faturaAtual,
        ]);
    }
    // ──────────────────────────────────────────────────────────────────
    // CONTRATAR MÓDULO ADICIONAL
    // ───────────────────────────────────────────────────────────────
    #[Route('dashboard/assinatura/modulo/contratar/{moduloId}', name: 'assinatura_contratar_modulo')]
    public function contratarModulo(int $moduloId, PaymentGatewayFactory $gatewayFactory, InvoiceService $invoiceService): Response
    {
        $eid = $this->getIdBase();

        // Valida módulo
        if (!in_array($moduloId, self::MODULOS_ADICIONAIS_IDS, true)) {
            $this->addFlash('error', 'Módulo não disponível para contratação avulsa.');
            return $this->redirectToRoute('assinatura_painel');
        }

        $modulo = $this->em->getRepository(Modulo::class)->find($moduloId);
        if (!$modulo) {
            $this->addFlash('error', 'Módulo não encontrado.');
            return $this->redirectToRoute('assinatura_painel');
        }

        // Verifica se já contratado
        $jaContratado = $this->em->getRepository(AssinaturaModulo::class)
            ->jaContratado($eid, $moduloId);

        if ($jaContratado) {
            $this->addFlash('warning', "O módulo \"{$modulo->getTitulo()}\" já está ativo ou pendente de aprovação.");
            return $this->redirectToRoute('assinatura_painel');
        }

        $estabelecimento = $this->em->getRepository(Estabelecimento::class)->find($eid);
        $plano = $this->em->getRepository(Plano::class)->find($estabelecimento->getPlanoId());
        $usuario = $this->em->getRepository(\App\Entity\Usuario::class)
            ->findOneBy(['petshop_id' => $eid]);

        $valorModulo = self::PRECOS_ADICIONAIS[$moduloId] ?? 49.90;

        // Cria registro de assinatura (status: pendente até aprovação MP)
        $assinaturaModulo = new AssinaturaModulo();
        $assinaturaModulo->setEstabelecimentoId($eid);
        $assinaturaModulo->setModuloId($moduloId);
        $assinaturaModulo->setModuloTitulo($modulo->getTitulo());
        $assinaturaModulo->setValorMensal((string) $valorModulo);
        $assinaturaModulo->setStatus(AssinaturaModulo::STATUS_PENDENTE);
        $assinaturaModulo->setObservacoes('Aguardando aprovação do gateway de pagamento.');

        $this->em->persist($assinaturaModulo);
        $this->em->flush();

        try {
            // Cria fatura para o adicional
            $invoice = $invoiceService->createInvoice($estabelecimento, [
                'tipo' => 'modulo_adicional',
                'valor_total' => $valorModulo,
                'plano_id' => $plano->getId(),
                'data_vencimento' => new \DateTime('+1 month'),
            ]);

            $gateway = $gatewayFactory->getDefaultGateway();

            // Monta assinatura recorrente no MP apenas para o valor do módulo
            $dadosPagamento = [
                'title' => "Módulo: {$modulo->getTitulo()} — {$estabelecimento->getRazaoSocial()}",
                'price' => $valorModulo,
                'email' => $usuario->getEmail() ?? 'noreply@homepet.com.br',
                'external_reference' => $invoice->getId(),
                'comprador' => [
                    'name' => $usuario->getNomeUsuario() ?? $estabelecimento->getRazaoSocial(),
                    'email' => $usuario->getEmail() ?? '',
                ],
            ];

            $result = $gateway->createSubscription($dadosPagamento);

            if ($result['success']) {
                $assinaturaModulo->setSubscriptionId($result['subscription_id']);
                $assinaturaModulo->setInitPoint($result['init_point']);

                $invoice->setSubscriptionId($result['subscription_id']);
                $invoice->setPaymentGateway($gateway->getGatewayName());
                $this->em->persist($invoice);
                $this->em->flush();

                // Redireciona para aprovação no MP
                return $this->redirect($result['init_point']);
            }

            throw new \Exception($result['error'] ?? 'Erro ao criar assinatura no gateway.');

        } catch (\Exception $e) {
            // Marca como suspenso para retentativa futura
            $assinaturaModulo->setStatus(AssinaturaModulo::STATUS_SUSPENSO);
            $assinaturaModulo->setObservacoes('Erro ao processar: ' . $e->getMessage());
            $this->em->flush();

            $this->addFlash('error', 'Não foi possível processar o pagamento: ' . $e->getMessage());
            return $this->redirectToRoute('assinatura_painel');
        }
    }
    // ──────────────────────────────────────────────────────────────────
    // CANCELAR MÓDULO ADICIONAL
    // ───────────────────────────────────────────────────────────────
    #[Route('dashboard/assinatura/modulo/cancelar/{id}', name: 'assinatura_cancelar_modulo')]
    public function cancelarModulo(int $id, PaymentGatewayFactory $gatewayFactory): Response
    {
        $eid = $this->getIdBase();

        $assinaturaModulo = $this->em->getRepository(AssinaturaModulo::class)->find($id);

        if (!$assinaturaModulo || $assinaturaModulo->getEstabelecimentoId() !== $eid) {
            $this->addFlash('error', 'Módulo não encontrado ou sem permissão.');
            return $this->redirectToRoute('assinatura_painel');
        }

        // Cancela no gateway se tiver subscription_id
        if ($assinaturaModulo->getSubscriptionId()) {
            try {
                $gateway = $gatewayFactory->getDefaultGateway();
                $gateway->cancelSubscription($assinaturaModulo->getSubscriptionId());
            } catch (\Exception $e) {
                // Loga mas não bloqueia o cancelamento local
                $this->logger->warning("Falha ao cancelar no gateway: " . $e->getMessage());
            }
        }

        $assinaturaModulo->setStatus(AssinaturaModulo::STATUS_CANCELADO);
        $assinaturaModulo->setCanceladoEm(new \DateTime());
        $assinaturaModulo->setObservacoes(
            ($assinaturaModulo->getObservacoes() ?? '') .
            "\n[" . date('d/m/Y H:i') . "] Cancelado pelo administrador do estabelecimento."
        );
        $this->em->flush();

        $this->addFlash('success', "Módulo \"{$assinaturaModulo->getModuloTitulo()}\" cancelado. O acesso será encerrado ao final do período pago.");
        return $this->redirectToRoute('assinatura_painel');
    }
    // ──────────────────────────────────────────────────────────────────
    // CANCELAR PLANO PRINCIPAL (cancela assinatura MP + marca inativo)
    // ───────────────────────────────────────────────────────────────
    #[Route('dashboard/assinatura/cancelar-plano', name: 'assinatura_cancelar_plano')]
    public function cancelarPlano(Request $request, PaymentGatewayFactory $gatewayFactory): Response
    {
        $eid = $this->getIdBase();
        $motivo = $request->get('motivo', 'Cancelado pelo usuário.');

        $fatura = $this->em->getRepository(Fatura::class)->findOneBy(
            ['estabelecimentoId' => $eid, 'status' => 'pago'],
            ['id' => 'DESC']
        );

        if ($fatura && $fatura->getSubscriptionId()) {
            try {
                $gateway = $gatewayFactory->getDefaultGateway();
                $gateway->cancelSubscription($fatura->getSubscriptionId());
            } catch (\Exception $e) {
                $this->logger->warning("Erro ao cancelar plano no gateway: " . $e->getMessage());
            }

            $fatura->setStatus('cancelado');
            $fatura->setObservacoes("Cancelado em " . date('d/m/Y H:i') . ". Motivo: {$motivo}");
            $this->em->flush();
        }

        // Mantém o estabelecimento ativo até o fim do período pago
        $this->addFlash('success', 'Seu plano foi cancelado. O acesso permanece até o fim do período já pago.');
        return $this->redirectToRoute('assinatura_painel');
    }
    // ──────────────────────────────────────────────────────────────────
    // UPGRADE / MUDANÇA DE PLANO
    // ───────────────────────────────────────────────────────────────
    #[Route('dashboard/assinatura/upgrade/{planoId}', name: 'assinatura_upgrade', methods: "POST")]
    public function upgrade(
        int $planoId,
        PaymentGatewayFactory $gatewayFactory,
        InvoiceService $invoiceService
    ): Response {
        $eid = $this->getIdBase();
        $estabelecimento = $this->em->getRepository(Estabelecimento::class)->find($eid);
        $novoPlanoDB = $this->em->getRepository(Plano::class)->find($planoId);
        $planoAtual = $this->em->getRepository(Plano::class)->find($estabelecimento->getPlanoId());

        if (!$novoPlanoDB || $novoPlanoDB->getStatus() !== 'Ativo') {
            $this->addFlash('error', 'Plano não disponível.');
            return $this->redirectToRoute('assinatura_painel');
        }

        if ($novoPlanoDB->getId() === $planoAtual->getId()) {
            $this->addFlash('info', 'Você já assina este plano.');
            return $this->redirectToRoute('assinatura_painel');
        }

        $usuario = $this->em->getRepository(\App\Entity\Usuario::class)
            ->findOneBy(['petshop_id' => $eid]);

        $totalAdicionais = $this->em->getRepository(AssinaturaModulo::class)->totalMensalAtivos($eid);
        $novoTotal = (float) $novoPlanoDB->getValor() + $totalAdicionais;

        try {
            // Cancela assinatura antiga no gateway
            $faturaAtual = $this->em->getRepository(Fatura::class)->findOneBy(
                ['estabelecimentoId' => $eid, 'status' => 'pago'],
                ['id' => 'DESC']
            );

            $gateway = $gatewayFactory->getDefaultGateway();

            if ($faturaAtual->getSubscriptionId()) {
                $gateway->cancelSubscription($faturaAtual->getSubscriptionId());
                $faturaAtual->setStatus('cancelado');
                $faturaAtual->setObservacoes('Substituído por upgrade para plano ID ' . $planoId);
            }

            // Cria nova assinatura com valor total (plano + adicionais)
            $invoice = $invoiceService->createInvoice($estabelecimento, [
                'tipo' => 'upgrade',
                'valor_total' => $novoTotal,
                'plano_id' => $planoId,
                'data_vencimento' => new \DateTime('+1 month'),
            ]);

            $result = $gateway->createSubscription([
                'title' => "Plano {$novoPlanoDB->getTitulo()} — {$estabelecimento->getRazaoSocial()}",
                'price' => $novoTotal,
                'email' => $usuario->getEmail() ?? '',
                'external_reference' => $invoice->getId(),
                'comprador' => [
                    'name' => $usuario->getNomeUsuario() ?? '',
                    'email' => $usuario->getEmail() ?? '',
                ],
            ]);

            if ($result['success']) {
                // Atualiza estabelecimento
                $estabelecimento->setPlanoId($planoId);

                // Atualiza invoice
                $invoice->setSubscriptionId($result['subscription_id']);
                $invoice->setPaymentGateway($gateway->getGatewayName());
                $this->em->persist($invoice);
                $this->em->flush();

                return $this->redirect($result['init_point']);
            }

            throw new \Exception($result['error'] ?? 'Erro ao criar nova assinatura.');

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erro ao processar upgrade: ' . $e->getMessage());
            return $this->redirectToRoute('assinatura_painel');
        }
    }
    // ──────────────────────────────────────────────────────────────────
    // WEBHOOK — Mercado Pago notifica aprovação de módulo adicional
    // ───────────────────────────────────────────────────────────────
    #[Route('dashboard/assinatura/webhook/modulo', name: 'assinatura_webhook_modulo')]
    public function webhookModulo(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $type = $payload['type'] ?? null;
        $id = $payload['data']['id'] ?? null;

        if (!$id) {
            return new JsonResponse(['ok' => false, 'msg' => 'ID ausente'], \Symfony\Component\HttpFoundation\Response::HTTP_BAD_REQUEST);
        }

        if ($type === 'preapproval') {
            // Busca pelo subscription_id na tabela assinatura_modulo
            $assinatura = $this->em->getRepository(AssinaturaModulo::class)
                ->findOneBy(['subscriptionId' => $id]);

            if ($assinatura) {
                $assinatura->setStatus(AssinaturaModulo::STATUS_ATIVO);
                $assinatura->setObservacoes(
                    ($assinatura->getObservacoes() ?? '') .
                    "\n[" . date('d/m/Y H:i') . "] Ativado via webhook."
                );
                $this->em->flush();
            }
        }

        return new JsonResponse(['ok' => true]);
    }
    // ──────────────────────────────────────────────────────────────────
    // SUCESSO pós-aprovação de módulo adicional no MP
    // ───────────────────────────────────────────────────────────────
    #[Route('dashboard/assinatura/modulo/sucesso', name: 'assinatura_modulo_sucesso')]
    public function moduloSucesso(Request $request): Response
    {
        $subscriptionId = $request->get('preapproval_id');

        if ($subscriptionId) {
            $assinatura = $this->em->getRepository(AssinaturaModulo::class)
                ->findOneBy(['subscriptionId' => $subscriptionId]);

            if ($assinatura && $assinatura->getStatus() === AssinaturaModulo::STATUS_PENDENTE) {
                $assinatura->setStatus(AssinaturaModulo::STATUS_ATIVO);
                $assinatura->setObservacoes(
                    ($assinatura->getObservacoes() ?? '') .
                    "\n[" . date('d/m/Y H:i') . "] Ativado via redirect pós-pagamento."
                );
                $this->em->flush();
            }
        }

        $this->addFlash('success', '🎉 Módulo adicional ativado com sucesso! Já está disponível no seu sistema.');
        return $this->redirectToRoute('assinatura_painel');
    }
}
