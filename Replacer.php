<?php

namespace UniDeal\DynamicReplacements;

use DateTime;
use NumberFormatter;

class Replacer
{
    protected const GROUP_REGEX = '/\{\{\s*([^:|}\s]+)\s*(?::\s*(?:"?([^"}]*)"?|[^|}\s]+))?\s*(?:\|\s*([^:}\s]+)\s*(?::\s*(?:"?([^"]*)"?|[^}\s]+))?)?\s*\}\}/';


    /**
     * @var array<string,callable>
     */
    protected static array $processors = [];

    public static function replaceAll(array $initials, array $replacements): array
    {
        return array_map(function ($initial) use (&$replacements) {
            return static::replace($initial, $replacements);
        }, $initials);
    }

    public static function replace(string $initial, array $replacements): string
    {
        $variables = [];
        preg_match_all(static::GROUP_REGEX, $initial, $variables, PREG_SET_ORDER);

        $replacedVariables = array_map(function (array $match) use (&$replacements) {
            $raw = $match[0];
            $name = trim($match[1]);
            $args = ($match[2] ?? null) ? explode(',', trim($match[2])) : null;

            $processorName = ($match[3] ?? null) ? trim($match[3]) : null;
            $processorArgs = ($match[4] ?? null) ? explode(',', trim($match[4])) : null;

            if (!array_key_exists($name, $replacements)) {
                return ['initial' => $raw, 'replace' => ''];
            }

            $replace = '';
            if (is_string($replacements[$name])) {
                $replace = $replacements[$name];
            }

            if (is_callable($replacements[$name])) {
                $replace = $replacements[$name]($args);
            }


            if (!empty($processorName) && !empty($replace)) {
                if ($processor = static::getProcessor($processorName)) {
                    $replace = $processor($replace, $processorArgs);
                }
            }

            return ['initial' => $raw, 'replace' => $replace];
        }, $variables);

        if (empty($replacedVariables)) {
            return $initial;
        }

        return str_replace(
            array_column($replacedVariables, 'initial'),
            array_column($replacedVariables, 'replace'),
            $initial
        );
    }


    public static function addProcessor(string $name, callable $callback): void
    {
        static::$processors[$name] = $callback;
    }

    public static function getProcessors(): array
    {
        return array_merge(static::getNativeProcessors(), static::$processors);
    }

    /**
     * @param string $name
     * @return callable|null
     */
    public static function getProcessor(string $name): ?callable
    {
        return static::getProcessors()[$name] ?? null;
    }

    public static function getNativeProcessors(): array
    {
        return [
            'date'       => function (string $initial, ?array $args = null) {
                try {
                    return (new DateTime($initial))->format($args[0] ?? 'Y-m-d H:i:s');
                } catch (\Throwable $exception) {
                    return $initial;
                }
            },
            'upper'      => function (string $initial, ?array $args = null) {
                return mb_strtoupper($initial);
            },
            'lower'      => function (string $initial, ?array $args = null) {
                return mb_strtolower($initial);
            },
            'capitalize' => function (string $initial, ?array $args = null) {
                return ucfirst(mb_strtolower($initial));
            },
            'currency'   => function (string $initial, ?array $args = null) {
                $locale = $args[0] ?? 'en_US';
                $currency = $args[1] ?? 'USD';
                try {
                    $fmt = new NumberFormatter($locale, NumberFormatter::CURRENCY);
                    return $fmt->formatCurrency((float)$initial, $currency);
                } catch (\Throwable $e) {
                    return $initial;
                }
            },
            'number'     => function (string $initial, ?array $args = null) {
                $decimals = $args[0] ?? 0;
                $decimal_separator = $args[1] ?? ',';
                $thousands_separator = $args[2] ?? ' ';
                try {
                    return number_format((float)$initial, $decimals, $decimal_separator, $thousands_separator);
                } catch (\Throwable $e) {
                    return $initial;
                }
            },
        ];
    }
}
