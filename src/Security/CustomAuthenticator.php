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
        return new JsonResponse(['error' => true, 'status' => 'danger', 'message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }
}
