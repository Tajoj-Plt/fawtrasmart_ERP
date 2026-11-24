@extends('sales::master')

{{-- تصاميم مستندات المبيعات — نافذة على `DocumentTemplate` مقصورة على
     أنواع دورة المبيعات الثمانية. التحرير والمعاينة والنسخ تذهب إلى مسارات
     وحدة الإعدادات نفسها، فلا محرّر ثانٍ يُصان.

     كانت الشاشة تستدعي `sitting::components._settings_styles` وتكتب فوقه
     ورقةَ أنماط ثالثة بالبادئة `dz-` — نظامان بصريان في شاشة واحدة،
     وأصنافٌ (`st-card`, `st-badge`, `st-note`) تنكسر بمجرّد إطفاء وحدة
     الإعدادات في `modules_statuses.json`. وألوانها مثبَّتة: `#2b6cb0`
     و`#8a93a0` لا تتبع لون المظهر ولا تنقلب في الوضع الليلي.

     الآن: `sl-*` وحدها، وألوانها من `--app-*` التي تحقنها `ThemeSettings`.

     وتأكيد الحذف صار `Swal` كبقيّة شاشات الإعدادات بدل `confirm()` الذي
     يوقف صفحةَ المتصفّح كلَّها بمربّع لا يشبه النظام. --}}

@section('title', __('sales_settings.designs.title'))

