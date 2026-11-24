{{--
    مُنتقي «مسؤول المبيعات» — موظّفو الأقسام المسموحة.

    ══════════ من أين تأتي القائمة ══════════

    من **الموظفين** (`employees`)، مقصورةً على «أقسام الموظفين المسموح
    تعيينهم للعملاء» في: إعدادات وحدات العملاء ← تبويب الموظفين. فمن قصر
    الإسنادَ على «قسم المبيعات» لا يرى في هذا الفلتر محاسباً ولا مدير
    تقنية — يرى ما تراه شاشةُ إسناد الموظفين للعميل نفسُها، لا أكثر.

    وقائمةُ الأقسام الفارغة هناك تعني **كلَّ الأقسام**، لا «لا أحد».

    ══════════ ما استبدله ══════════

    كانت القائمةُ `User::whereIn('role', ['employee','manager'])` — فضاءُ
    **مستخدمين**، بينما `clients.employee_id` معرّفُ **موظّف**. فكان
    المتحكّم يترجم المستخدمَ إلى ملفّه الوظيفي عند الفلترة، ويسقط في
    الترجمة كلُّ مندوبٍ بلا حساب دخول — وهو حالُ أكثرهم — بينما تمتلئ
    القائمةُ بمستخدمين إداريين لا يُسنَد إليهم عميلٌ أصلاً.

    والقيمةُ المرسَلة الآن `employees.id` مباشرةً.

    ══════════ حقلٌ واحدٌ للموظف (2026-09-15) ══════════

    حُذف «تمت الإضافة بواسطة» من الشاشات بطلب المستخدم، وصار هذا الحقلُ
    يطابق العملاءَ المُسنَدين إلى الموظف **أو** الذين أضافهم بحسابه —
    انظر FiltersCustomerReports::applyClientFilters().

    @param \Illuminate\Support\Collection $salesEmployees  خرجُ FiltersCustomerReports::salesEmployeeOptions()
    @param ?string $label  نصُّ العنوان فوق الحقل — بلا قيمةٍ لا يُطبع عنوان
--}}

@php
    $seEmployees = $salesEmployees ?? collect();
    $seCurrent = (string) request('sales_manager', '');
    $seLabel = $label ?? null;
@endphp

@if ($seLabel)
    <label for="sales_manager">{{ $seLabel }}</label>
@endif

<select name="sales_manager" id="sales_manager" class="form-control rpt-select2"
        data-placeholder="كل مسؤولي المبيعات">
    <option value="">كل مسؤولي المبيعات</option>
    @foreach ($seEmployees as $seEmployee)
        <option value="{{ $seEmployee->id }}" {{ $seCurrent === (string) $seEmployee->id ? 'selected' : '' }}>
            {{ $seEmployee->full_name }}
        </option>
    @endforeach
</select>
