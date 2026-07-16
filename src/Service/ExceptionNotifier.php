<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Centraliza o envio de e-mails de notificação de exceptions ocorridas
 * durante a execução do sistema.
 *
 * O envio é totalmente "à prova de falhas": qualquer erro no próprio
 * processo de notificação é capturado internamente e apenas registrado no
 * log, para NUNCA interromper o fluxo da aplicação que originou a exception.
 */
class ExceptionNotifier
{
    /** Destinatário das notificações de exception. */
    private const DESTINO = 'contato@systemhomepet.com';

    /** Remetente (mesma conta configurada no MAILER_DSN). */
    private const REMETENTE = 'suporte@systemhomepet.com';

    private MailerInterface $mailer;
    private ?RequestStack $requestStack;
    private ?LoggerInterface $logger;

    public function __construct(
        MailerInterface $mailer,
        ?RequestStack $requestStack = null,
        ?LoggerInterface $logger = null
    ) {
        $this->mailer       = $mailer;
        $this->requestStack = $requestStack;
        $this->logger       = $logger;
    }

    /**
     * Notifica por e-mail que uma exception ocorreu.
     *
     * @param \Throwable $e       A exception capturada.
     * @param array      $contexto Dados extras opcionais (rota, ação, etc.).
     */
    public function notify(\Throwable $e, array $contexto = []): void
    {
        try {
            $email = (new Email())
                ->from(self::REMETENTE)
                ->to(self::DESTINO)
                ->subject('[SYSTEM HOME PET] Exception #' . $e->getCode() . ' — ' . $this->resumoMensagem($e))
                ->text($this->montarTexto($e, $contexto))
                ->html($this->montarHtml($e, $contexto));

            $this->mailer->send($email);
        } catch (\Throwable $falha) {
            // A notificação jamais pode quebrar o fluxo da aplicação.
            if ($this->logger) {
                $this->logger->error('Falha ao enviar e-mail de notificação de exception: ' . $falha->getMessage());
            }
        }
    }

    /**
     * Versão em texto puro (fallback para clientes sem HTML), no formato de log.
     */
    private function montarTexto(\Throwable $e, array $contexto): string
    {
        $linhas = [];
        $linhas[] = 'SISTEMA SYSTEM HOME PET - NOTIFICAÇÕES DE EXCEPTIONS OCORRIDAS DURANTE A EXECUÇÃO DO SISTEMA';
        $linhas[] = str_repeat('=', 80);
        $linhas[] = '';
        $linhas[] = 'Erro #' . $e->getCode() . ':';
        $linhas[] = '';
        $linhas[] = 'Ocorreu um erro no sistema:';
        $linhas[] = '';
        $linhas[] = $e->getMessage();
        $linhas[] = '';
        $linhas[] = $e->getFile();
        $linhas[] = 'Linha: ' . $e->getLine();
        $linhas[] = '';

        $ctx = $this->contextoCompleto($e, $contexto);
        if (!empty($ctx)) {
            $linhas[] = str_repeat('-', 80);
            $linhas[] = 'CONTEXTO';
            $linhas[] = str_repeat('-', 80);
            foreach ($ctx as $chave => $valor) {
                $linhas[] = $chave . ': ' . $valor;
            }
            $linhas[] = '';
        }

        $linhas[] = str_repeat('-', 80);
        $linhas[] = 'STACK TRACE';
        $linhas[] = str_repeat('-', 80);
        $linhas[] = $e->getTraceAsString();
        $linhas[] = '';
        $linhas[] = str_repeat('-', 80);
        $linhas[] = 'JSON';
        $linhas[] = str_repeat('-', 80);
        $linhas[] = $this->jsonException($e);

        return implode(PHP_EOL, $linhas);
    }

