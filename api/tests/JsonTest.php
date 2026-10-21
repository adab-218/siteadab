<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Json;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class JsonTest extends TestCase
{
    public function testRespostaUsaEnvelopeData(): void
    {
        $res = Json::resposta(new Response(), ['data' => ['ok' => true]]);
        $body = json_decode((string) $res->getBody(), true);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(['ok' => true], $body['data']);
        $this->assertStringContainsString('application/json', $res->getHeaderLine('Content-Type'));
    }

    public function testRespostaDefineNoStore(): void
    {
        $res = Json::resposta(new Response(), ['data' => []]);
        $this->assertSame('no-store', $res->getHeaderLine('Cache-Control'));
    }

    public function testErroUsaEnvelopeErrorComStatus(): void
    {
        $res = Json::erro(new Response(), 'SEM_PERMISSAO', 'Sem acesso.', 403);
        $body = json_decode((string) $res->getBody(), true);
        $this->assertSame(403, $res->getStatusCode());
        $this->assertSame('SEM_PERMISSAO', $body['error']['code']);
        $this->assertSame('Sem acesso.', $body['error']['message']);
    }

    public function testRespostaFallbackQuandoJsonEncodeFalha(): void
    {
        $res = Json::resposta(new Response(), ['data' => ['invalido' => "\xB1\x31"]]);
        $body = json_decode((string) $res->getBody(), true);
        $this->assertSame(500, $res->getStatusCode());
        $this->assertSame('ERRO_INTERNO', $body['error']['code']);
    }
}
