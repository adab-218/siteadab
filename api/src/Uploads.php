<?php

declare(strict_types=1);

namespace Adab\Api;

use Psr\Http\Message\UploadedFileInterface;

/**
 * Salvamento seguro de uploads (fotos de membros, banners de eventos).
 *
 * - Apenas imagens: jpeg/png/webp, validadas por conteúdo
 *   (getimagesize), nunca pela extensão do nome original.
 * - Tamanho máx. 2 MB.
 * - Nome gerado no servidor (prefixo + aleatório); /uploads/ tem .htaccess
 *   que nega execução de scripts.
 */
final class Uploads
{
    private const MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(private string $dir)
    {
    }

    /**
     * Grava o arquivo em {dir}/{subdir}/ e devolve a URL pública relativa
     * (/uploads/{subdir}/{nome}). Lança RuntimeException com código
     * UPLOAD_FALHOU | ARQUIVO_GRANDE | TIPO_INVALIDO.
     */
    public function salvarImagem(UploadedFileInterface $file, string $subdir, string $prefixo): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('UPLOAD_FALHOU');
        }
        if (($file->getSize() ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException('ARQUIVO_GRANDE');
        }

        // getimagesize valida o conteúdo (não a extensão do nome) e não
        // depende da ext fileinfo — ausente em alguns hosts compartilhados
        $tmp = $file->getStream()->getMetadata('uri');
        $img = is_string($tmp) && is_file($tmp) ? @getimagesize($tmp) : false;
        $mime = is_array($img) ? ($img['mime'] ?? null) : null;
        $ext = is_string($mime) ? (self::MIME[$mime] ?? null) : null;
        if ($ext === null) {
            throw new \RuntimeException('TIPO_INVALIDO');
        }

        $subdir = trim($subdir, '/\\');
        $destino = rtrim($this->dir, '/\\') . DIRECTORY_SEPARATOR . $subdir;
        if (!is_dir($destino)) {
            mkdir($destino, 0755, true);
        }

        $nome = $prefixo . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $file->moveTo($destino . DIRECTORY_SEPARATOR . $nome);
        return "/uploads/{$subdir}/{$nome}";
    }

    /** Remove arquivo anterior (somente se for caminho interno /uploads/*). */
    public function remover(?string $url): void
    {
        if (!$url || !str_starts_with($url, '/uploads/')) {
            return;
        }
        $base = realpath($this->dir);
        $real = realpath(rtrim($this->dir, '/\\') . substr($url, strlen('/uploads')));
        if ($base !== false && $real !== false && str_starts_with($real, $base)) {
            @unlink($real);
        }
    }

    /** Mensagem amigável por código de erro. */
    public static function mensagem(string $code): string
    {
        return match ($code) {
            'ARQUIVO_GRANDE' => 'Imagem excede o limite de 2 MB.',
            'TIPO_INVALIDO' => 'Formato inválido — envie JPG, PNG ou WebP.',
            default => 'Falha no upload — tente novamente.',
        };
    }
}
