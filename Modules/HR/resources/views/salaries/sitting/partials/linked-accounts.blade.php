{{--
    حساباتٌ مربوطة للقراءة وحدها — مصدرُها «إعدادات الحسابات المحاسبية».

    المدخلات:
      $rows   — من `PayrollAccounts::rows([...])`
      $hints  — (اختياري) المفتاح => ما يقيّده الحساب
      $title  — عنوان القسم (افتراضاً «الحسابات»)
      $icon   — أيقونة feather (افتراضاً briefcase)
      $focus  — (اختياري) المفتاح الذي يُفتح عليه رابطُ التعديل؛ وإلا مجموعةُ الرواتب

    لا حقلَ هنا يُرسَل مع النموذج: الربطُ يُعدَّل في الشاشة المحاسبية وحدها،
    كي لا يكون للحساب مصدران يُقيَّد القيدُ على أحدهما ويُعرض الآخر.
--}}
@php
    $hints = $hints ?? [];
    $title = $title ?? 'الحسابات';
    $icon = $icon ?? 'briefcase';
    $focus = $focus ?? null;
    $unmappedCount = collect($rows)->where('state', '!=', 'mapped')->count();
@endphp
<div class="stk-section">
    <div class="stk-section__head">
        <i class="feather icon-{{ $icon }}"></i>
        <h6 class="stk-section__title">{{ $title }}</h6>
        <i class="feather icon-help-circle stk-tip"
           title="تُعرض هنا للقراءة. الربطُ يُعدَّل من {{ \App\Support\PayrollAccounts::SCREEN }} (مجموعة «{{ \App\Support\PayrollAccounts::GROUP }}») — ويسري على القيود فور حفظه هناك."></i>
        @if ($unmappedCount > 0)
            <span class="stk-state stk-state--off">{{ $unmappedCount }} غير مربوط</span>
        @endif
    </div>

    <dl class="stk-def mb-1">
        @foreach ($rows as $row)
            <div class="stk-def__row">
                <dt>
                    {{ $row['label'] }}
                    @if (! empty($hints[$row['key']]))
                        <i class="feather icon-help-circle stk-tip" title="{{ $hints[$row['key']] }}"></i>
                    @endif
                </dt>
                <dd>
                    @if ($row['state'] === 'mapped')
                        <span class="stk-num">{{ $row['account']->code ?? '—' }}</span> — {{ $row['account']->name }}
                        @unless ($row['account']->is_active)
                            <span class="stk-state stk-state--off">الحساب معطَّل في الشجرة</span>
                        @endunless
                    @elseif ($row['state'] === 'inactive')
                        <span class="stk-num">{{ $row['account']->code ?? '—' }}</span> — {{ $row['account']->name }}
                        <span class="badge badge-danger">الإعداد موقوف — لا يُقيَّد عليه</span>
                    @else
                        <span class="badge badge-danger"><i class="feather icon-alert-triangle"></i> غير مربوط</span>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>

    <div class="d-flex align-items-center flex-wrap" style="gap: .5rem">
        @if (\App\Support\PayrollAccounts::canEdit())
            <a href="{{ \App\Support\PayrollAccounts::editUrl($focus) }}" class="btn btn-sm btn-outline-primary">
                <i class="feather icon-external-link"></i> تعديل الربط في إعدادات الحسابات
            </a>
        @endif
        <span class="stk-help mb-0">للقراءة — الربطُ من {{ \App\Support\PayrollAccounts::SCREEN }}.</span>
    </div>
</div>
