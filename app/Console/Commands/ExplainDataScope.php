<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\User;
use App\Support\DataScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * «لماذا لا يرى هذا المستخدم إلّا بياناته؟» — يُجيب بالأرقام لا بالتخمين.
 *
 * ══════════ لماذا أمرٌ لا فحصٌ يدويّ ══════════
 *
 * نطاقُ الرؤية يمرّ بستّ حلقاتٍ قبل أن يصل إلى صفٍّ في الشاشة: دورُ
 * المستخدم، وصلاحيةُ النطاق في الدور، وربطُ حساب المستخدم بسجلّ موظف،
 * والتسلسلُ الإداري في سجلّات الموظفين، وحسابُ {@see DataScope}، ثمّ
 * فلترُ الفرع النشط في الشريط العلويّ فوق ذلك كلِّه. وانقطاعُ أيّ حلقةٍ
 * يُخرج الشاشةَ نفسَها: «لا أرى إلّا بياناتي» — بلا رسالةٍ تقول أيُّ
 * حلقةٍ انقطعت.
 *
 * فهذا الأمرُ يطبع الحلقاتِ الستَّ مرتَّبةً، ويعدّ ما يراه المستخدمُ **مع**
 * فلتر الفرع و**بدونه** — فيفصل بين «نطاقي ضيّق» و«الفرعُ يحجب فريقي»،
 * وهما سببان مختلفان علاجُهما مختلف.
 *
 * الاستعمال:
 *   php artisan scope:explain 12
 *   php artisan scope:explain sup@example.com
 *   php artisan scope:explain "أحمد"
 */
class ExplainDataScope extends Command
{
    protected $signature = 'scope:explain {user : معرّف المستخدم أو بريده أو جزءٌ من اسمه}';

    protected $description = 'يشرح نطاق رؤية مستخدمٍ بعينه: دورُه، وصلاحيةُ نطاقه، وفريقُه، وما يراه فعلاً في شاشات المبيعات';

