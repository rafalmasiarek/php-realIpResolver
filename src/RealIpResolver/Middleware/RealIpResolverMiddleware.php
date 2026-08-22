<?php

declare(strict_types=1);

namespace rafalmasiarek\RealIpResolver\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use rafalmasiarek\RealIpResolver;

/**
 * PSR-15 middleware that resolves the real client IP and injects it
 * into the request attributes as 'real_ip'.
 *
 * Requires psr/http-server-middleware (listed as a suggested dependency).
 *
 * @package rafalmasiarek\RealIpResolver\Middleware
 */
class RealIpResolverMiddleware implements MiddlewareInterface
{
    /**
     * The resolver used to determine the real client IP.
     *
     * @var RealIpResolver
     */
    private RealIpResolver $resolver;

    /**
     * @param RealIpResolver $resolver Configured resolver instance.
     */
    public function __construct(RealIpResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Resolve the real client IP and inject it into request attributes.
     *
     * @param ServerRequestInterface  $request Current HTTP request.
     * @param RequestHandlerInterface $handler Next middleware handler.
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle(
            $request->withAttribute('real_ip', $this->resolver->getIp())
        );
    }
}
