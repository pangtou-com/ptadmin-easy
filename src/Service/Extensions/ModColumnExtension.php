<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Service\Extensions;

use PTAdmin\Easy\Exceptions\InvalidDataException;

class ModColumnExtension
{
    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private static array $extensions = [];

    /**
     * @param array<int, array<string, mixed>> $extensions
     */
    public static function setExtension(string $modName, array $extensions): void
    {
        self::$extensions[$modName] = array_values($extensions);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getExtension(string $modName): array
    {
        return self::$extensions[$modName] ?? [];
    }

    /**
     * @param array<int, array<string, mixed>> $extensions
     */
    public static function insertExtension(string $modName, array $extensions): void
    {
        self::$extensions[$modName] = array_values(array_merge(
            self::getExtension($modName),
            $extensions
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $extensions
     */
    public static function checkExtension(array $extensions): void
    {
        $names = [];

        foreach ($extensions as $extension) {
            $name = trim((string) ($extension['name'] ?? ''));
            if ('' === $name) {
                throw new InvalidDataException('Column extension name is required.');
            }

            if (isset($names[$name])) {
                throw new InvalidDataException('Column extension name must be unique.');
            }

            $names[$name] = true;
        }
    }
}
