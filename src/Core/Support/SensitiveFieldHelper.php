<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Support;

use Illuminate\Support\Str;

final class SensitiveFieldHelper
{
    /**
     * @param array<string, mixed> $metadata
     */
    public static function isSecret(array $metadata): bool
    {
        return true === (bool) ($metadata['secret'] ?? false);
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>|null
     */
    public static function maskConfigFromMetadata(array $metadata): ?array
    {
        return self::normalizeMaskConfig($metadata['mask'] ?? null);
    }

    /**
     * @param mixed $mask
     *
     * @return array<string, mixed>|null
     */
    public static function normalizeMaskConfig($mask): ?array
    {
        if (null === $mask || false === $mask) {
            return null;
        }

        if (true === $mask) {
            $mask = [];
        }

        if (!\is_array($mask)) {
            return null;
        }

        $strategy = strtolower(trim((string) ($mask['strategy'] ?? 'custom')));
        if (!\in_array($strategy, ['custom', 'phone', 'email', 'idcard'], true)) {
            $strategy = 'custom';
        }

        $defaults = self::strategyDefaults($strategy);

        return [
            'strategy' => $strategy,
            'keepStart' => self::normalizeLength($mask['keepStart'] ?? null, $defaults['keepStart']),
            'keepEnd' => self::normalizeLength($mask['keepEnd'] ?? null, $defaults['keepEnd']),
            'maskChar' => self::normalizeMaskChar($mask['maskChar'] ?? null),
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function applyMask(?string $value, array $config): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        $strategy = (string) ($config['strategy'] ?? 'custom');
        if ('email' === $strategy && false !== strpos($value, '@')) {
            return self::maskEmail($value, $config);
        }

        return self::maskString($value, $config);
    }

    /**
     * @return array{keepStart:int,keepEnd:int}
     */
    private static function strategyDefaults(string $strategy): array
    {
        switch ($strategy) {
            case 'phone':
                return ['keepStart' => 3, 'keepEnd' => 4];

            case 'idcard':
                return ['keepStart' => 6, 'keepEnd' => 4];

            case 'email':
                return ['keepStart' => 1, 'keepEnd' => 0];

            case 'custom':
            default:
                return ['keepStart' => 3, 'keepEnd' => 3];
        }
    }

    private static function normalizeLength($value, int $default): int
    {
        if (\is_numeric($value)) {
            return max(0, (int) $value);
        }

        return $default;
    }

    private static function normalizeMaskChar($value): string
    {
        if (\is_string($value) && '' !== $value) {
            return Str::substr($value, 0, 1);
        }

        return '*';
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function maskString(string $value, array $config): string
    {
        $length = Str::length($value);
        if (0 === $length) {
            return $value;
        }

        $keepStart = min((int) ($config['keepStart'] ?? 0), $length);
        $keepEnd = min((int) ($config['keepEnd'] ?? 0), max(0, $length - $keepStart));
        $maskChar = (string) ($config['maskChar'] ?? '*');
        $maskedLength = $length - $keepStart - $keepEnd;
        if ($maskedLength <= 0) {
            return str_repeat($maskChar, max(1, $length));
        }

        return Str::substr($value, 0, $keepStart)
            .str_repeat($maskChar, $maskedLength)
            .Str::substr($value, $length - $keepEnd, $keepEnd);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function maskEmail(string $value, array $config): string
    {
        [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');
        if ('' === $local || '' === $domain) {
            return self::maskString($value, $config);
        }

        return self::maskString($local, $config).'@'.$domain;
    }
}
