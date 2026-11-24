@extends('installer::layout')

@section('title', 'الإعدادات')

@section('content')
    @php $d = config('installer.defaults'); @endphp

    <h2>بيانات المنشأة والإعدادات الأساسية</h2>
    <p class="lead">تظهر هذه البيانات على الفواتير وقوالب المستندات، وتُحدِّد سلوك النظام المالي.</p>

    @include('installer::partials.guide', [
        'title' => 'ما يهمّ في هذه الخطوة',
        'items' => [
            '<b>العملة</b> تُضبط افتراضيةً في <b>جدول العملات</b> نفسه الذي تديره من «الإعدادات ← العملات» — فتظهر محدَّدةً هناك ويُنسَّق بها كل مبلغ في النظام.',
            '<b>اللغة</b> تُجعل الافتراضية في «الإعدادات ← اللغات»، وتبقى بقية اللغات مفعّلة فيظلّ مبدّل اللغة عاملاً.',
            '<b>الضريبة</b> تُدخل مئوية (15) ويخزّنها النظام عشرية (0.15). اختر «شاملة» إن كانت أسعارك تتضمّن الضريبة أصلاً.',
            '<b>الثيم</b> يُطبَّق على النظام كلّه ويمكن تغييره لاحقاً من «الإعدادات ← المظهر».',
            'كل هذه القيم قابلة للتعديل بعد التثبيت — لا شيء هنا نهائي.',
        ],
    ])

    <form method="POST" action="{{ route('installer.settings.store') }}">
        @csrf

        <h3>المنشأة</h3>
        <div class="grid">
            <div class="field full">
                <label for="company_name">اسم المنشأة</label>
                <input type="text" id="company_name" name="company_name"
                       value="{{ old('company_name', $company['name'] ?? '') }}" required>
            </div>

            <div class="field">
                <label for="company_email">البريد الإلكتروني <span class="opt">(اختياري)</span></label>
                <input type="email" id="company_email" name="company_email"
                       value="{{ old('company_email', $company['email'] ?? '') }}">
            </div>

            <div class="field">
                <label for="company_phone">الهاتف <span class="opt">(اختياري)</span></label>
                <input type="text" id="company_phone" name="company_phone"
                       value="{{ old('company_phone', $company['phone'] ?? '') }}">
            </div>

            <div class="field">
                <label for="company_country">الدولة <span class="opt">(اختياري)</span></label>
                <input type="text" id="company_country" name="company_country"
                       value="{{ old('company_country', $company['country'] ?? $d['country']) }}">
            </div>

            <div class="field">
                <label for="company_city">المدينة <span class="opt">(اختياري)</span></label>
                <input type="text" id="company_city" name="company_city"
                       value="{{ old('company_city', $company['city'] ?? '') }}">
            </div>

            <div class="field">
                <label for="tax_number">الرقم الضريبي <span class="opt">(اختياري)</span></label>
                <input type="text" id="tax_number" name="tax_number"
                       value="{{ old('tax_number', $company['tax_number'] ?? '') }}">
            </div>

            <div class="field">
                <label for="commercial_registration">السجل التجاري <span class="opt">(اختياري)</span></label>
                <input type="text" id="commercial_registration" name="commercial_registration"
                       value="{{ old('commercial_registration', $company['commercial_registration'] ?? '') }}">
            </div>

            <div class="field">
                <label for="website">الموقع الإلكتروني <span class="opt">(اختياري)</span></label>
                <input type="text" id="website" name="website"
                       value="{{ old('website', $company['website'] ?? '') }}">
            </div>
        </div>

        <h3>اللغة والعملة والتوقيت</h3>
        <div class="grid-3">
            <div class="field">
                <label for="language">اللغة الافتراضية</label>
                <select id="language" name="language" required>
                    @foreach ($languages as $code => $label)
                        <option value="{{ $code }}"
                            @selected(old('language', $values['language'] ?? $d['language']) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="currency">العملة الافتراضية</label>
                <select id="currency" name="currency" required>
                    @foreach ($currencies as $code => $label)
                        <option value="{{ $code }}"
                            @selected(old('currency', $values['currency'] ?? $d['currency']) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="timezone">المنطقة الزمنية</label>
                <select id="timezone" name="timezone" required>
                    @foreach ($timezones as $tz => $label)
                        <option value="{{ $tz }}"
                            @selected(old('timezone', $values['timezone'] ?? $d['timezone']) === $tz)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="date_format">صيغة التاريخ</label>
                <select id="date_format" name="date_format" required>
                    @foreach (['Y-m-d' => '2026-08-17', 'd/m/Y' => '17/08/2026', 'd-m-Y' => '17-08-2026', 'm/d/Y' => '08/17/2026'] as $fmt => $sample)
                        <option value="{{ $fmt }}"
                            @selected(old('date_format', $values['date_format'] ?? $d['date_format']) === $fmt)>{{ $sample }} ({{ $fmt }})</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="time_format">صيغة الوقت</label>
                <select id="time_format" name="time_format" required>
                    @foreach (['H:i' => '14:30 (24 ساعة)', 'h:i A' => '02:30 PM (12 ساعة)'] as $fmt => $sample)
                        <option value="{{ $fmt }}"
                            @selected(old('time_format', $values['time_format'] ?? $d['time_format']) === $fmt)>{{ $sample }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="business_type">طبيعة النشاط</label>
                <select id="business_type" name="business_type" required>
                    @foreach (['products' => 'بيع منتجات', 'services' => 'تقديم خدمات', 'both' => 'منتجات وخدمات'] as $type => $label)
                        <option value="{{ $type }}"
                            @selected(old('business_type', $values['business_type'] ?? $d['business_type']) === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <h3>ثيم النظام</h3>
        <p class="hint" style="margin-bottom: 14px;">
            نفس اللوحات المتاحة في «الإعدادات ← المظهر». المحدَّد هنا هو ما ستجده محدَّداً هناك بعد التثبيت.
        </p>

        @php
            $selectedTheme = old('theme', $values['theme'] ?? 'default');

            // المعامل الثاني `true` إلزامي: `groupBy` يُعيد فهرسة المفاتيح
            // افتراضياً، فتخرج قيم الراديو أرقاماً (value="0") بدل مفاتيح
            // الثيمات — والتحقّق في المتحكّم يرفضها، فيعجز المستخدم عن تجاوز
            // الخطوة بلا سببٍ ظاهر
            $themeGroups = collect($themes)->groupBy('group', true);
        @endphp

        @foreach ($themeGroups as $groupName => $groupThemes)
            <p class="hint" style="margin: 12px 0 8px; font-weight: 600;">{{ $groupName }}</p>
            <div class="theme-grid">
                @foreach ($groupThemes as $key => $preset)
                    <label class="theme-option {{ $selectedTheme === $key ? 'is-selected' : '' }}">
                        <input type="radio" name="theme" value="{{ $key }}"
                               @checked($selectedTheme === $key)>
                        <span class="theme-swatch"
                              style="background: linear-gradient(135deg, {{ $preset['primary_color'] }} 0%, {{ $preset['sidebar_color'] }} 100%);"></span>
                        <span class="theme-name">{{ $preset['label'] }}</span>
                        <span class="theme-code">{{ $preset['primary_color'] }}</span>
                    </label>
                @endforeach
            </div>
        @endforeach

        <h3>ضريبة القيمة المضافة</h3>
        <div class="grid">
            <div class="field">
                <label for="tax_percent">النسبة (%)</label>
                <input type="number" id="tax_percent" name="tax_percent" step="0.01" min="0" max="100"
                       value="{{ old('tax_percent', $values['tax_percent'] ?? $d['tax_percent']) }}" required>
                <span class="hint">تُدخل مئوية (15) ويخزّنها النظام عشرية (0.15)</span>
            </div>

            <div class="field">
                <label for="tax_type">طريقة الاحتساب</label>
                <select id="tax_type" name="tax_type" required>
                    <option value="excluded" @selected(old('tax_type', $values['tax_type'] ?? $d['tax_type']) === 'excluded')>
                        غير شاملة — تُضاف على السعر
                    </option>
                    <option value="included" @selected(old('tax_type', $values['tax_type'] ?? $d['tax_type']) === 'included')>
                        شاملة — مضمَّنة في السعر
                    </option>
                </select>
            </div>
        </div>

        <div class="actions">
            <a class="btn ghost" href="{{ route('installer.database') }}">→ رجوع</a>
            <button type="submit" class="btn">التالي: حساب المدير ←</button>
        </div>
    </form>
@endsection
