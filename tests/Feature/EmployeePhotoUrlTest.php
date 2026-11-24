<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * عنوانُ صورة الموظف — مصدرٌ واحدٌ لا ثلاثة.
 *
 * **ما كان**
 *
 * الرفعُ يذهب إلى `assets/uploads/employee/` (انظر
 * `EmployeeController::store/update`)، وأكثرُ الشاشات تقرأ من هناك.
 * لكنّ **واجهة جهاز الفرع** و**شاشة التسجيل الميداني** كانتا تبنيان
 * العنوانَ بأيديهما من `storage/` — وهو وصلةٌ إلى `storage/app/public`،
 * مجلّدٌ آخر لا توجد فيه صورةُ موظفٍ واحدة.
 *
 * فقسمُ «معلومات الموظف» في شاشة المسح يعرض أيقونةَ صورةٍ مكسورةٍ عند
 * كل مسحة. والاحتياطيُّ نفسُه (`/images/default-avatar.png`) لم يكن
 * موجوداً على القرص — فمن لا صورةَ له يرى كسراً كذلك.
 *
 * **ما يقيسه هذا الاختبار**
 *
 * أن العنوان يُبنى من مجلّد الرفع، وأن ملفّاً مفقوداً يُرَدّ بـ`null`
 * لا بعنوانٍ مكسور، وأن الاحتياطيَّ موجودٌ فعلاً على القرص.
 */
class EmployeePhotoUrlTest extends TestCase
{
    private string $uploaded = '';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('nickname')->nullable();
            $t->string('email')->unique();
            $t->string('employee_photo')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if ($this->uploaded !== '' && is_file($this->uploaded)) {
            @unlink($this->uploaded);
        }

        parent::tearDown();
    }

    private function employee(?string $photo): Employee
    {
        return Employee::create([
            'first_name' => 'سالم',
            'email' => 'e'.uniqid().'@example.test',
            'employee_photo' => $photo,
        ]);
    }

    /** الصورةُ الموجودة تُعطي عنواناً تحت مجلّد الرفع لا تحت storage. */
    public function test_the_url_points_at_the_upload_directory(): void
    {
        $name = 'test-'.uniqid().'.png';
        $dir = public_path('assets/uploads/employee');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->uploaded = $dir.'/'.$name;
        file_put_contents($this->uploaded, 'x');

        $url = $this->employee($name)->photo_url;

        $this->assertNotNull($url);
        $this->assertStringContainsString('assets/uploads/employee/'.$name, $url);
        $this->assertStringNotContainsString('/storage/', $url, 'لا صورةَ موظفٍ تحت storage');
    }

    /** وموظفٌ بلا صورة يُرَدّ بـ`null` — فتعرض الواجهةُ البديلَ المقصود. */
    public function test_an_employee_without_a_photo_yields_null(): void
    {
        $this->assertNull($this->employee(null)->photo_url);
    }

    /**
     * واسمُ ملفٍّ لا وجودَ له على القرص يُرَدّ بـ`null` لا بعنوانٍ مكسور.
     *
     * الصفُّ قد يحمل اسمَ ملفٍّ حُذف — نقلُ استضافةٍ أو تنظيفُ مجلّد.
     */
    public function test_a_missing_file_yields_null_not_a_broken_url(): void
    {
        $this->assertNull($this->employee('not-on-disk-'.uniqid().'.png')->photo_url);
    }

    /** والاحتياطيُّ الذي تعرضه الشاشاتُ موجودٌ فعلاً. */
    public function test_the_placeholder_avatar_exists_on_disk(): void
    {
        $this->assertFileExists(
            public_path('images/default-avatar.svg'),
            'الشاشاتُ تشير إلى احتياطيٍّ غير موجود — أيقونةُ صورةٍ مكسورة'
        );
    }

    /**
     * ولا شاشةَ تبني المسارَ بيدها من `storage/` بعد اليوم.
     *
     * والتعليقاتُ تُجرَّد قبل الفحص: شرحُ العلّة يذكر النصَّ الخاطئ
     * بالضرورة، ولا يصحّ أن يُسقط الاختبارَ شرحٌ يصف ما أُصلح.
     */
    public function test_no_screen_still_builds_the_path_from_storage(): void
    {
        foreach ([
            'Modules/HR/Http/Controllers/Attendance/BarcodeAttendanceController.php',
            'Modules/HR/resources/views/attendance/field/index.blade.php',
            'Modules/HR/resources/views/barcode/barcode-scan.blade.php',
        ] as $file) {
            $code = $this->withoutComments((string) file_get_contents(base_path($file)));

            $this->assertStringNotContainsString(
                "storage/'.\$employee->employee_photo",
                $code,
                $file.' ما زال يبني مسارَ الصورة من storage'
            );
            $this->assertStringNotContainsString(
                "'/storage/' + emp.photo",
                $code,
                $file.' ما زال يبني مسارَ الصورة من storage'
            );
        }
    }

    /** تجريدُ تعليقات بليد وPHP وجافاسكربت — الفحصُ على الشيفرة الحيّة. */
    private function withoutComments(string $source): string
    {
        return (string) preg_replace(
            ['/\{\{--.*?--\}\}/s', '#/\*.*?\*/#s', '#^\s*//.*$#m'],
            '',
            $source
        );
    }
}
