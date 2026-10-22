<?php

declare(strict_types=1);

namespace Adab\Api\Tests;

use Adab\Api\Uploads;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

/**
 * Validação de upload por conteúdo (finfo) — não confia no nome/extensão
 * que o cliente enviou. Filesystem apenas (sem banco).
 */
final class UploadsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/adab_uploads_test';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
    }

    private function uploadFake(string $conteudo, string $nomeCliente = 'x.png'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($tmp, $conteudo);
        $stream = (new StreamFactory())->createStreamFromFile($tmp);
        return new UploadedFile($stream, $nomeCliente, null, filesize($tmp), UPLOAD_ERR_OK);
    }

    /** PNG 1×1 real. */
    private function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    public function testAceitaPngERetornaUrlPublica(): void
    {
        $u = new Uploads($this->dir);
        $url = $u->salvarImagem($this->uploadFake($this->png(), 'foto.php.png'), 'membros', 'membro_7');

        $this->assertMatchesRegularExpression('#^/uploads/membros/membro_7_[a-f0-9]{16}\.png$#', $url);
        $this->assertFileExists($this->dir . '/membros/' . basename($url));
        // extensão veio do conteúdo, não do nome enviado pelo cliente
        $this->assertStringEndsWith('.png', $url);
    }

    public function testRejeitaConteudoNaoImagem(): void
    {
        $u = new Uploads($this->dir);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TIPO_INVALIDO');
        $u->salvarImagem($this->uploadFake('<?php echo 1;', 'foto.png'), 'membros', 'm');
    }

    public function testRejeitaArquivoGrande(): void
    {
        $u = new Uploads($this->dir);
        // arquivo além do limite (2MB): marca o tamanho no objeto
        $tmp = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($tmp, str_repeat("\x89", 10));
        $stream = (new StreamFactory())->createStreamFromFile($tmp);
        $grande = new UploadedFile($stream, 'a.png', null, 3 * 1024 * 1024, UPLOAD_ERR_OK);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ARQUIVO_GRANDE');
        $u->salvarImagem($grande, 'membros', 'm');
    }

    public function testRejeitaErroDeUpload(): void
    {
        $u = new Uploads($this->dir);
        $file = new UploadedFile((new StreamFactory())->createStream(''), 'a.png', null, 0, UPLOAD_ERR_NO_FILE);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('UPLOAD_FALHOU');
        $u->salvarImagem($file, 'membros', 'm');
    }

    public function testRemoverSoApagaDentroDeUploads(): void
    {
        $u = new Uploads($this->dir);
        $url = $u->salvarImagem($this->uploadFake($this->png()), 'x', 'k');
        $u->remover($url);
        $this->assertFileDoesNotExist($this->dir . '/x/' . basename($url));

        // caminho externo não é apagado (defesa contra path traversal)
        $fora = tempnam(sys_get_temp_dir(), 'fora');
        $u->remover('/fora/' . basename($fora));
        $this->assertFileExists($fora);
        unlink($fora);
    }
}
