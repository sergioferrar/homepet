<?php

namespace App\Security;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class CustomAuthenticator extends AbstractAuthenticator
{
    private UrlGeneratorInterface $urlGenerator;
    protected $security;
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager, ?Security $security, UrlGeneratorInterface $urlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->urlGenerator = $urlGenerator;
    }

    private function getRepositorio($class)
    {
        return $this->entityManager->getRepository($class);
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/api/login' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $data = [];
        $data['username'] = $request->get('username');
        $data['password'] = $request->get('password');

        if (!$data || !isset($data['username']) || !isset($data['password'])) {
            throw new AuthenticationException('Credenciais inválidas ou ausentes.');
        }

        return new Passport(
            new UserBadge($data['username']),
            new PasswordCredentials($data['password'])
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        $tokenUser = new UsernamePasswordToken($user, $firewallName, $user->getRoles());
        $serialize = serialize($user);

        $data = [];
        $data['message'] = 'Login efetuado com sucesso!';
        $data['direcionaHome'] = true;
        $data['redireciona'] = $this->urlGenerator->generate('app_login_valida');

        // Recarregar as configurações do env no $_SERVER e $_ENV
        // dd($_ENV, $_SERVER);
        $configs = $this->entityManager->getRepository(\App\Entity\Config::class)->findBy(['estabelecimento_id' => $user->getPetshopId()]);

        // Configura
        $this->loadSettingsMailer($configs);
        $this->loadGatewayPayments($configs);

        $response = new JsonResponse($data);
        $response->headers->setCookie(
            Cookie::create('AUTH_TOKEN')
                ->withValue($serialize)
                ->withHttpOnly(true)
                ->withSecure(true) // Use true para HTTPS
                ->withPath('/')
        );
        return $response;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        //dd($exception);
        return new JsonResponse(['error' => true, 'status' => 'danger', 'message' => 'E-mail ou senha inválidos'], Response::HTTP_UNAUTHORIZED);
    }

    public function loadSettingsMailer($config)
    {
        $data=[];
        foreach($config as $row){
            if($row->getTipo() == 'mailer'){
                if($row->getChave() == 'mailer_server'){$data['host'] = $row->getValor();}
                if($row->getChave() == 'mailer_user'){$data['user'] = $row->getValor();}
                if($row->getChave() == 'mailer_paswd'){$data['pswd'] = $row->getValor();}
                if($row->getChave() == 'mailer_port'){$data['port'] = $row->getValor();}
                if($row->getChave() == 'mailer_crypt'){$data['crypt'] = ($row->getValor() == 'ssl'?'smtps':'smpt');}
            }
        }
        
        $_ENV['MAILER_DSN'] = "{$data['crypt']}://{$data['user']}:{$data['pswd']}@{$data['host']}:{$data['port']}";
        $_SERVER['MAILER_DSN'] = $_ENV['MAILER_DSN'];
    }

    private function loadGatewayPayments($config)
    {
        $data=[];
        foreach($config as $row){
            if($row->getTipo() == 'gateway_payment'){
                $_ENV[strtoupper($row->getChave())] = $row->getValor();
                $_SERVER[strtoupper($row->getChave())] = $row->getValor();
            }
        }
        
    }
}
