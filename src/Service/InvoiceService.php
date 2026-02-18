<?php

namespace App\Service;

use App\Entity\Fatura;
use App\Entity\Estabelecimento;
use App\Repository\FaturaRepository;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceService
{
    private $em;
    private $invoiceRepository;
    private $emailService;

    public function __construct(
        EntityManagerInterface $em,
        FaturaRepository $invoiceRepository,
        EmailService $emailService
    ) {
        $this->em = $em;
        $this->invoiceRepository = $invoiceRepository;
        $this->emailService = $emailService;
    }

    /**
     * Cria um novo invoice para um estabelecimento
     */
    public function createInvoice(Estabelecimento $estabelecimento, array $data): Fatura
    {
        $invoice = new Fatura();
        $invoice->setEstabelecimentoId($estabelecimento->getId());
        $invoice->setNumeroInvoice($this->generateInvoiceNumber());
        $invoice->setTipo($data['tipo'] ?? 'assinatura');
        $invoice->setValorTotal($data['valor_total']);
        $invoice->setValorDesconto($data['valor_desconto'] ?? 0);
        $invoice->setPlanoId($data['plano_id']);
        $invoice->setDataVencimento($data['data_vencimento'] ?? new \DateTime('+30 days'));
        
        if (isset($data['payment_gateway'])) {
            $invoice->setPaymentGateway($data['payment_gateway']);
        }
        
        if (isset($data['subscription_id'])) {
            $invoice->setSubscriptionId($data['subscription_id']);
        }

        $this->em->persist($invoice);
        $this->em->flush();

        return $invoice;
    }

    /**
     * Marca um invoice como pago
     */
    public function markAsPaid(Invoice $invoice, array $paymentData = []): void
    {
        $invoice->setStatus('pago');
        $invoice->setDataPagamento(new \DateTime());
        $invoice->setUpdatedAt(new \DateTime());

        if (isset($paymentData['payment_id'])) {
            $invoice->setPaymentId($paymentData['payment_id']);
        }

        if (!empty($paymentData)) {
            $invoice->setPaymentData(json_encode($paymentData));
        }

        $this->em->flush();
    }

    /**
     * Marca um invoice como vencido
     */
    public function markAsExpired(Invoice $invoice): void
    {
        $invoice->setStatus('vencido');
        $invoice->setUpdatedAt(new \DateTime());
        $this->em->flush();
    }

    /**
     * Cancela um invoice
     */
    public function cancelInvoice(Invoice $invoice): void
    {
        $invoice->setStatus('cancelado');
        $invoice->setUpdatedAt(new \DateTime());
        $this->em->flush();
    }

    /**
     * Gera número único para o invoice
     */
    private function generateInvoiceNumber(): string
    {
        $ano = date('Y');
        $mes = date('m');
        
        // Busca último invoice do mês
        $lastInvoice = $this->invoiceRepository->createQueryBuilder('i')
            ->where('i.numeroInvoice LIKE :prefix')
            ->setParameter('prefix', "INV-{$ano}{$mes}%")
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->getNumeroInvoice(), -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "INV-{$ano}{$mes}{$newNumber}";
    }

    /**
     * Verifica e marca invoices vencidos
     */
    public function checkExpiredInvoices(): int
    {
        $expiredInvoices = $this->invoiceRepository->findPendingInvoices();
        
        foreach ($expiredInvoices as $invoice) {
            $this->markAsExpired($invoice);
        }

        return count($expiredInvoices);
    }

    /**
     * Envia notificação de invoice criado
     */
    public function sendInvoiceNotification(Fatura $invoice, string $email): void
    {
        // TODO: Implementar template de email
        $subject = "Nova fatura - " . $invoice->getNumeroInvoice();
        $message = "Uma nova fatura foi gerada para sua assinatura.";
        
        $this->emailService->sendEmail($email, $subject, $message);
    }

    /**
     * Envia notificação de assinatura próxima ao vencimento
     */
    public function sendExpirationWarning(Estabelecimento $estabelecimento, int $diasRestantes): void
    {
        // TODO: Buscar email do estabelecimento
        $email = ""; // implementar busca
        
        $subject = "Sua assinatura expira em {$diasRestantes} dias";
        $message = "Sua assinatura está próxima do vencimento. Renove agora para continuar utilizando o sistema.";
        
        // $this->emailService->sendEmail($email, $subject, $message);
    }

    /**
     * Renova assinatura de um estabelecimento
     */
    public function renewSubscription(Estabelecimento $estabelecimento, int $planoId, float $valor): Fatura
    {
        $dataVencimento = clone $estabelecimento->getDataPlanoFim();
        $dataVencimento->modify('+30 days');

        $invoice = $this->createInvoice($estabelecimento, [
            'tipo' => 'renovacao',
            'valor_total' => $valor,
            'plano_id' => $planoId,
            'data_vencimento' => $dataVencimento,
        ]);

        return $invoice;
    }

    /**
     * Obtém estatísticas de invoices
     */
    public function getInvoiceStats(): array
    {
        $statusData = $this->invoiceRepository->getInvoicesPorStatus();
        
        $stats = [
            'total_pendente' => 0,
            'total_pago' => 0,
            'total_vencido' => 0,
            'total_cancelado' => 0,
            'valor_pendente' => 0,
            'valor_recebido' => 0,
        ];

        foreach ($statusData as $data) {
            $status = $data['status'];
            $quantidade = $data['quantidade'];
            $total = $data['total'];

            $stats["total_{$status}"] = $quantidade;
            
            if ($status === 'pago') {
                $stats['valor_recebido'] = $total;
            } elseif ($status === 'pendente') {
                $stats['valor_pendente'] = $total;
            }
        }

        return $stats;
    }
}
