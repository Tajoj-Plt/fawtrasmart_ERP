<?php

namespace Tests\Feature;

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * يغطّي سلوك الشجرة الذي تعتمد عليه كل شاشات الموديول: توليد الأكواد،
 * كشف الأحفاد، وإفراغ الذاكرة بعد النقل. كانت هذه المنطقة بلا اختبار واحد
 * رغم أن خطأً فيها ينقل عملاء إلى الفرع الخطأ بصمت.
 */
class BranchTreeTest extends TestCase
{
    use RefreshDatabase;

    private function makeBranch(string $name, ?int $parentId = null): Branch
    {
        return Branch::create([
            'name' => $name,
            'code' => Branch::generateTreeCode($parentId),
            'parent_id' => $parentId,
            'status' => 0,
            'is_main' => false,
        ]);
    }

    public function test_root_codes_are_sequential_integers(): void
    {
        $first = $this->makeBranch('الرياض');
        $second = $this->makeBranch('جدة');

        $this->assertSame('1', $first->code);
        $this->assertSame('2', $second->code);
    }

    public function test_child_code_extends_parent_code(): void
    {
        $root = $this->makeBranch('الرياض');
        $childA = $this->makeBranch('الشمال', $root->id);
        $childB = $this->makeBranch('الجنوب', $root->id);
        $grandchild = $this->makeBranch('حي النخيل', $childA->id);

        $this->assertSame('11', $childA->code);
        $this->assertSame('12', $childB->code);
        $this->assertSame('111', $grandchild->code);
    }

    public function test_tenth_sibling_uses_letter_suffix_to_avoid_colliding_with_grandchildren(): void
    {
        // الابن العاشر لو أخذ اللاحقة «10» لصار كوده 110 وهو كود يخصّ حفيداً
        // تحت 11. لذلك يبدأ الترقيم بالحروف من العاشر فما فوق.
        $root = $this->makeBranch('الرياض');
        for ($i = 1; $i <= 9; $i++) {
            $this->makeBranch("منطقة {$i}", $root->id);
        }
        $tenth = $this->makeBranch('منطقة 10', $root->id);

        $this->assertSame('1A', $tenth->code);
    }

    public function test_descendant_ids_include_all_depths(): void
    {
        $root = $this->makeBranch('الرياض');
        $child = $this->makeBranch('الشمال', $root->id);
        $grandchild = $this->makeBranch('حي النخيل', $child->id);

        $ids = $root->descendantBranchIds();

        $this->assertTrue($ids->contains($child->id));
        $this->assertTrue($ids->contains($grandchild->id));
        $this->assertCount(2, $ids);
    }

    public function test_descendants_are_complete_even_after_ancestors_partially_filled_the_cache(): void
    {
        // الانحدار الذي كان قائماً: ancestors() تملأ $branchCache جزئياً،
        // وdescendantBranchIds() كانت تعتبر أي محتوى فيه مجموعة كاملة فتحسب
        // الأحفاد من عيّنة ناقصة وتُرجع شجرة أصغر من الحقيقة.
        $root = $this->makeBranch('الرياض');
        $child = $this->makeBranch('الشمال', $root->id);
        $grandchild = $this->makeBranch('حي النخيل', $child->id);

        Branch::flushTreeCaches();
        $grandchild->ancestors();               // يملأ الذاكرة جزئياً

        $this->assertCount(2, $root->descendantBranchIds());
    }

    public function test_moving_a_branch_regenerates_codes_for_its_whole_subtree(): void
    {
        $riyadh = $this->makeBranch('الرياض');
        $jeddah = $this->makeBranch('جدة');
        $north = $this->makeBranch('الشمال', $riyadh->id);
        $palm = $this->makeBranch('حي النخيل', $north->id);

        $this->assertSame('11', $north->code);
        $this->assertSame('111', $palm->code);

        $north->update(['parent_id' => $jeddah->id]);
        $north->refresh();
        $north->regenerateSubtreeCodes();
        Branch::flushTreeCaches();

        // نتحقّق من الثابت لا من اللاحقة الحرفية: المولّد يتخطّى أي كود محجوز
        // عالمياً، فتثبيت «21» يجعل الاختبار يفشل على قاعدة غير فارغة.
        $northCode = $north->fresh()->code;
        $this->assertStringStartsWith($jeddah->code, $northCode);
        $this->assertStringStartsNotWith($riyadh->code, $northCode);
        $this->assertStringStartsWith($northCode, $palm->fresh()->code);
    }

    public function test_lineage_path_reads_from_root_to_node(): void
    {
        $root = $this->makeBranch('الرياض');
        $child = $this->makeBranch('الشمال', $root->id);
        $grandchild = $this->makeBranch('حي النخيل', $child->id);

        $this->assertSame('الرياض ← الشمال ← حي النخيل', $grandchild->lineagePathLabel());
    }
}
