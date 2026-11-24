<?php

namespace Tests\Feature;

use App\Models\Shift;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HR\Http\Controllers\ShiftManagementController;
use Tests\TestCase;

/**
 * تصفيةُ شاشة الورديات ترُدّ جدولاً لا خطأً.
 *
 * **ما كان**
 *
 * `search()` تُنهي استعلامَها بـ`->get()` ثم تُصيّر القالبَ المشترك
 * `partials.shifts_table` — وهو يستدعي `currentPage()` و`lastPage()`
 * و`total()` و`previousPageUrl()`، وكلُّها من المُرقِّم وحدَه. فترمي
 * `Collection::currentPage does not exist`، ويبتلعها `catch` فتردّ 500
 * «حدث خطأ أثناء البحث».
 *
 * أي أن **كلَّ** تصفيةٍ في هذه الشاشة كانت تفشل — بحثاً بالاسم أو
 * بالنوع أو بعدد أيام العمل.
 */
class ShiftManagementSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('shifts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->nullable();
            $t->tinyInteger('type')->default(1);
            $t->timestamps();
        });

        Schema::create('shift_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('shift_id');
            $t->string('day');
            $t->boolean('working_day')->default(true);
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->integer('grace_period')->nullable();
            $t->timestamps();
        });
    }

    private function controller(): ShiftManagementController
    {
        return new class extends ShiftManagementController
        {
            public function __construct() {}
        };
    }

    private function shift(string $name, string $start = '08:00:00', string $end = '16:00:00', ?int $grace = 15): Shift
    {
        $shift = Shift::create(['name' => $name, 'type' => 1]);

        DB::table('shift_days')->insert([
            'shift_id' => $shift->id, 'day' => 'monday', 'working_day' => 1,
            'start_time' => $start, 'end_time' => $end, 'grace_period' => $grace,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $shift;
    }

    /** التصفيةُ تنجح وتُعيد جدولاً — لا 500. */
    public function test_searching_returns_a_rendered_table_not_an_error(): void
    {
        $this->shift('الوردية الصباحية');
        $this->shift('الوردية المسائية');

        $response = $this->controller()->search(Request::create('/', 'GET', ['keywords' => 'الصباحية']));

        $this->assertSame(200, $response->getStatusCode());

        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['success'], $payload['message'] ?? '');
        $this->assertSame(1, $payload['count']);
        $this->assertStringContainsString('الوردية الصباحية', $payload['html']);
        $this->assertStringNotContainsString('الوردية المسائية', $payload['html']);
    }

    /** وبلا مُرشِّح تُعيد الكلَّ مُرقَّماً. */
    public function test_an_unfiltered_search_returns_every_shift(): void
    {
        $this->shift('أ');
        $this->shift('ب');
        $this->shift('ج');

        $payload = json_decode($this->controller()->search(Request::create('/', 'GET'))->getContent(), true);

        $this->assertTrue($payload['success']);
        $this->assertSame(3, $payload['count']);
    }

    /** والجدولُ يُظهر أوقاتَ الدوام ومهلةَ التأخير — وهما ما تُعرَّف الشاشةُ لأجله. */
    public function test_the_table_shows_the_shift_hours_and_grace_period(): void
    {
        $this->shift('الصباحية', '08:00:00', '16:00:00', 30);

        $html = json_decode($this->controller()->search(Request::create('/', 'GET'))->getContent(), true)['html'];

        $this->assertStringContainsString('08:00 – 16:00', $html);
        $this->assertStringContainsString('30 دقيقة', $html);
    }

    /** والورديةُ الليلية تُعلَّم: نهايةٌ تسبق البداية تمتدّ لليوم التالي. */
    public function test_a_shift_crossing_midnight_is_flagged(): void
    {
        $this->shift('الليلية', '22:00:00', '06:00:00', 15);

        $html = json_decode($this->controller()->search(Request::create('/', 'GET'))->getContent(), true)['html'];

        $this->assertStringContainsString('تمتد لليوم التالي', $html);
    }

    /** ووردِيّةٌ نهاريّةٌ لا تُعلَّم. */
    public function test_a_day_shift_is_not_flagged_as_crossing_midnight(): void
    {
        $this->shift('الصباحية', '08:00:00', '16:00:00', 15);

        $html = json_decode($this->controller()->search(Request::create('/', 'GET'))->getContent(), true)['html'];

        $this->assertStringNotContainsString('تمتد لليوم التالي', $html);
    }

    /** ومُنتقي «عرض … سجل» يعمل: كان `paginate(10)` ثابتاً يتجاهله. */
    public function test_the_per_page_selector_is_honoured(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->shift('وردية '.$i);
        }

        $ten = $this->controller()->index(Request::create('/', 'GET'))->getData()['shifts'];
        $this->assertCount(10, $ten);

        $fifty = $this->controller()->index(Request::create('/', 'GET', ['per_page' => 50]))->getData()['shifts'];
        $this->assertCount(12, $fifty);
    }
}
