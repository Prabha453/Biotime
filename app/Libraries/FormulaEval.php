<?php

namespace App\Libraries;

/**
 * Safe evaluator for ZKBioTime-style payroll formulas.
 *
 * A formula is an arithmetic expression over {Placeholder} tokens, e.g.
 *   ((({Basic Salary}/30)/8)*1.5)*{NOT}
 *   (({Basic Salary}/30)/8)/60*{L}
 *
 * Only  + - * / ( )  and decimal numbers survive tokenisation, so there is
 * no code-execution surface — tokens are substituted with numeric context
 * values first, then the remaining pure-maths string is evaluated by a
 * small shunting-yard parser (no eval()).
 */
class FormulaEval
{
    /**
     * @param string               $formula  raw formula text
     * @param array<string,float>   $context  'Basic Salary' => 5000, 'NOT' => 12, …
     */
    public static function run(string $formula, array $context): ?float
    {
        // {Token} -> numeric value (0 when the context has no such key)
        $expr = preg_replace_callback('/\{([^}]+)\}/', static function ($m) use ($context) {
            $key = trim($m[1]);
            $val = $context[$key] ?? 0;
            foreach ($context as $k => $v) {                 // case-insensitive fallback
                if (strcasecmp($k, $key) === 0) {
                    $val = $v;
                    break;
                }
            }

            return '(' . (float) $val . ')';
        }, $formula);

        // strip everything that is not maths
        if ($expr === null || preg_match('/[^0-9.+\-*\/()\s]/', $expr)) {
            return null;
        }

        try {
            return self::evalMath($expr);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Placeholders a formula author may use, with a one-line meaning. */
    public static function tokens(): array
    {
        return [
            'Basic Salary' => 'Employee monthly base salary',
            'NOT'          => 'Normal (weekday) overtime hours',
            'WOT'          => 'Weekend overtime hours',
            'HOT'          => 'Holiday overtime hours',
            'L'            => 'Late-in minutes in the period',
            'E'            => 'Early-out minutes in the period',
            'A'            => 'Absent days in the period',
            'Present'      => 'Present days in the period',
            'Leave'        => 'Leave days for this pay code',
            'WorkDays'     => 'Standard working days per month',
        ];
    }

    // ------------------------------------------------------------ internals

    /** Shunting-yard evaluation of a + - * / ( ) expression. */
    private static function evalMath(string $s): float
    {
        $tokens = self::lex($s);
        $out    = [];
        $ops    = [];
        $prec   = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, 'u-' => 3];

        $prev = null;
        foreach ($tokens as $t) {
            if (is_numeric($t)) {
                $out[] = (float) $t;
            } elseif ($t === '(') {
                $ops[] = $t;
            } elseif ($t === ')') {
                while ($ops && end($ops) !== '(') {
                    self::apply($out, array_pop($ops));
                }
                array_pop($ops); // '('
            } else {
                if ($t === '-' && ($prev === null || $prev === '(' || isset($prec[$prev]))) {
                    $t = 'u-';
                }
                while ($ops && end($ops) !== '(' && $prec[end($ops)] >= $prec[$t]) {
                    self::apply($out, array_pop($ops));
                }
                $ops[] = $t;
            }
            $prev = $t;
        }
        while ($ops) {
            self::apply($out, array_pop($ops));
        }

        return (float) ($out[0] ?? 0);
    }

    private static function apply(array &$out, string $op): void
    {
        if ($op === 'u-') {
            $out[] = -array_pop($out);

            return;
        }
        $b = array_pop($out);
        $a = array_pop($out);
        $out[] = match ($op) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => $b == 0.0 ? 0.0 : $a / $b,
            default => 0.0,
        };
    }

    /** @return string[] */
    private static function lex(string $s): array
    {
        preg_match_all('/\d+\.?\d*|[+\-*\/()]/', $s, $m);

        return $m[0];
    }
}
