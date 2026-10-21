<?php

declare(strict_types=1);

namespace Adab\Api\Tests\Middleware;

use Adab\Api\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

final class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $mw;

    protected function setUp(): void
    {
        $this->mw = new CsrfMiddleware();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    private function handlerOk(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        };
    }

    public function testMetodoSeguroPassaSemToken(): void
    {
        $req = (new ServerRequestFactory())->createServerRequest('GET', '/membros');
        $res = $this->mw->process($req, $this->handlerOk());
        $this->assertSame(200, $res->getStatusCode());
    }

    public function testPostSemTokenRetorna403(): void
    {
        $_SESSION['csrf'] = 'token-valido';
        $req = (new ServerRequestFactory())->createServerRequest('POST', '/membros');
        $res = $this->mw->process($req, $this->handlerOk());
        $this->assertSame(403, $res->getStatusCode());
        $this->assertStringContainsString('CSRF_INVALIDO', (string) $res->getBody());
    }

    public function testPostComTokenErradoRetorna403(): void
    {
        $_SESSION['csrf'] = 'token-valido';
        $req = (new ServerRequestFactory())
            ->createServerRequest('POST', '/membros')
            ->withHeader('X-CSRF-Token', 'errado');
        $res = $this->mw->process($req, $this->handlerOk());
        $this->assertSame(403, $res->getStatusCode());
    }

    public function testPostComTokenCorretoPassa(): void
    {
        $_SESSION['csrf'] = 'token-valido';
        $req = (new ServerRequestFactory())
            ->createServerRequest('POST', '/membros')
            ->withHeader('X-CSRF-Token', 'token-valido');
        $res = $this->mw->process($req, $this->handlerOk());
        $this->assertSame(200, $res->getStatusCode());
    }
}
