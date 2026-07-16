<?php

namespace App\EventListener;

use App\Service\ExceptionNotifier;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Dispara o envio de e-mail de notificação sempre que uma exception não
 * tratada ocorre durante a execução do sistema.
 *
 * Erros HTTP "esperados" (404, 403, 405, etc.) são ignorados para evitar
 * spam — apenas erros reais (status >= 500 ou exceptions não-HTTP) são
 * notificados.
 */
class ExceptionNotificationSubscriber implements EventSubscriberInterface
{
    private ExceptionNotifier $notifier;

    public function __construct(ExceptionNotifier $notifier)
    {
        $this->notifier = $notifier;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Prioridade baixa: roda depois dos demais listeners de exception.
            KernelEvents::EXCEPTION => ['onKernelException', -200],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Ignora erros HTTP de cliente (4xx). Notifica apenas 5xx.
        if ($exception instanceof HttpExceptionInterface
            && $exception->getStatusCode() < 500) {
            return;
        }

        $this->notifier->notify($exception);
    }
}
