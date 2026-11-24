{{--
    «ما الذي يضيّق هذه القائمة؟» — سطرٌ فوق نتائج الفواتير والمرتجعات
    والإشعارات.

    ثلاثةُ مرشِّحاتٍ تعمل على هذه الشاشات دون أن يختارها المستخدم: مدى
    التاريخ الافتراضي، والفرعُ النشط من الهيدر، ونطاقُ الرؤية بحسب التسلسل
    الإداري. وحين يجتمع اثنان منها على قاعدة بياناتٍ أخرى تخرج الشاشة فارغةً
    فتُقرأ عطلاً — وهذا السطر يقول أيُّها يعمل الآن، على أيّ خادم.

    يُمرَّر `$scopeNotice` من `ScopesToEmployeeHierarchy::listScopeNotice()`.
--}}
@php
    $scopeLine = (isset($scopeNotice) && is_array($scopeNotice)) ? $scopeNotice : null;
    $scopeDateFormat = \App\Support\LanguageSettings::dateFormat() ?: 'Y-m-d';
    $scopeDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->translatedFormat($scopeDateFormat) : '—';
@endphp

@if ($scopeLine)
    <div class="po-list-scope" role="note">
        <span>
            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
            {{ __('purchase_cycle.list_scope.period', ['from' => $scopeDate($scopeLine['from']), 'to' => $scopeDate($scopeLine['to'])]) }}
            @if ($scopeLine['is_default'])
                {{-- النصُّ يتبع مصدرَ المدى فعلاً: كان يقول «السنة المالية الجارية»
                     وهو شهرٌ اخترعه تقويمٌ غير مضبوط --}}
                <em>{{ __('purchase_cycle.list_scope.period_source.'.($scopeLine['source'] ?? 'fiscal_year')) }}</em>
            @endif
        </span>

        <span>
            <i class="fas fa-sitemap" aria-hidden="true"></i>
            {{ $scopeLine['branch'] === null
                ? __('purchase_cycle.list_scope.branch_all')
                : __('purchase_cycle.list_scope.branch_one', ['name' => $scopeLine['branch']]) }}
        </span>

        <span>
            <i class="fas fa-user-shield" aria-hidden="true"></i>
            {{ $scopeLine['people'] === null
                ? __('purchase_cycle.list_scope.people_all')
                : __('purchase_cycle.list_scope.people_own', ['count' => $scopeLine['people']]) }}
        </span>

        @if ($scopeLine['role_fallback'])
            <span class="po-list-scope__warn">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                {{ __('purchase_cycle.list_scope.role_fallback', ['role' => $scopeLine['role']]) }}
            </span>
        @endif
    </div>
@endif

@once
    <style>
        .po-list-scope {
            display: flex; flex-wrap: wrap; gap: .4rem 1.25rem;
            padding: .6rem .9rem; margin-bottom: 1rem;
            background: var(--app-surface, #fff);
            border: 1px solid var(--app-border, #e6e9f0);
            border-inline-start: 3px solid var(--app-info, #00cfe8);
            border-radius: var(--app-radius, .5rem);
            font-size: .8rem; color: var(--app-muted, #6e6b7b);
        }
        .po-list-scope i { color: var(--app-info, #00cfe8); margin-inline-end: .25rem; }
        .po-list-scope em { font-style: normal; opacity: .8; }
        .po-list-scope__warn { color: var(--app-warning, #ff9f43); font-weight: 600; }
        .po-list-scope__warn i { color: inherit; }
    </style>
@endonce