    /**
     * Versão HTML estilizada, com aparência de log de terminal.
     */
    private function montarHtml(\Throwable $e, array $contexto): string
    {
        $esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $ctx = $this->contextoCompleto($e, $contexto);
        $ctxHtml = '';
        foreach ($ctx as $chave => $valor) {
            $ctxHtml .= '<div class="linha"><span class="rotulo">' . $esc($chave) . ':</span> '
                . '<span class="valor">' . $esc($valor) . '</span></div>';
        }

        $trace = $esc($e->getTraceAsString());
        $json  = $esc($this->jsonException($e));

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#0d1117;font-family:'Segoe UI',Arial,sans-serif;">
  <div style="max-width:720px;margin:0 auto;padding:24px;">
    <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;overflow:hidden;">

      <!-- Cabeçalho -->
      <div style="background:linear-gradient(135deg,#b91c1c,#7f1d1d);padding:20px 24px;">
        <div style="color:#fecaca;font-size:12px;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;">
          Sistema System Home Pet
        </div>
        <div style="color:#ffffff;font-size:16px;font-weight:600;line-height:1.4;">
          Notificações de exceptions ocorridas durante a execução do sistema
        </div>
      </div>

      <!-- Corpo estilo log -->
      <div style="padding:20px 24px;">

        <div style="display:inline-block;background:#7f1d1d;color:#fecaca;font-family:'Courier New',monospace;font-size:13px;font-weight:700;padding:6px 12px;border-radius:6px;margin-bottom:16px;">
          Erro #{$esc($e->getCode())}
        </div>

        <p style="color:#8b949e;font-size:13px;margin:0 0 8px;">Ocorreu um erro no sistema:</p>

        <div style="background:#0d1117;border-left:3px solid #f85149;border-radius:6px;padding:14px 16px;margin-bottom:16px;">
          <span style="color:#f85149;font-family:'Courier New',monospace;font-size:14px;line-height:1.6;word-break:break-word;">
            {$esc($e->getMessage())}
          </span>
        </div>

        <div style="background:#0d1117;border-radius:6px;padding:14px 16px;margin-bottom:16px;font-family:'Courier New',monospace;font-size:13px;">
          <div style="color:#58a6ff;line-height:1.6;word-break:break-all;">{$esc($e->getFile())}</div>
          <div style="color:#8b949e;line-height:1.6;">Linha: <span style="color:#d29922;">{$esc($e->getLine())}</span></div>
        </div>

        <!-- Contexto -->
        <div style="color:#8b949e;font-size:11px;letter-spacing:1px;text-transform:uppercase;margin:20px 0 8px;">Contexto</div>
        <div style="background:#0d1117;border-radius:6px;padding:14px 16px;margin-bottom:16px;font-family:'Courier New',monospace;font-size:12px;color:#c9d1d9;line-height:1.7;">
          {$ctxHtml}
        </div>

        <!-- Stack trace -->
        <div style="color:#8b949e;font-size:11px;letter-spacing:1px;text-transform:uppercase;margin:20px 0 8px;">Stack trace</div>
        <pre style="background:#0d1117;border-radius:6px;padding:14px 16px;margin:0 0 16px;font-family:'Courier New',monospace;font-size:11px;color:#8b949e;line-height:1.6;white-space:pre-wrap;word-break:break-all;overflow-x:auto;">{$trace}</pre>

        <!-- JSON -->
        <div style="color:#8b949e;font-size:11px;letter-spacing:1px;text-transform:uppercase;margin:20px 0 8px;">JSON</div>
        <pre style="background:#0d1117;border-radius:6px;padding:14px 16px;margin:0;font-family:'Courier New',monospace;font-size:11px;color:#7ee787;line-height:1.6;white-space:pre-wrap;word-break:break-all;overflow-x:auto;">{$json}</pre>

      </div>

      <!-- Rodapé -->
      <div style="background:#0d1117;padding:14px 24px;border-top:1px solid #30363d;">
        <span style="color:#484f58;font-size:11px;font-family:'Courier New',monospace;">
          Mensagem automática &bull; System Home Pet
        </span>
      </div>

    </div>
  </div>
</body>
</html>
HTML;
    }

    /**
     * Monta o array de contexto (dados da requisição + extras informados).
     *
     * @return array<string,string>
     */
    private function contextoCompleto(\Throwable $e, array $extra): array
    {
        $ctx = [];

        try {
            $request = $this->requestStack ? $this->requestStack->getCurrentRequest() : null;
            if ($request) {
                $ctx['Rota']     = (string) $request->attributes->get('_route', '(desconhecida)');
                $ctx['Método']   = $request->getMethod();
                $ctx['URL']      = $request->getUri();
                $ctx['IP']       = (string) $request->getClientIp();
            }
        } catch (\Throwable $ignore) {
            // Ambiente sem request (CLI, worker) — ignora.
        }

        $ctx['Data/Hora'] = date('d/m/Y H:i:s');
        $ctx['Exception'] = get_class($e);

        // Extras informados pelo chamador têm prioridade.
        foreach ($extra as $chave => $valor) {
            if (is_scalar($valor) || $valor === null) {
                $ctx[(string) $chave] = (string) $valor;
            } else {
                $ctx[(string) $chave] = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        return $ctx;
    }

    /**
     * Serializa a exception em JSON de forma segura e legível.
     */
    private function jsonException(\Throwable $e): string
    {
        $dados = [
            'class'   => get_class($e),
            'code'    => $e->getCode(),
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ];

        if ($previous = $e->getPrevious()) {
            $dados['previous'] = [
                'class'   => get_class($previous),
                'message' => $previous->getMessage(),
                'file'    => $previous->getFile(),
                'line'    => $previous->getLine(),
            ];
        }

        return (string) json_encode(
            $dados,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    private function resumoMensagem(\Throwable $e): string
    {
        $msg = trim($e->getMessage());
        if ($msg === '') {
            return get_class($e);
        }

        return mb_strlen($msg) > 80 ? mb_substr($msg, 0, 77) . '...' : $msg;
    }
}
