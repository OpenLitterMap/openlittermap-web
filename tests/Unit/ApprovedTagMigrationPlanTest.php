<?php
namespace Tests\Unit;

use App\Services\Tags\ApprovedTagMigrationPlan;
use PHPUnit\Framework\TestCase;

class ApprovedTagMigrationPlanTest extends TestCase
{
    private function row(string $category): array
    {
        return ['source_category' => $category, 'source_object' => 'straws', 'destination_category' => $category,
            'destination_object' => 'straw', 'type' => '', 'allow_xp_change' => 'no', 'status' => 'APPROVED', 'approved_by' => 'reviewer'];
    }
    public function test_whole_object_categories_become_one_structured_command(): void
    {
        $plan = ApprovedTagMigrationPlan::commands([$this->row('marine'), $this->row('softdrinks')], 'straws');
        $this->assertSame(['retired' => 'straws', 'desired' => 'straw'], $plan['arguments']);
        $this->assertCount(2, $plan['categories']);
    }
    public function test_conflicting_category_plan_is_refused(): void
    {
        $row = $this->row('marine'); $row['destination_object'] = 'other';
        $this->expectException(\InvalidArgumentException::class);
        ApprovedTagMigrationPlan::commands([$row, $this->row('softdrinks')], 'straws');
    }
    public function test_partial_approval_is_refused(): void
    {
        $row = $this->row('marine'); $row['status'] = 'PENDING';
        $this->expectException(\InvalidArgumentException::class);
        ApprovedTagMigrationPlan::commands([$row, $this->row('softdrinks')], 'straws');
    }
    public function test_a_review_that_omits_a_prepared_category_is_refused(): void
    {
        $plan = ApprovedTagMigrationPlan::commands([$this->row('marine'), $this->row('softdrinks')], 'straws', ['softdrinks', 'marine']);
        $this->assertSame(['marine', 'softdrinks'], $plan['categories']);
        $this->expectException(\InvalidArgumentException::class);
        ApprovedTagMigrationPlan::commands([$this->row('marine')], 'straws', ['marine', 'softdrinks']);
    }
}
