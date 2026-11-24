<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * هجرةُ نسخ login_time/logout_time التاريخية إلى check_in_time/check_out_time.
 *
 * الهجرةُ نفسُها (لا محاكاةٌ لمنطقها) تُحمَّل وتُشغَّل هنا مباشرةً — بخلاف
 * هجرات MySQL الخام الأخرى في هذا المشروع، هذه الهجرة استعلامُ Eloquent
 * عاديّ فقط (`DB::table(...)->update(...)`) فتعمل على sqlite دون حاجةٍ
 * لجداولَ يدوية موازية لمنطقها.
 */
class AttendanceDaysBackfillMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('attendance_days', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('attendance_date');
            $t->time('check_in_time')->nullable();
            $t->time('check_out_time')->nullable();
            $t->time('login_time')->nullable();
            $t->time('logout_time')->nullable();
            $t->timestamps();
        });
    }

    private function migration()
    {
        return require base_path('database/migrations/2026_09_01_090200_backfill_attendance_days_check_in_out_from_login_out.php');
    }

    public function test_it_copies_login_time_into_an_empty_check_in_time(): void
    {
        DB::table('attendance_days')->insert([
            'employee_id' => 1, 'attendance_date' => '2025-01-01',
            'login_time' => '08:15:00', 'logout_time' => '16:15:00',
            'check_in_time' => null, 'check_out_time' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration()->up();

        $day = DB::table('attendance_days')->first();

        $this->assertSame('08:15:00', $day->check_in_time);
        $this->assertSame('16:15:00', $day->check_out_time);
        // العمودان القديمان يبقيان كما هما — نسخٌ لا نقل.
        $this->assertSame('08:15:00', $day->login_time);
        $this->assertSame('16:15:00', $day->logout_time);
    }

    /** يومٌ سجَّله الباركود بالفعل — check_in_time موجود — لا يُستبدَل بشيء. */
    public function test_it_does_not_overwrite_an_existing_check_in_time(): void
    {
        DB::table('attendance_days')->insert([
            'employee_id' => 1, 'attendance_date' => '2025-01-01',
            'login_time' => null, 'logout_time' => null,
            'check_in_time' => '09:00:00', 'check_out_time' => '17:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration()->up();

        $day = DB::table('attendance_days')->first();

        $this->assertSame('09:00:00', $day->check_in_time);
        $this->assertSame('17:00:00', $day->check_out_time);
    }

    /** يومٌ بلا حضور أصلاً: يبقى الجميع فارغاً. */
    public function test_it_leaves_a_day_with_neither_column_set_alone(): void
    {
        DB::table('attendance_days')->insert([
            'employee_id' => 1, 'attendance_date' => '2025-01-01',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migration()->up();

        $day = DB::table('attendance_days')->first();

        $this->assertNull($day->check_in_time);
        $this->assertNull($day->check_out_time);
    }
}
