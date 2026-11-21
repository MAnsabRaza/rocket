<?php

namespace App\Services\IsoContent;

class FormulaEvaluator
{
    public function evaluate(string $formula, array $context): float|int
    {
        $tokens = $this->tokenize($formula);
        $pos    = 0;
        return $this->parseExpression($tokens, $pos, $context);
    }

    private function tokenize(string $f): array
    {
        preg_match_all('/[a-z_][a-z0-9_]*|\d+(?:\.\d+)?|[+\-*\/(),]/i', $f, $m);
        return $m[0];
    }

    private function parseExpression(array $t, int &$p, array $ctx): float|int
    {
        $left = $this->parseTerm($t, $p, $ctx);
        while (isset($t[$p]) && in_array($t[$p], ['+','-'], true)) {
            $op = $t[$p++];
            $right = $this->parseTerm($t, $p, $ctx);
            $left = $op === '+' ? $left + $right : $left - $right;
        }
        return $left;
    }

    private function parseTerm(array $t, int &$p, array $ctx): float|int
    {
        $left = $this->parseFactor($t, $p, $ctx);
        while (isset($t[$p]) && in_array($t[$p], ['*','/'], true)) {
            $op = $t[$p++];
            $right = $this->parseFactor($t, $p, $ctx);
            if ($op === '*') $left *= $right;
            else {
                if ($right == 0) return 0;
                $left /= $right;
            }
        }
        return $left;
    }

    private function parseFactor(array $t, int &$p, array $ctx): float|int
    {
        $tok = $t[$p] ?? null;
        if ($tok === '(') { $p++; $v = $this->parseExpression($t, $p, $ctx); if (($t[$p] ?? null) === ')') $p++; return $v; }
        if (is_numeric($tok)) { $p++; return $tok + 0; }
        if (is_string($tok) && preg_match('/^[a-z_][a-z0-9_]*$/i', $tok)) {
            $name = strtolower($tok); $p++;
            if (in_array($name, ['avg','sum','min','max','count'], true) && ($t[$p] ?? null) === '(') {
                $p++;
                $args = [];
                while (($t[$p] ?? null) !== ')' && $t[$p] !== null) {
                    $args[] = $this->parseExpression($t, $p, $ctx);
                    if (($t[$p] ?? null) === ',') $p++;
                }
                if (($t[$p] ?? null) === ')') $p++;
                return $this->applyAggregate($name, $args);
            }
            return (float) ($ctx[$name] ?? 0);
        }
        return 0;
    }

    private function applyAggregate(string $fn, array $args): float|int
    {
        return match ($fn) {
            'sum'   => array_sum($args),
            'avg'   => count($args) ? array_sum($args) / count($args) : 0,
            'min'   => !empty($args) ? min($args) : 0,
            'max'   => !empty($args) ? max($args) : 0,
            'count' => count($args),
            default => 0,
        };
    }
}