<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

/**
 * Protège les écritures de l'API authentifiée par cookie de session (contre le CSRF) :
 * - si l'en-tête Origin est présent, il doit désigner le même site que la requête ;
 * - une écriture doit être envoyée en application/json (un formulaire HTML classique ne peut pas).
 * Les lectures (GET, HEAD, OPTIONS) ne sont pas concernées.
 * Derrière un reverse proxy, vérifier que l'hôte et le port vus par PHP sont ceux du navigateur.
 */
final class ApiWriteGuard implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $method = strtoupper($request->getMethod());

        if (in_array($method, self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        $origin = $request->getHeaderLine('Origin');
        if ($origin !== '' && !$this->isSameOrigin($origin, $request)) {
            return $this->deny(403, 'CROSS_ORIGIN_FORBIDDEN', 'Origine non autorisée.');
        }

        $hasBody = (int) $request->getBody()->getSize() > 0;
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) || $hasBody) {
            $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
            if ($contentType !== 'application/json') {
                return $this->deny(415, 'UNSUPPORTED_MEDIA_TYPE', 'Content-Type application/json attendu.');
            }
        }

        return $handler->handle($request);
    }

    private function isSameOrigin(string $origin, ServerRequestInterface $request): bool
    {
        $parts = parse_url($origin);
        if (!is_array($parts) || !isset($parts['host'])) {
            return false;
        }

        $originPort = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
        $uri = $request->getUri();
        $requestPort = $uri->getPort() ?? ($uri->getScheme() === 'https' ? 443 : 80);

        return strcasecmp($parts['host'], $uri->getHost()) === 0 && $originPort === $requestPort;
    }

    private function deny(int $status, string $code, string $message): ResponseInterface
    {
        return JsonResponder::respond(new Response(), ['error' => $code, 'message' => $message], $status);
    }
}
