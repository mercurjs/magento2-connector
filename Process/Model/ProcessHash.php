<?php

declare(strict_types=1);

namespace Mercur\Process\Model;

class ProcessHash
{
    public static function compute(string $code, array $params): string
    {
        return sha1($code . "\n" . json_encode(self::canonical($params), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function canonical(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonical($item);
            }
        }

        return $value;
    }
}
