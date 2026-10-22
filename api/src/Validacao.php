<?php

declare(strict_types=1);

namespace Adab\Api;

/**
 * Validações compartilhadas entre controllers (antes duplicadas como
 * dataOuNull/horaOuNull privadas — regex só aceitava 9999-99-99).
 */
final class Validacao
{
    private function __construct()
    {
    }

    /** Data ISO 'YYYY-MM-DD' ou null se inválida (inclui checkdate). */
    public static function data(mixed $v): ?string
    {
        $v = trim((string) ($v ?? ''));
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return null;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $v : null;
    }

    /** Hora 'HH:MM[:SS]' ou null se inválida (24h real). */
    public static function hora(mixed $v): ?string
    {
        $v = trim((string) ($v ?? ''));
        if (!preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $v, $m)) {
            return null;
        }
        if ((int) $m[1] > 23 || (int) $m[2] > 59 || (int) ($m[3] ?? 0) > 59) {
            return null;
        }
        return $v;
    }

    /** Comprimento máximo (VARCHAR) — truncar em strict mode vira 500. */
    public static function maxLen(mixed $v, int $max): bool
    {
        return mb_strlen((string) ($v ?? '')) <= $max;
    }
}
