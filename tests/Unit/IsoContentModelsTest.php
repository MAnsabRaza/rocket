<?php

namespace Tests\Unit;

use App\Models\IsoContentChecklist;
use App\Models\IsoContentForm;
use App\Models\IsoContentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsoContentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_initial_state_and_transitions(): void
    {
        $w = IsoContentWorkflow::create([
            'technical_key' => 'test',
            'category'      => 'test',
            'name'          => ['en' => 'Test'],
            'states'        => [
                ['key' => 'a', 'kind' => 'initial'],
                ['key' => 'b', 'kind' => 'final'],
            ],
            'transitions'   => [['from' => 'a', 'to' => 'b']],
        ]);

        $this->assertSame('a', $w->initialState()['key']);
        $this->assertCount(1, $w->transitionsFrom('a'));
        $this->assertCount(0, $w->transitionsFrom('b'));
    }

    public function test_checklist_all_questions_flattens_schema(): void
    {
        $c = IsoContentChecklist::create([
            'technical_key' => 't',
            'name'          => ['en' => 'T'],
            'schema'        => [
                ['key' => 's1', 'questions' => [['key' => 'q1'], ['key' => 'q2']]],
                ['key' => 's2', 'questions' => [['key' => 'q3']]],
            ],
        ]);
        $this->assertSame(3, $c->questionCount());
    }

    public function test_form_all_fields_flattens_schema(): void
    {
        $f = IsoContentForm::create([
            'technical_key' => 't',
            'category'      => 't',
            'name'          => ['en' => 'T'],
            'schema'        => [
                ['key' => 's1', 'fields' => [['key' => 'a'], ['key' => 'b']]],
            ],
        ]);
        $this->assertCount(2, $f->allFields());
    }
}