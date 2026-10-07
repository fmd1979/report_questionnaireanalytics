<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace mod_surveypulse;
defined('MOODLE_INTERNAL') || die();
/** Server-side question validation shared by editor and submission. */
class question {
    public static function types(): array {
        return ['single', 'multiple', 'yesno', 'scale', 'numeric', 'text'];
    }
    public static function choices(object $question): array {
        if ($question->qtype === 'yesno') {
            return [1 => get_string('yes'), 2 => get_string('no')];
        }
        if ($question->qtype === 'scale') {
            return [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'];
        }
        $choices = [];
        foreach (preg_split('/\R/u', trim($question->options)) as $text) {
            if (trim($text) !== '') {
                $choices[count($choices) + 1] = trim($text);
            }
        }
        return $choices;
    }
    /** Return normalized answer strings, or throw if invalid. Blank optional answers return []. */
    public static function normalize(object $question, $input): array {
        $values = is_array($input) ? $input : [$input];
        $values = array_values(array_unique(array_map(static function($v) {
            if (!is_scalar($v) && $v !== null) {
                throw new \InvalidArgumentException('Invalid answer shape');
            }
            return trim((string)$v);
        }, $values)));
        $values = array_values(array_filter($values, static fn($v) => $v !== ''));
        if (!$values) {
            if ($question->required) {
                throw new \InvalidArgumentException('Required answer');
            }
            return [];
        }
        if ($question->qtype !== 'multiple' && count($values) !== 1) {
            throw new \InvalidArgumentException('One answer required');
        }
        if (in_array($question->qtype, ['single', 'multiple', 'scale', 'yesno'], true)) {
            $choices = self::choices($question);
            foreach ($values as $value) {
                if (!ctype_digit($value) || !isset($choices[(int)$value]) || (string)(int)$value !== $value) {
                    throw new \InvalidArgumentException('Unknown option');
                }
            }
        } else if ($question->qtype === 'numeric') {
            if (!is_numeric($values[0]) || !is_finite((float)$values[0]) || abs((float)$values[0]) > 1.0e12) {
                throw new \InvalidArgumentException('Invalid number');
            }
            $values[0] = (string)(float)$values[0];
        } else if ($question->qtype === 'text') {
            if (\core_text::strlen($values[0]) > 4000) {
                throw new \InvalidArgumentException('Text too long');
            }
        } else {
            throw new \InvalidArgumentException('Unknown question type');
        }
        return $values;
    }
}
