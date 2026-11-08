<?php

namespace App\Services\Lms;

/**
 * Short-answer comparison. Deliberately more forgiving than a raw string compare, and still honest about its limits:
 * it does NOT understand synonyms, spelling mistakes or free text - those answers need a teacher (manual regrade).
 *
 *  - Unicode-normalised, trimmed, internal whitespace collapsed, trailing full stops / commas ignored;
 *  - case-insensitive unless the question says otherwise;
 *  - numbers compare as numbers ("1,5" = "1.50" = " 1.5 "), so a decimal comma is not a wrong answer.
 */
class AnswerMatcher
{
    /** @param array<int,string> $accepted */
    public function matches(?string $given, array $accepted, bool $caseSensitive = false): bool
    {
        $g = $this->normalise($given, $caseSensitive);
        if ($g === '') {
            return false;
        }
        $gNum = $this->number($g);
        foreach ($accepted as $a) {
            $n = $this->normalise((string) $a, $caseSensitive);
            if ($n === '') {
                continue;
            }
            if ($n === $g) {
                return true;
            }
            $aNum = $this->number($n);
            if ($gNum !== null && $aNum !== null && abs($gNum - $aNum) < 1e-9) {
                return true;
            }
        }

        return false;
    }

    public function normalise(?string $value, bool $caseSensitive = false): string
    {
        $v = (string) $value;
        if (class_exists(\Normalizer::class)) {
            $v = \Normalizer::normalize($v, \Normalizer::FORM_C) ?: $v;
        }
        $v = trim(preg_replace('/\s+/u', ' ', $v) ?? $v);
        $v = rtrim($v, ".,;:! \t");

        return $caseSensitive ? $v : mb_strtolower($v);
    }

    protected function number(string $v): ?float
    {
        $c = str_replace(' ', '', $v);
        if (preg_match('/^[+-]?\d{1,3}(,\d{3}){2,}(\.\d+)?$/', $c) || preg_match('/^[+-]?\d{1,3}(,\d{3})+\.\d+$/', $c)) {
            $c = str_replace(',', '', $c);                  // unambiguous thousands separators: 1,234,567 / 1,234.5
        } elseif (preg_match('/^[+-]?\d+,\d{3}$/', $c)) {
            return null;                                     // "1,000" is 1 in French and 1000 in English: never guess, a teacher decides
        } elseif (preg_match('/^[+-]?\d+,\d+$/', $c)) {
            $c = str_replace(',', '.', $c);                  // decimal comma: 1,5
        }

        return preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', $c) ? (float) $c : null;
    }
}