    public function handle(): int
    {
        $user = $this->findUser((string) $this->argument('user'));

        if (! $user) {
            $this->error('لا مستخدمَ بهذا المعرّف أو البريد أو الاسم.');

            return self::FAILURE;
        }

        Auth::login($user);
        DataScope::flush();

        $problems = [];

        $this->line('');
        $this->section('١) المستخدم');
        $role = $user->primaryRole;
        $this->pair('الاسم', $user->name.'  (#'.$user->id.')');
        $this->pair('الدور (role_id)', $role ? $role->name.'  (#'.$role->id.')' : 'لا دورَ مربوط — عمود role النصّي: '.($user->role ?: 'فارغ'));
        $this->pair('سجلّ الموظف (users.employee_id)', $user->employee_id ?: 'فارغ');
        $this->pair('فرع المستخدم (users.branch_id)', $this->branchLabel($user->branch_id));

        if (! $role) {
            $problems[] = 'المستخدم بلا دورٍ مربوط (`users.role_id` فارغ) — لا صلاحياتِ له ولا نطاق. اربطه بدورٍ من شاشة المستخدمين.';
        }

        $this->section('٢) صلاحية النطاق في دوره');
        $held = collect(['data_scope_all', 'data_scope_region', 'data_scope_branch', 'data_scope_team', 'data_scope_own'])
            ->filter(fn ($p) => hasPermission($p))->values();
        $this->pair('المملوكة', $held->isEmpty() ? 'لا شيء' : $held->implode('، '));
        $this->pair('النطاق المحسوب', DataScope::level().(DataScope::isUnrestricted() ? '  (بلا قيد)' : ''));

        if ($held->contains('data_scope_own')) {
            $problems[] = 'الدورُ يحمل `data_scope_own` («بياناته هو») — وهو قرارٌ صريحٌ يُحترم. امنحه `data_scope_team` بدلاً منه وانزع `own`.';
        } elseif ($held->isEmpty() && $user->employee_id) {
            $this->comment('    (بلا نطاقٍ مصرَّح — يُقاس على التسلسل الإداري كما في سندات القبض)');
        }

        $this->section('٣) سجلّ الموظف وفريقه');
        $employee = $user->employee;

        if (! $employee) {
            $this->pair('سجلّ الموظف', 'غير موجود');
            if ($user->employee_id) {
                $problems[] = 'حسابُ المستخدم يشير إلى موظفٍ رقمه '.$user->employee_id.' ولا سجلَّ بهذا الرقم في `employees`.';
            } else {
                $problems[] = 'حسابُ المستخدم غير مربوطٍ بسجلّ موظف (`users.employee_id` فارغ) — ولا يُعرف فريقُه من دونه. اربطه من شاشة المستخدمين.';
            }
        } else {
            $this->pair('الموظف', $employee->full_name.'  (#'.$employee->id.')');
            $this->pair('مديرُه المباشر', $employee->direct_manager_id ?: 'لا أحد');

            $direct = Employee::where('direct_manager_id', $employee->id)->get(['id', 'first_name', 'branch_id']);
            $all = $employee->getDescendantIds();

            $this->pair('من يتبعونه مباشرةً', $direct->isEmpty() ? 'لا أحد'
                : $direct->map(fn ($e) => $e->full_name.' (#'.$e->id.')')->implode('، '));
            $this->pair('كلُّ من تحته في الشجرة', $all ? implode('، ', $all) : 'لا أحد');

            if (! $all) {
                $problems[] = 'لا موظفَ واحدٌ `direct_manager_id` فيه يساوي '.$employee->id.' — «فريقه» فارغ. افتح ملفَّ كلِّ مرؤوسٍ واضبط «المدير المباشر» عليه (القيمةُ رقمُ **الموظف** لا رقمُ المستخدم).';
            } else {
                $withUsers = User::whereIn('employee_id', $all)->pluck('employee_id')->all();
                $orphans = array_diff($all, $withUsers);
                if ($orphans) {
                    $this->warn('    تنبيه: موظفون في فريقه بلا حساب مستخدم: '.implode('، ', $orphans)
                        .' — مستنداتُهم تظهر بعمود «المسؤول» لا بعمود «أنشأها».');
                }
            }
        }

        $this->section('٤) ما حسبه النظام');
        $this->pair('المستخدمون في نطاقه', $this->ids(DataScope::documentUserIds()));
        $this->pair('الموظفون في نطاقه', $this->ids(DataScope::documentEmployeeIds()));
        $this->pair('الفروع في نطاقه', $this->ids(DataScope::branchIds()));

        $this->section('٥) فلتر الفرع في الشريط العلويّ');
        $headerBranch = current_branch_id();
        $isMain = $headerBranch ? (bool) Branch::where('id', $headerBranch)->value('is_main') : true;
        $this->pair('الفرع النشط', $this->branchLabel($headerBranch));
        $peopleAxis = in_array(DataScope::level(), [DataScope::TEAM, DataScope::OWN], true) && ! DataScope::isUnrestricted();

        $this->pair('أهو الفرع الرئيسي؟', $isMain ? 'نعم — فلا تصفيةَ فرعٍ إطلاقاً' : 'لا');
        $this->pair('أيُطبَّق على هذا المستخدم؟', $peopleAxis
            ? 'لا — نطاقُه محورُ أشخاص (فريقُه/هو)، فلا يُقصّ على الفرع فوق ذلك'
            : ($isMain ? 'لا' : 'نعم — مقصورٌ على هذا الفرع وما تحته'));

        $this->section('٦) ما يراه فعلاً في شاشة الفواتير');
        $controller = app(\Modules\Sales\Http\Controllers\InvoicesController::class);
        $scope = new \ReflectionMethod($controller, 'scopeSalesDocuments');
        $scope->setAccessible(true);

        $base = fn () => Invoice::whereIn('type', ['normal', 'pos', 'online']);

        $branch = new \ReflectionMethod($controller, 'applyHeaderBranchScope');
        $branch->setAccessible(true);

        $total = $base()->count();
        $scoped = $scope->invoke($controller, $base())->count();

        $onScreen = $scope->invoke($controller, $base());
        $branch->invoke($controller, $onScreen);
        $withBranch = $onScreen->count();

        $mine = $base()->where('created_by', $user->id)->count();

        $this->pair('فواتير المنشأة كلُّها', $total);
        $this->pair('ما أنشأه هو وحدَه', $mine);
        $this->pair('بنطاقه (بلا فلتر الفرع)', $scoped);
        $this->pair('بنطاقه + فلتر الفرع  ← ما يراه على الشاشة', $withBranch);

        if ($scoped > $withBranch) {
            $problems[] = 'فلترُ الفرع يحجب '.($scoped - $withBranch).' فاتورةً من فواتير فريقه: فريقُه يعمل في فروعٍ غير فرعه. '
                .'إمّا أن تنقل المستخدم إلى الفرع الأب في «تابع لـ»، أو تُبدّل الفرع من الشريط العلويّ.';
        }

        if ($scoped <= $mine && $scoped < $total) {
            $problems[] = 'نطاقُه لا يتجاوز ما أنشأه هو — راجع الخطوتين ٢ و٣ أعلاه.';
        }

        $this->line('');
        $this->section('الخلاصة');

        if (! $problems) {
            $this->info('  ✔ لا عائق: النطاقُ يعمل وما على الشاشة هو ما يُفترض أن يكون.');
        } else {
            foreach ($problems as $i => $p) {
                $this->line('  '.($i + 1).') '.$p);
            }
        }
        $this->line('');

        return self::SUCCESS;
    }

    private function findUser(string $needle): ?User
    {
        if (ctype_digit($needle)) {
            return User::find((int) $needle);
        }

        return User::where('email', $needle)->first()
            ?? User::where('name', 'like', '%'.$needle.'%')->first();
    }

    private function branchLabel($id): string
    {
        if (! $id) {
            return 'بلا فرع — «كل الفروع»';
        }

        return (Branch::where('id', $id)->value('name') ?: '؟').'  (#'.$id.')';
    }

    /** @param  array<int, int>|null  $ids */
    private function ids(?array $ids): string
    {
        if ($ids === null) {
            return 'بلا قيد (الكلّ)';
        }

        return $ids ? implode('، ', $ids) : 'لا أحد';
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line("<fg=cyan;options=bold>{$title}</>");
    }

    /** `str_pad` تعدّ البايتات، والعربيّةُ حرفان أو ثلاثة للحرف — فتختلّ الأعمدة. */
    private function pair(string $label, $value): void
    {
        $pad = max(1, 40 - mb_strlen($label));

        $this->line('    '.$label.' '.str_repeat('.', $pad).' '.$value);
    }
}
