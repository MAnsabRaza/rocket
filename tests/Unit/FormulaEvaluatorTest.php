<?php

namespace Tests\Unit;

use App\Services\IsoContent\FormulaEvaluator;
use PHPUnit\Framework\TestCase;

class FormulaEvaluatorTest extends TestCase
{
    private FormulaEvaluator $eval;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eval = new FormulaEvaluator();
    }

    public function test_basic_arithmetic(): void
    {
        $this->assertEquals(14, $this->eval->evaluate('2 + 3 * 4', []));
        $this->assertEquals(20, $this->eval->evaluate('(2 + 3) * 4', []));
    }

    public function test_variable_resolution(): void
    {
        $ctx = ['completed_audits' => 8, 'planned_audits' => 10];
        $this->assertEqualsWithDelta(80.0, $this->eval->evaluate('(completed_audits / planned_audits) * 100', $ctx), 0.0001);
    }

    public function test_aggregates(): void
    {
        $this->assertEquals(10, $this->eval->evaluate('sum(2, 3, 5)', []));
        $this->assertEquals(5,  $this->eval->evaluate('avg(2, 4, 9)', []));
    }

    public function test_division_by_zero_returns_zero(): void
    {
        $this->assertEquals(0, $this->eval->evaluate('a / b', ['a' => 5, 'b' => 0]));
    }
}