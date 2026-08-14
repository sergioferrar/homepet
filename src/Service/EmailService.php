<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Envia um e-mail simples.
     *
     * @param string $to Destinatário
     * @param string $subject Assunto
     * @param string $content Conteúdo em HTML ou texto
     * @param string|null $from Remetente (opcional)
     * @return void
     */
    public function sendEmail(string $to, string $subject, string $content, ?string $from = null): void
    {
        $email = (new Email())
            ->from('suporte@systemhomepet.com')
            ->to($to)
            ->subject($subject)
            ->html($content);

        $this->mailer->send($email);
    }
}
