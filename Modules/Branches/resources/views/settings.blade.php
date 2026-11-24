@extends('branches::layouts.master')

@section('title', __('branches.settings.title'))

@section('content')
    <x-branches::page-head
        :title="__('branches.settings.title')"
        :subtitle="__('branches.settings.subtitle')"
        :crumbs="[__('branches.settings.title') => null]" />

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- أخطاء التحقّق — ليست إرشاداً ثابتاً: لا تظهر إلا بعد ردِّ حفظٍ
             فعليّ. والمكوّن نفسه المستعمل في create وedit، فالخطأ يُقرأ
             بالشكل ذاته في كل شاشات الوحدة. --}}
        @if ($errors->any())
            <x-branches::explain tone="danger" :title="__('branches.settings.errors_title')">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-branches::explain>
        @endif


        @if ($branchs->isEmpty())
            <div class="card">
                <div class="card-body">
                    <x-branches::empty icon="git-branch" :title="__('branches.settings.empty_branches')"
                                       :text="__('branches.settings.empty_branches_hint')">
                        @can('branches.create')
                            <a href="{{ route('branches.create') }}" class="btn btn-primary">
                                <i class="feather icon-plus"></i> {{ __('branches.settings.create_branch') }}
                            </a>
                        @endcan
                    </x-branches::empty>
                </div>
            </div>
        @else
            {{-- ١. اختيار الفرع --}}
            <div class="card">
                <div class="sl-table-head">
                    <h6 class="sl-table-head__title"><i class="feather icon-git-branch"></i> {{ __('branches.settings.step_choose') }}</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('branches.settings') }}">
                        <div class="row align-items-end">
                            <div class="col-lg-6 col-12">
                                <div class="form-group mb-0">
                                    <label for="branch_id">{{ __('branches.settings.the_branch') }}</label>
                                    {{-- القائمة بترتيب الشجرة: الابن تحت أبيه بمسافة بادئة --}}
                                    <select id="branch_id" name="branch_id" class="form-control" onchange="this.form.submit()">
                                        @foreach ($branchOptions as $opt)
                                            <option value="{{ $opt['id'] }}" {{ (int) $selectedBranchId === (int) $opt['id'] ? 'selected' : '' }}>
                                                {{ $opt['label'] }}
                                            </option>
                                        @endforeach
                                    </select>

                                    {{--
                                        شاشة الإعدادات تعمل على فرعٍ مختار،
                                        فبلا فروعٍ لا شيء تُعدّه. والزرّ لا
                                        يظهر إلا حين تكون القائمة فارغة.
                                    --}}
                                    <x-create-first
                                        :count="count($branchOptions ?? [])"
                                        route="branches.create"
                                        permission="branches.create"
                                        :plan="\App\Constants\planFeatures\BranchesPlanFeatures::BRANCHES"
                                        label="فرعاً"
                                        hint="الإعدادات تُضبط لفرعٍ بعينه، ولا فروع مسجَّلة بعد." />
                                </div>
                            </div>
                            @if ($branch)
                                <div class="col-lg-6 col-12 mt-1 mt-lg-0">
                                    <div class="d-flex flex-wrap" style="gap:.4rem">
                                        <span class="sl-tag">{{ __('branches.settings.own_count', ['count' => $ownCount]) }}</span>
                                        @if ($parentBranch)
                                            <span class="sl-tag">{{ __('branches.settings.inherits_from', ['name' => $parentBranch->name]) }}</span>
                                        @else
                                            <span class="sl-tag">{{ __('branches.settings.is_root') }}</span>
                                        @endif
                                        @if ($childCount)
                                            <span class="sl-tag">{{ __('branches.settings.children_inherit', ['count' => $childCount]) }}</span>
                                        @endif
                                    </div>
                                    @if ($branch->lineagePathLabel())
                                        <small class="text-muted d-block mt-50">
                                            <i class="feather icon-corner-down-left"></i> {{ $branch->lineagePathLabel() }}
                                        </small>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </form>
                </div>

            </div>

            @if ($branch && !empty($grouped))
                {{-- ٢. الإعدادات --}}
                <form method="POST" action="{{ route('branches.settings_store') }}" id="branch-settings-form">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">
                    <input type="hidden" name="group" id="active-group-input" value="{{ $activeGroup }}">

                    <div class="card">
                        <div class="sl-table-head">
                            <h6 class="sl-table-head__title">
                                <i class="feather icon-sliders"></i>
                                {{ __('branches.settings.step_settings', ['name' => $branch->name]) }}
                            </h6>
                            <div class="form-group mb-0" style="min-width:260px">
                                <input type="search" id="settings-search" class="form-control"
                                       placeholder="{{ __('branches.settings.search_placeholder') }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row">
                                {{-- تبويبات التصنيفات --}}
                                <div class="col-lg-3 col-12 mb-2 mb-lg-0">
                                    <ul class="nav nav-pills flex-column stk-tabs" id="settings-tabs" role="tablist">
                                        @foreach ($groupsMeta as $groupKey => $meta)
                                            <li class="nav-item">
                                                {{-- تبديل التبويبات بجافاسكربت الصفحة لا بـ data-toggle:
                                                     القالب يحمّل Bootstrap 5 فوق Vuexy (BS4)، و`data-toggle`
                                                     مقابل `data-bs-toggle` يختلف بينهما فينكسر التبويب. --}}
                                                <a class="nav-link d-flex align-items-center {{ $activeGroup === $groupKey ? 'active' : '' }}"
                                                   href="#group-{{ $groupKey }}"
                                                   data-group="{{ $groupKey }}" role="tab">
                                                    <i class="feather icon-{{ $meta['icon'] }} mr-50"></i>
                                                    <span class="flex-grow-1">{{ $meta['label'] }}</span>
                                                    <span class="sl-count tab-count">{{ count($grouped[$groupKey]) }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                {{-- محتوى التبويبات --}}
                                <div class="col-lg-9 col-12">
                                    <div class="tab-content">
                                        @foreach ($groupsMeta as $groupKey => $meta)
                                            <div class="tab-pane {{ $activeGroup === $groupKey ? 'active' : '' }}"
                                                 id="group-{{ $groupKey }}" data-group-pane="{{ $groupKey }}" role="tabpanel">

                                                <div class="d-flex justify-content-between align-items-start flex-wrap mb-1" style="gap:.5rem">
                                                    <p class="text-muted mb-0" style="max-width:70ch">{{ $meta['hint'] }}</p>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-success btn-bulk" data-group="{{ $groupKey }}" data-on="1">{{ __('branches.settings.enable_all') }}</button>
                                                        <button type="button" class="btn btn-outline-secondary btn-bulk" data-group="{{ $groupKey }}" data-on="0">{{ __('branches.settings.disable_all') }}</button>
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    @foreach ($grouped[$groupKey] as $setting)
                                                        @php
                                                            $definition = $setting['definition'];
                                                            $key = $setting['key'];
                                                            $inputId = 'bs-' . $key;
                                                            $isOwn = $setting['source'] === 'own';
                                                            $options = $definition->options ?? [];
                                                            $old = old($key, $setting['value']);
                                                            $sourceBadge = [
                                                                'own' => ['primary', __('branches.settings.source_own')],
                                                                'inherited' => ['info', __('branches.settings.source_inherited')],
                                                                'default' => ['secondary', __('branches.settings.source_default')],
                                                            ][$setting['source']];
                                                        @endphp

                                                        <div class="col-xl-6 col-12 mb-1 setting-item"
                                                             data-search="{{ mb_strtolower($setting['name'] . ' ' . ($setting['description'] ?? '') . ' ' . $key) }}"
                                                             data-group="{{ $groupKey }}">
                                                            <div class="bx-setting {{ $isOwn ? 'is-own' : '' }} {{ $errors->has($key) ? 'is-invalid' : '' }}">
                                                                <input type="hidden" name="__present__[]" value="{{ $key }}">

                                                                <div class="d-flex justify-content-between align-items-start" style="gap:.5rem">
                                                                    <label class="stk-switch__title mb-0" for="{{ $inputId }}">{{ $setting['name'] }}</label>
                                                                    <span class="sl-tag source-badge">{{ $sourceBadge[1] }}</span>
                                                                </div>

                                                                @if ($setting['description'])
                                                                    <small class="text-muted d-block mt-25">{{ $setting['description'] }}</small>
                                                                @endif

                                                                <div class="mt-75 setting-control">
                                                                    @switch($setting['type'])
                                                                        @case('boolean')
                                                                            <label class="stk-switch stk-switch--sm mb-0">
                                                                                <input type="checkbox" class="setting-input"
                                                                                       id="{{ $inputId }}" name="{{ $key }}" value="1"
                                                                                       data-type="boolean"
                                                                                       {{ $old ? 'checked' : '' }}>
                                                                                <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                                                                <span class="stk-switch__label">
                                                                                    <span class="stk-switch__title">
                                                                                        {{ $old ? __('branches.common.enabled') : __('branches.common.disabled') }}
                                                                                    </span>
                                                                                </span>
                                                                            </label>
                                                                            @break

                                                                        @case('number')
                                                                            <div class="input-group">
                                                                                <input type="number" class="form-control setting-input"
                                                                                       id="{{ $inputId }}" name="{{ $key }}"
                                                                                       value="{{ $old }}"
                                                                                       @isset($options['min']) min="{{ $options['min'] }}" @endisset
                                                                                       @isset($options['max']) max="{{ $options['max'] }}" @endisset
                                                                                       step="{{ $options['step'] ?? 1 }}">
                                                                                @isset($options['suffix'])
                                                                                    <div class="input-group-append">
                                                                                        <span class="input-group-text">{{ $definition->option('suffix') }}</span>
                                                                                    </div>
                                                                                @endisset
                                                                            </div>
                                                                            @break

                                                                        @case('select')
                                                                            <select class="form-control setting-input" id="{{ $inputId }}" name="{{ $key }}">
                                                                                @foreach ($definition->choices() as $value => $label)
                                                                                    <option value="{{ $value }}" {{ (string) $old === (string) $value ? 'selected' : '' }}>
                                                                                        {{ $label }}
                                                                                    </option>
                                                                                @endforeach
                                                                            </select>
                                                                            @break

                                                                        @case('textarea')
                                                                            <textarea class="form-control setting-input" id="{{ $inputId }}" name="{{ $key }}"
                                                                                      rows="{{ $options['rows'] ?? 3 }}"
                                                                                      maxlength="{{ $options['maxlength'] ?? 500 }}">{{ $old }}</textarea>
                                                                            @break

                                                                        @case('time')
                                                                            <input type="time" class="form-control setting-input"
                                                                                   id="{{ $inputId }}" name="{{ $key }}" value="{{ $old }}">
                                                                            @break

                                                                        @case('date')
                                                                            <input type="date" class="form-control setting-input"
                                                                                   id="{{ $inputId }}" name="{{ $key }}" value="{{ $old }}">
                                                                            @break

                                                                        @default
                                                                            <input type="{{ ($options['input'] ?? 'text') === 'email' ? 'email' : 'text' }}"
                                                                                   class="form-control setting-input"
                                                                                   id="{{ $inputId }}" name="{{ $key }}" value="{{ $old }}"
                                                                                   maxlength="{{ $options['maxlength'] ?? 191 }}"
                                                                                   placeholder="{{ $definition->option('placeholder') ?? '' }}">
                                                                    @endswitch
                                                                </div>

                                                                @error($key)
                                                                    <small class="text-danger d-block mt-25">{{ $message }}</small>
                                                                @enderror

                                                                <div class="d-flex justify-content-between align-items-center mt-75 flex-wrap" style="gap:.4rem">
                                                                    @if ($parentBranch)
                                                                        <div class="custom-control custom-checkbox">
                                                                            <input type="checkbox" class="custom-control-input inherit-toggle"
                                                                                   id="inh-{{ $key }}" name="__inherit__{{ $key }}" value="1"
                                                                                   data-target="{{ $inputId }}"
                                                                                   {{ $isOwn ? '' : 'checked' }}>
                                                                            <label class="custom-control-label" for="inh-{{ $key }}">
                                                                                <small>{{ __('branches.settings.follow_parent', ['name' => $parentBranch->name]) }}</small>
                                                                            </label>
                                                                        </div>
                                                                    @else
                                                                        <small class="text-muted">
                                                                            {{ __('branches.settings.default_is', ['value' => $definition->display($setting['default'])]) }}
                                                                        </small>
                                                                    @endif

                                                                    <span class="stk-num">{{ $key }}</span>
                                                                </div>

                                                                @if ($setting['source'] === 'inherited' && $setting['source_branch'])
                                                                    <small class="text-info d-block mt-25">
                                                                        <i class="feather icon-corner-left-down"></i>
                                                                        {{ __('branches.settings.inherited_value', ['value' => $definition->display($setting['value']), 'name' => $setting['source_branch']->name]) }}
                                                                    </small>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach

                                        <div class="d-none" id="no-search-results">
                                            <x-branches::empty icon="search" :title="__('branches.settings.no_search_results')"
                                                               :text="__('branches.settings.no_search_results_hint')" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        {{-- الحفظُ وحده خلف الصلاحية: من يملك العرض دون
                             التعديل يقرأ الإعدادات ولا يجد زرّاً يُردُّ عنده. --}}
                        @can('branches.settings.edit')
                        <div class="sl-form-foot d-block">
                            @if ($childCount)
                                {{-- الخيار يظهر فقط لفرع له تابعون — عرضه على فرع بلا
                                     أبناء زرٌّ لا يفعل شيئاً ويوهم بأنه فعل. --}}
                                <div class="bx-cascade mb-1">
                                    {{-- مفتاحُ نظام التصميم: أثرُ القلب مكتوبٌ داخل
                                         المفتاح نفسه، وهذا قلبٌ يمسّ فروعاً غير
                                         المعروض فحقُّه أن يُقرأ قبل أن يُضغط. --}}
                                    <label class="stk-switch mb-0">
                                        <input type="checkbox" id="apply-to-descendants" name="apply_to_descendants" value="1">
                                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                                        <span class="stk-switch__label">
                                            <span class="stk-switch__title">
                                                {{ __('branches.settings.cascade_label', ['name' => $branch->name]) }}
                                                <span class="sl-count">{{ __('branches.settings.cascade_count', ['count' => $childCount]) }}</span>
                                            </span>
                                            <span class="stk-switch__hint">
                                                {{ __('branches.settings.cascade_off') }}
                                                <br>
                                                {!! __('branches.settings.cascade_on', [
                                                    'strong' => '<strong>' . e(__('branches.settings.cascade_on_strong')) . '</strong>',
                                                ]) !!}
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.75rem">
                                <small class="sl-help mb-0">
                                    <i class="feather icon-info"></i>
                                    {{ __('branches.settings.save_scope', ['name' => $branch->name]) }}
                                    @if ($childCount)
                                        {{ __('branches.settings.save_scope_children') }}
                                    @else
                                        {{ __('branches.settings.save_scope_alone') }}
                                    @endif
                                </small>
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather icon-save"></i> {{ __('branches.settings.save_all') }}
                                </button>
                            </div>
                        </div>
                        @endcan
                    </div>
                </form>

                {{-- ٣. أدوات — كلُّ أداةٍ خلف صلاحيتها هي: النسخُ والإرجاع
                     فعلان مختلفان يمسّان فروعاً غير المعروض، ولكلٍّ حارسُه
                     في BranchesController. --}}
                <div class="row">
                    @can('branches.settings.copy')
                    <div class="col-lg-7 col-12">
                        <div class="card h-100">
                            <div class="sl-table-head">
                                <h6 class="sl-table-head__title"><i class="feather icon-copy"></i> {{ __('branches.settings.copy_title') }}</h6>
                            </div>
                            <div class="card-body">
                                <p class="sl-help">
                                    {!! __('branches.settings.copy_intro', [
                                        'strong' => '<strong>' . e(__('branches.settings.copy_intro_strong')) . '</strong>',
                                        'name' => e($branch->name),
                                        'count' => $ownCount,
                                    ]) !!}
                                </p>

                                <form method="POST" action="{{ route('branches.settings_copy') }}"
                                      onsubmit="return confirm({{ Illuminate\Support\Js::from(__('branches.settings.copy_confirm')) }});">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">

                                    <div class="form-group">
                                        <label>{{ __('branches.settings.copy_where') }}</label>
                                        <div class="custom-control custom-radio mb-50">
                                            <input type="radio" class="custom-control-input" id="copy-mode-desc" name="mode" value="descendants" checked>
                                            <label class="custom-control-label" for="copy-mode-desc">
                                                {{ __('branches.settings.copy_descendants', ['count' => $childCount]) }}
                                            </label>
                                        </div>
                                        <div class="custom-control custom-radio">
                                            <input type="radio" class="custom-control-input" id="copy-mode-sel" name="mode" value="selected">
                                            <label class="custom-control-label" for="copy-mode-sel">{{ __('branches.settings.copy_selected') }}</label>
                                        </div>
                                    </div>

                                    <div class="form-group d-none" id="copy-targets-wrap">
                                        <label for="copy-targets">{{ __('branches.settings.copy_targets') }}</label>
                                        <select id="copy-targets" name="target_ids[]" class="form-control" multiple size="10">
                                            @foreach ($branchOptions as $opt)
                                                @continue((int) $opt['id'] === (int) $selectedBranchId)
                                                <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">{{ __('branches.settings.copy_targets_hint') }}</small>
                                    </div>

                                    <div class="form-group">
                                        <label>{{ __('branches.settings.copy_groups') }}</label>
                                        <div class="d-flex flex-wrap" style="gap:.75rem">
                                            @foreach ($groupsMeta as $groupKey => $meta)
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="copy-g-{{ $groupKey }}"
                                                           name="groups[]" value="{{ $groupKey }}">
                                                    <label class="custom-control-label" for="copy-g-{{ $groupKey }}">{{ $meta['label'] }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                        <small class="text-muted">{{ __('branches.settings.copy_groups_hint') }}</small>
                                    </div>

                                    <button type="submit" class="btn btn-info">
                                        <i class="feather icon-copy"></i> {{ __('branches.settings.copy_action') }}
                                    </button>
                                </form>
                            </div>

                        </div>
                    </div>

                    @endcan

                    @can('branches.settings.reset')
                    <div class="col-lg-5 col-12">
                        <div class="card h-100">
                            <div class="sl-table-head">
                                <h6 class="sl-table-head__title"><i class="feather icon-rotate-ccw text-danger"></i> {{ __('branches.settings.reset_title') }}</h6>
                            </div>
                            <div class="card-body">
                                <p class="sl-help">
                                    {{ __('branches.settings.reset_intro', [
                                        'name' => $branch->name,
                                        'target' => $parentBranch ? '«' . $parentBranch->name . '»' : __('branches.settings.reset_defaults'),
                                    ]) }}
                                </p>

                                <form method="POST" action="{{ route('branches.settings_reset') }}"
                                      onsubmit="return confirm({{ Illuminate\Support\Js::from(__('branches.settings.reset_confirm')) }});">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">

                                    <div class="form-group">
                                        <label>{{ __('branches.settings.reset_groups') }}</label>
                                        <div class="d-flex flex-column" style="gap:.35rem">
                                            @foreach ($groupsMeta as $groupKey => $meta)
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="reset-g-{{ $groupKey }}"
                                                           name="groups[]" value="{{ $groupKey }}">
                                                    <label class="custom-control-label" for="reset-g-{{ $groupKey }}">{{ $meta['label'] }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                        <small class="text-muted">{{ __('branches.settings.reset_groups_hint') }}</small>
                                    </div>

                                    <button type="submit" class="btn btn-outline-danger" {{ $ownCount ? '' : 'disabled' }}>
                                        <i class="feather icon-rotate-ccw"></i> {{ __('branches.settings.reset_action') }}
                                    </button>
                                    @unless ($ownCount)
                                        <small class="d-block text-muted mt-50">{{ __('branches.settings.reset_none') }}</small>
                                    @endunless
                                </form>
                            </div>

                        </div>
                    </div>
                    @endcan
                </div>
            @elseif ($branch)
                <div class="card">
                    <div class="card-body">
                        <x-branches::empty icon="sliders" :title="__('branches.settings.empty_catalog')">
                            <p>
                                {{ __('branches.settings.empty_catalog_hint') }}
                            </p>
                            <span class="stk-num" style="font-size:.82rem;padding:.4rem .8rem">php artisan branches:sync-settings</span>
                        </x-branches::empty>
                    </div>
                </div>
            @endif
        @endif
    </div>
@endsection

@section('scripts')
    <script>
        (function () {
            var form = document.getElementById('branch-settings-form');
            if (!form) return;

            // ------- «اتبع الأب»: تعطيل الحقل لأن الفرع لن يحتفظ بقيمة خاصة -------
            function applyInherit(toggle) {
                var target = document.getElementById(toggle.dataset.target);
                if (!target) return;

                var box = toggle.closest('.setting-item');
                target.disabled = toggle.checked;
                if (box) box.querySelector('.setting-control').style.opacity = toggle.checked ? '.45' : '1';

                var badge = box && box.querySelector('.source-badge');
                if (badge && !badge.dataset.original) badge.dataset.original = badge.textContent.trim();
            }

            document.querySelectorAll('.inherit-toggle').forEach(function (toggle) {
                applyInherit(toggle);
                toggle.addEventListener('change', function () { applyInherit(toggle); });
            });

            // ------- تسمية مفتاح التبديل تتبع حالته -------
            document.querySelectorAll('.setting-input[data-type="boolean"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    // النصّ يعيش الآن في `.stk-switch__title` داخل المفتاح نفسه
                    // بعد الانتقال إلى مفتاح نظام التصميم؛ و`closest` أمتنُ من
                    // `parentElement` لأنها لا تنكسر إن تغيّر عمقُ التغليف.
                    var label = input.closest('.stk-switch');
                    label = label && label.querySelector('.stk-switch__title');
                    if (label) label.textContent = input.checked ? @json(__('branches.common.enabled')) : @json(__('branches.common.disabled'));
                });
            });

            // ------- تفعيل/إيقاف كل بوليانيات التبويب -------
            document.querySelectorAll('.btn-bulk').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var on = btn.dataset.on === '1';
                    document
                        .querySelectorAll('[data-group-pane="' + btn.dataset.group + '"] .setting-input[data-type="boolean"]')
                        .forEach(function (input) {
                            if (input.disabled) return; // يتبع الأب — لا يُلمس
                            input.checked = on;
                            input.dispatchEvent(new Event('change'));
                        });
                });
            });

            // ------- البحث عبر كل التبويبات -------
            var search = document.getElementById('settings-search');
            var items = Array.prototype.slice.call(document.querySelectorAll('.setting-item'));
            var panes = Array.prototype.slice.call(document.querySelectorAll('[data-group-pane]'));
            var tabs = Array.prototype.slice.call(document.querySelectorAll('#settings-tabs .nav-link'));
            var empty = document.getElementById('no-search-results');

            if (search) {
                search.addEventListener('input', function () {
                    var term = search.value.trim().toLowerCase();
                    var searching = term.length > 0;
                    var total = 0;

                    items.forEach(function (item) {
                        var hit = !searching || item.dataset.search.indexOf(term) !== -1;
                        item.classList.toggle('d-none', !hit);
                        if (hit) total++;
                    });

                    // أثناء البحث تُعرض كل التبويبات معاً حتى لا تختفي نتيجة
                    // في تبويب مغلق ويظن المستخدم أن الإعداد غير موجود.
                    panes.forEach(function (pane) {
                        var visible = pane.querySelectorAll('.setting-item:not(.d-none)').length;
                        pane.classList.toggle('active', searching ? visible > 0 : pane.dataset.groupPane === document.getElementById('active-group-input').value);
                    });

                    tabs.forEach(function (tab) {
                        var pane = document.querySelector('[data-group-pane="' + tab.dataset.group + '"]');
                        var count = pane ? pane.querySelectorAll('.setting-item:not(.d-none)').length : 0;
                        var badge = tab.querySelector('.tab-count');
                        if (badge) badge.textContent = count;
                        tab.classList.toggle('text-muted', searching && count === 0);
                    });

                    if (empty) empty.classList.toggle('d-none', total > 0);
                });
            }

            // ------- تبديل التبويبات + تذكّر المفتوح منها عبر الحفظ -------
            function activate(groupKey) {
                document.getElementById('active-group-input').value = groupKey;
                tabs.forEach(function (t) { t.classList.toggle('active', t.dataset.group === groupKey); });
                panes.forEach(function (p) { p.classList.toggle('active', p.dataset.groupPane === groupKey); });
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function (e) {
                    e.preventDefault();
                    if (search) search.value = '';
                    items.forEach(function (item) { item.classList.remove('d-none'); });
                    if (empty) empty.classList.add('d-none');
                    tabs.forEach(function (t) {
                        var pane = document.querySelector('[data-group-pane="' + t.dataset.group + '"]');
                        var badge = t.querySelector('.tab-count');
                        if (badge && pane) badge.textContent = pane.querySelectorAll('.setting-item').length;
                        t.classList.remove('text-muted');
                    });
                    activate(tab.dataset.group);
                });
            });

            // ------- تحذير عند مغادرة الصفحة بتغييرات غير محفوظة -------
            var dirty = false;
            form.addEventListener('change', function () { dirty = true; });
            form.addEventListener('submit', function () { dirty = false; });
            window.addEventListener('beforeunload', function (e) {
                if (!dirty) return;
                e.preventDefault();
                e.returnValue = '';
            });

            // ------- أداة النسخ: إظهار قائمة الفروع عند اختيار «فروع أختارها» -------
            var wrap = document.getElementById('copy-targets-wrap');
            document.querySelectorAll('input[name="mode"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    if (wrap) wrap.classList.toggle('d-none', radio.value !== 'selected' || !radio.checked);
                });
            });
        })();
    </script>
@endsection
