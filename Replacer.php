<?php

namespace UniDeal\DynamicReplacements;

class Replacer
{
    protected const GROUP_REGEX = "/\{\{([^:}]+)(?::([^}]+))?\}\}/";

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
            $name = $match[1];
            $arg = ($match[2] ?? null) ? explode(',', $match[2]) : null;

            if (!array_key_exists($name, $replacements)) {
                return ['initial' => $raw, 'replace' => ''];
            }

            if (is_string($replacements[$name])) {
                return ['initial' => $raw, 'replace' => $replacements[$name]];
            }

            if (!is_callable($replacements[$name])) {
                return ['initial' => $raw, 'replace' => ''];
            }
            return ['initial' => $raw, 'replace' => $replacements[$name]($arg)];
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
}
