<?php

declare(strict_types=1);

namespace Candidate\SeoAudit;

use RuntimeException;

final class CatalogDetailProperties
{
    /**
     * Adds property codes to DETAIL_PROPERTY_CODE without changing other catalog parameters.
     */
    public static function withRequiredCodes(string $contents, array $requiredCodes): string
    {
        $pattern = '/(?<opening>^[\t ]*["\']DETAIL_PROPERTY_CODE["\']\s*=>\s*array\(\R)(?<body>.*?)(?<closing>^[\t ]*\),)/ms';
        $replacements = 0;

        $result = preg_replace_callback(
            $pattern,
            static function (array $matches) use ($requiredCodes): string {
                preg_match_all('/=>\s*["\']([^"\']+)["\']/', $matches['body'], $codeMatches);
                $codes = array_values(array_filter(array_map(
                    static fn($code): string => strtoupper(trim((string)$code)),
                    $codeMatches[1] ?? []
                )));

                foreach ($requiredCodes as $requiredCode) {
                    $requiredCode = strtoupper(trim((string)$requiredCode));
                    if ($requiredCode !== '' && !in_array($requiredCode, $codes, true)) {
                        $codes[] = $requiredCode;
                    }
                }

                $lineEnding = str_contains($matches[0], "\r\n") ? "\r\n" : "\n";
                preg_match('/^([\t ]*)\d+\s*=>/m', $matches['body'], $indentMatch);
                $indent = $indentMatch[1] ?? '                ';
                $body = '';
                foreach ($codes as $index => $code) {
                    $body .= sprintf('%s%d => "%s",%s', $indent, $index, $code, $lineEnding);
                }

                return $matches['opening'] . $body . $matches['closing'];
            },
            $contents,
            1,
            $replacements
        );

        if ($result === null || $replacements !== 1) {
            throw new RuntimeException('Не удалось найти параметр DETAIL_PROPERTY_CODE в странице каталога.');
        }

        return $result;
    }

    public static function updateFile(string $path, array $requiredCodes): bool
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Не удалось прочитать файл каталога %s.', $path));
        }

        $updated = self::withRequiredCodes($contents, $requiredCodes);
        if ($updated === $contents) {
            return false;
        }

        if (file_put_contents($path, $updated, LOCK_EX) === false) {
            throw new RuntimeException(sprintf('Не удалось обновить файл каталога %s.', $path));
        }

        return true;
    }
}