@section('content')
    @include('sales::partials.settings-theme')
    @include('components.hint-tips')

    @php
        // النوع بلا قالب افتراضي يُطبع بقالب النظام — تنبيه واحد مجمَّع
        // أوضح من شارة مبعثرة على ثمانية أقسام
        $missing = collect($groups)->reject(fn ($g) => $g['hasDefault'])->pluck('label');
    @endphp

    <div class="content-body sl-scope">

        @section('page-actions')
            @if ($canEdit)
                <a href="{{ route('document-templates.create') }}" class="btn btn-primary">
                    <i class="feather icon-plus"></i> {{ __('sales_settings.designs.new') }}
                </a>

                <a href="{{ route('document-templates.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-grid"></i> {{ __('sales_settings.designs.all_templates') }}
                </a>
            @endif

            <a href="{{ route('sittingInvoice.index') }}" class="btn btn-outline-secondary">
                <i class="feather icon-arrow-{{ ($languageIsRtl ?? true) ? 'right' : 'left' }}"></i>
                {{ __('sales_settings.ui.back') }}
            </a>
        @endsection

        @include('sales::components.page-head', [
            'title' => __('sales_settings.designs.title'),
            'icon' => 'layout',
            'sub' => __('sales_settings.designs.subtitle'),
            'crumbs' => [
                __('sales_settings.ui.home') => route('dashboard'),
                __('sales_settings.hub.title') => route('sittingInvoice.index'),
                __('sales_settings.designs.title') => null,
            ],
        ])

        @include('layouts.alerts.success')
        @include('layouts.alerts.error')
        @include('sales::components.form-errors')

        {{-- ══════════ إرشادُ الشاشة ══════════ --}}
        @include('sales::components.guide', [
            'key' => 'sales-designs.index',
            'open' => false,
            'title' => __('sales_settings.guide.designs.title'),
            'lead' => __('sales_settings.guide.designs.lead'),
            'blocks' => [
                [
                    __('sales_settings.guide.designs.a'),
                    __('sales_settings.guide.designs.b'),
                    __('sales_settings.guide.designs.c'),
                ],
            ],
        ])

        @unless ($canEdit)
            <div class="alert alert-warning">
                <i class="feather icon-lock"></i> {{ __('sales_settings.designs.no_permission') }}
            </div>
        @endunless

        {{-- ══════════ الإحصاء ══════════ --}}
        <div class="sl-stats">
            <div class="sl-stat">
                <div class="sl-stat__icon"><i class="feather icon-layout"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['total'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.designs.total') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--success"><i class="feather icon-check-circle"></i></div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ \App\Support\CurrencySettings::number($stats['active'], 0) }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.designs.active') }}</p>
                </div>
            </div>

            <div class="sl-stat">
                <div class="sl-stat__icon sl-stat__icon--{{ $stats['covered'] === $stats['types'] ? 'success' : 'warning' }}">
                    <i class="feather icon-award"></i>
                </div>
                <div class="sl-stat__body">
                    <p class="sl-stat__value sl-num">{{ $stats['covered'] }} / {{ $stats['types'] }}</p>
                    <p class="sl-stat__label">{{ __('sales_settings.designs.types_covered') }}</p>
                </div>
            </div>
        </div>

        {{-- المظهر واللغة وحدهما من إعدادات النظام تخصّان هذه الشاشة:
             قالب الطباعة يرث خطّ النظام واتجاهه، ولا شأن للعملة ولا
             للحسابات هنا --}}
        @include('sales::components.system-context', ['only' => ['appearance', 'language', 'business']])

        @if ($missing->isNotEmpty())
            <div class="alert alert-warning">
                <i class="feather icon-alert-triangle"></i>
                {{ __('sales_settings.designs.missing_default_note', [
                    'types' => $missing->implode(__('sales_settings.ui.list_separator')),
                ]) }}
            </div>
        @endif

        {{-- ══════════ الأنواع الثمانية ══════════ --}}
        @foreach ($groups as $type => $group)
            <div class="card sl-table-card">
                <div class="sl-table-head">
                    <h6 class="sl-table-head__title">
                        <i class="feather icon-{{ $group['icon'] }}"></i> {{ $group['label'] }}
                    </h6>

                    <div class="d-flex align-items-center" style="gap:.5rem">
                        <span class="sl-count sl-num">{{ $group['templates']->count() }}</span>

                        @unless ($group['hasDefault'])
                            <span class="badge badge-warning">{{ __('sales_settings.designs.missing_default') }}</span>
                        @endunless

                        @if ($canEdit)
                            <a href="{{ route('document-templates.create') }}?document_type={{ $type }}"
                               class="btn btn-sm btn-outline-primary"
                               title="{{ __('sales_settings.designs.create_for', ['type' => $group['label']]) }}"
                               aria-label="{{ __('sales_settings.designs.create_for', ['type' => $group['label']]) }}">
                                <i class="feather icon-plus"></i>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <tbody>
                            @forelse ($group['templates'] as $template)
                                <tr>
                                    <td>
                                        <div class="sl-media">
                                            <span class="sl-media__thumb" aria-hidden="true">
                                                <i class="feather icon-{{ $group['icon'] }}"></i>
                                            </span>

                                            <div class="sl-media__body">
                                                <span class="sl-media__title">{{ $template->name }}</span>
                                                <span class="sl-media__sub">
                                                    {{ __('sales_settings.designs.paper') }}:
                                                    {{ $paperSizes[$template->paper_size] ?? $template->paper_size }}

                                                    @if ($template->updated_at)
                                                        · {{ __('sales_settings.ui.updated_at') }}
                                                        {{ \App\Support\LanguageSettings::formatDate($template->updated_at) }}
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-center" style="width:180px">
                                        <div class="sl-tags justify-content-center">
                                            @if ($template->is_default)
                                                <span class="badge badge-success">{{ __('sales_settings.designs.is_default') }}</span>
                                            @endif

                                            <span class="sl-state {{ $template->is_active ? 'sl-state--on' : 'sl-state--off' }}">
                                                {{ $template->is_active
                                                    ? __('sales_settings.ui.active')
                                                    : __('sales_settings.ui.inactive') }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="text-center" style="width:170px">
                                        <div class="sl-actions">
                                            <a href="{{ route('document-templates.preview', $template->id) }}" target="_blank"
                                               class="btn btn-outline-secondary"
                                               title="{{ __('sales_settings.designs.preview') }}"
                                               aria-label="{{ __('sales_settings.designs.preview') }}">
                                                <i class="feather icon-eye"></i>
                                            </a>

                                            @if ($canEdit)
                                                <a href="{{ route('document-templates.edit', $template->id) }}"
                                                   class="btn btn-outline-primary"
                                                   title="{{ __('sales_settings.ui.edit') }}"
                                                   aria-label="{{ __('sales_settings.ui.edit') }}">
                                                    <i class="feather icon-edit-2"></i>
                                                </a>

                                                {{-- الإجراءات الثلاثة أزرارٌ تُرسَل عبر النموذج المشترك
                                                     في أسفل الصفحة. ثلاثة نماذج لكل صفّ تعني مئاتٍ منها
                                                     مع رمز CSRF في كلٍّ — وهي صفحة تعرض عشرات القوالب --}}
                                                @unless ($template->is_default)
                                                    <button type="button" class="btn btn-outline-success dz-act"
                                                            data-action="{{ route('document-templates.set-default', $template->id) }}"
                                                            title="{{ __('sales_settings.designs.set_default') }}"
                                                            aria-label="{{ __('sales_settings.designs.set_default') }}">
                                                        <i class="feather icon-award"></i>
                                                    </button>
                                                @endunless

                                                <button type="button" class="btn btn-outline-secondary dz-act"
                                                        data-action="{{ route('document-templates.duplicate', $template->id) }}"
                                                        title="{{ __('sales_settings.designs.duplicate') }}"
                                                        aria-label="{{ __('sales_settings.designs.duplicate') }}">
                                                    <i class="feather icon-copy"></i>
                                                </button>

                                                <button type="button" class="btn btn-outline-danger dz-act"
                                                        data-action="{{ route('document-templates.destroy', $template->id) }}"
                                                        data-method="DELETE"
                                                        data-confirm="{{ __('sales_settings.designs.confirm_delete', ['name' => $template->name]) }}"
                                                        @disabled($template->is_default)
                                                        title="{{ $template->is_default
                                                            ? __('sales_settings.designs.default_locked')
                                                            : __('sales_settings.ui.delete') }}"
                                                        aria-label="{{ __('sales_settings.ui.delete') }}">
                                                    <i class="feather icon-trash-2"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3">
                                        @include('sales::components.empty-state', [
                                            'icon' => $group['icon'],
                                            'title' => __('sales_settings.designs.empty_type'),
                                            'hint' => null,
                                            'action' => $canEdit
                                                ? '<a href="'.route('document-templates.create').'?document_type='.e($type).'" class="btn btn-primary">'
                                                    .'<i class="feather icon-plus"></i> '
                                                    .e(__('sales_settings.designs.create_for', ['type' => $group['label']])).'</a>'
                                                : null,
                                        ])
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        @if ($canEdit)
            {{-- نموذج واحد لكل إجراءات الصفحة: يُملأ وجهتُه وطريقتُه عند الضغط --}}
            <form id="dzForm" method="POST" class="d-none">
                @csrf
                <input type="hidden" name="_method" id="dzMethod" value="POST">
            </form>
        @endif
    </div>
@endsection

@section('scripts')
    @if ($canEdit)
        <script>
            (function () {
                var form = document.getElementById('dzForm');
                if (!form) return;

                var method = document.getElementById('dzMethod');

                function send(button) {
                    form.action = button.dataset.action;
                    method.value = button.dataset.method || 'POST';
                    form.submit();
                }

                document.addEventListener('click', function (event) {
                    var button = event.target.closest('.dz-act');
                    if (!button || button.disabled) return;

                    if (!button.dataset.confirm) {
                        send(button);
                        return;
                    }

                    // `confirm()` يوقف صفحة المتصفّح بمربّع لا يشبه النظام،
                    // وهو معطَّل في بعض المتصفّحات داخل إطار — Swal كبقيّة
                    // شاشات الإعدادات
                    Swal.fire({
                        title: @json(__('sales_settings.ui.confirm_delete_title')),
                        text: button.dataset.confirm,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: @json(__('sales_settings.ui.confirm_yes')),
                        cancelButtonText: @json(__('sales_settings.ui.cancel')),
                        reverseButtons: true
                    }).then(function (result) {
                        if (result.isConfirmed) send(button);
                    });
                });
            })();
        </script>
    @endif
@endsection
