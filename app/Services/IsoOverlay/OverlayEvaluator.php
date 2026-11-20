<?php

namespace App\Services\IsoOverlay;

use App\Models\IsoOverlayItem;

/**
 * Applies the rules stored in KPI and risk items of an overlay version.
 * Generic: every KPI and every risk, for every standard, is evaluated by the same
 * code from its own stored definition (decision 58). Nothing here writes data.
 */
class OverlayEvaluator
{
    public const ON_TARGET = 'on_target';
    public const BELOW_TARGET = 'below_target';
    public const WARNING = 'warning';
    public const CRITICAL = 'critical';

    /**
     * Classify a measured KPI value against the item's target and thresholds.
     *
     * higher_is_better: value >= target on_target; >= warning below_target; >= critical warning; else critical.
     * lower_is_better:  value <= target on_target; <= warning below_target; <= critical warning; else critical.
     */
    public function kpiStatus(IsoOverlayItem $item, $value): array
    {
        $this->assertType($item, 'kpi');

        if (!is_numeric($value)) {
            throw new OverlayException('KPI value must be a number.');
        }

        $d = $item->data;
        $v = (float)$value;
        $target = (float)$d['target'];
        $warning = (float)$d['thresholds']['warning'];
        $critical = (float)$d['thresholds']['critical'];
        $higher = $d['direction'] === 'higher_is_better';

        $meets = fn(float $limit) => $higher ? $v >= $limit : $v <= $limit;

        if ($meets($target)) {
            $status = self::ON_TARGET;
        } elseif ($meets($warning)) {
            $status = self::BELOW_TARGET;
        } elseif ($meets($critical)) {
            $status = self::WARNING;
        } else {
            $status = self::CRITICAL;
        }

        return [
            'key' => $item->key,
            'value' => $v,
            'unit' => $d['unit'],
            'target' => $target,
            'direction' => $d['direction'],
            'status' => $status,
        ];
    }

    /**
     * Score a risk from probability and impact using the item's scale, rule and levels.
     */
    public function riskScore(IsoOverlayItem $item, $probability, $impact): array
    {
        $this->assertType($item, 'risk');
        $d = $item->data;

        foreach (['probability' => [$probability, $d['probability_scale']], 'impact' => [$impact, $d['impact_scale']]] as $name => [$given, $scale]) {
            if (!is_numeric($given) || (int)$given != $given || (int)$given < 1 || (int)$given > (int)$scale) {
                throw new OverlayException("Risk {$name} must be a whole number from 1 to {$scale}.");
            }
        }

        $p = (int)$probability;
        $i = (int)$impact;
        $score = $d['scoring_rule'] === 'multiply' ? $p * $i : $p + $i;

        $level = null;
        foreach ($d['levels'] as $candidate) {
            if ($score <= (float)$candidate['max_score']) {
                $level = $candidate['label'];
                break;
            }
        }

        return [
            'key' => $item->key,
            'probability' => $p,
            'impact' => $i,
            'scoring_rule' => $d['scoring_rule'],
            'score' => $score,
            'level' => $level,
        ];
    }

    /**
     * Residual risk: the same scoring applied to the probability and impact after mitigation.
     */
    public function residualRisk(IsoOverlayItem $item, $probability, $impact, $residualProbability, $residualImpact): array
    {
        $inherent = $this->riskScore($item, $probability, $impact);
        $residual = $this->riskScore($item, $residualProbability, $residualImpact);

        return [
            'key' => $item->key,
            'inherent' => $inherent,
            'residual' => $residual,
            'reduction' => $inherent['score'] - $residual['score'],
            'mitigation' => $item->data['mitigation'] ?? null,
        ];
    }

    private function assertType(IsoOverlayItem $item, string $type): void
    {
        if ($item->type !== $type) {
            throw new OverlayException("Item {$item->key} is a {$item->type}, not a {$type}.");
        }
    }
}
