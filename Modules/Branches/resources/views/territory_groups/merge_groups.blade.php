@extends('branches::layouts.master')
@section('title', __('branches.merge.tab_merge'))


@section('content')
    @php
        use App\Constants\planFeatures\BranchesPlanFeatures;
        use App\Support\PlanLock;

        $lockMerge = PlanLock::locked(BranchesPlanFeatures::MERGE);
    @endphp

    {{--
        كانت الترويسة مكتوبة يدوياً بفتاتِ تنقّلٍ ناقص، والتنبيهان
        صندوقَين (`info-box` / `warning-box`) بأيقونات FontAwesome
        بينما بقيّة الوحدة Feather. وصارت بنفس مكوّن باقي الشاشات.
    --}}
    <x-branches::page-head
        icon="git-merge"
        :title="__('branches.merge.tab_merge')"
        :subtitle="__('branches.merge.merge_intro')"
        :crumbs="[__('branches.tree.breadcrumb') => route('branches.index'), __('branches.merge.tab_merge') => null]" />

    <div class="content-body merge-container">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        {{-- تحذيرُ عمليةٍ لا رجعةَ فيها: يبقى لأنه يقول ما يقع قبل الوقوع،
             لا يشرح الشاشة. --}}
        <x-branches::explain tone="danger" :title="__('branches.merge.merge_warning_title')">
            {{ __('branches.merge.merge_warning') }}
        </x-branches::explain>

        {{-- النموذجُ بهيئة بقيّة الشاشات: بطاقةٌ للمصدر وبطاقةٌ للهدف، فالخيار،
             فذيلُ النموذج. كان صناديقَ مرصوصةً عمودياً بسهمٍ بينها، وبياناتُها
             فقراتٍ «اسم: قيمة» لا صفوفاً. --}}
        <form action="{{ route('groups.merge_groups', $sourceGroup->id) }}" method="POST" id="single-merge-form">
            @csrf

            <div class="row bx-sides">
                <div class="col-lg-6 col-12">
                    <div class="card bx-side bx-side--danger">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-folder-minus"></i> {{ __('branches.merge.merge_source') }}
                            </h4>
                        </div>
                        <div class="card-body">
                            <dl class="sl-def mb-0">
                                <div class="sl-def__row"><dt>{{ __('branches.common.name') }}</dt><dd>{{ $sourceGroup->name }}</dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.merge.node_kind') }}</dt><dd>{{ $sourceGroup->displayTypeLabel() }}</dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.merge.node_path') }}</dt><dd>{{ $sourceGroup->lineagePathLabel() }}</dd></div>
                                <div class="sl-def__row">
                                    <dt>{{ __('branches.merge.node_clients') }}</dt>
                                    <dd><strong class="bx-tone--primary">{{ number_format($sourceGroup->neighborhoods->count()) }}</strong></dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-12">
                    <div class="card bx-side bx-side--success">
                        <div class="card-header">
                            <h4 class="card-title">
                                <i class="feather icon-folder-plus"></i> {{ __('branches.merge.merge_target') }}
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="target_group_id" class="sl-req">{{ __('branches.merge.target_group_strong') }}</label>
                                <select name="target_group_id" id="target_group_id" class="form-control select2" required>
                                    <option value="">{{ __('branches.merge.choose_group') }}</option>
                                    {{-- القائمةُ صارت كلَّ عناصر الهيكل لا المجموعات وحدها،
                                         فيلزم أن يقول الخيارُ نوعَه ومساره: أسماءٌ متشابهة تحت
                                         آباءٍ مختلفين لا تُميَّز بالاسم وحده. --}}
                                    @foreach ($targetGroups as $group)
                                        <option value="{{ $group->id }}" data-name="{{ $group->name }}"
                                                data-type="{{ $group->displayTypeLabel() }}"
                                                data-path="{{ $group->lineagePathLabel() }}">
                                            {{ $group->lineagePathLabel() }} — {{ $group->displayTypeLabel() }}
                                        </option>
                                    @endforeach
                                </select>

                                {{--
                                    الضمُّ يقع في عنصرٍ هدف، ولا هدفَ إن لم يكن في الشجرة
                                    عنصرٌ آخر غير المصدر. الزرُّ لا يظهر إلا حين تفرغ
                                    القائمة، ويقود إلى إنشاء عقدةٍ جديدة.
                                --}}
                                <x-create-first
                                    :count="count($targetGroups ?? [])"
                                    route="branches.create"
                                    permission="branches.create"
                                    :plan="\App\Constants\planFeatures\BranchesPlanFeatures::BRANCHES"
                                    label="عنصراً"
                                    hint="النقلُ يحتاج عنصراً هدفاً غير المصدر، ولا عنصرَ آخر في الهيكل بعد." />

                                @error('target_group_id')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>

                            <dl id="target-group-display" class="sl-def bx-side__facts" style="display:none">
                                <div class="sl-def__row"><dt>{{ __('branches.common.name') }}</dt><dd id="target-group-name"></dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.merge.node_kind') }}</dt><dd id="target-group-type"></dd></div>
                                <div class="sl-def__row"><dt>{{ __('branches.merge.node_path') }}</dt><dd id="target-group-path"></dd></div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    {{-- حذفُ المصدر خيارٌ صريح لا أثرٌ لازم للنقل: كان يقع
                         دائماً، فمن أراد نقل عملاء من فرعٍ إلى آخر يفقد
                         الفرع نفسَه. --}}
                    <label class="stk-switch mb-0">
                        <input type="hidden" name="delete_source" value="0">
                        <input type="checkbox" name="delete_source" value="1">
                        <span class="stk-switch__track"><span class="stk-switch__thumb"></span></span>
                        <span class="stk-switch__label">
                            <span class="stk-switch__title">{{ __('branches.merge.delete_source') }}</span>
                            <span class="stk-switch__hint">{{ __('branches.merge.delete_source_hint') }}</span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="sl-form-foot">
                <div class="d-flex flex-wrap" style="gap:.5rem">
                    @can('branches.view')
                        <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> {{ __('branches.common.cancel') }}
                        </a>
                    @endcan
                    <button type="submit" class="btn btn-danger" id="confirm-merge-btn" disabled
                            @if ($lockMerge) title="{{ PlanLock::reason(BranchesPlanFeatures::MERGE) }}" @endif>
                        <i class="feather icon-{{ $lockMerge ? 'lock' : 'git-merge' }}"></i> {{ __('branches.merge.merge_action') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    @php
        // النصوصُ تُحضَّر هنا وتُطبع بوسيطٍ واحد: كانت تُلصق داخل قالبٍ نصّيّ
        // بعلامات جمعٍ فتظهر العلاماتُ نفسُها في نافذة التأكيد
        $mergeSourceName = (string) $sourceGroup->name;
        $mergeSourcePath = (string) $sourceGroup->lineagePathLabel();
        $mergeConfirmQuestion = __('branches.merge.merge_confirm_q2', ['source' => ':s', 'target' => ':t']);
        $mergeConfirmNote = __('branches.merge.merge_note2');
    @endphp
    <script>
        $(document).ready(function () {
            var form = document.getElementById('single-merge-form');
            var select = document.getElementById('target_group_id');
            var facts = $('#target-group-display');
            var button = $('#confirm-merge-btn');

            // بيانُ الهدف من خصائص الخيار نفسِه — لا من مصفوفةٍ ثانيةٍ في الصفحة
            $(select).on('change', function () {
                var option = select.options[select.selectedIndex];

                if (select.value && option) {
                    $('#target-group-name').text(option.getAttribute('data-name') || '');
                    $('#target-group-type').text(option.getAttribute('data-type') || '');
                    $('#target-group-path').text(option.getAttribute('data-path') || '');
                    facts.slideDown();
                    button.prop('disabled', false);
                } else {
                    facts.slideUp();
                    button.prop('disabled', true);
                }
            });

            // تأكيدٌ قبل الإرسال: العمليةُ تنقل عملاءَ العنصر كلَّهم
            var confirmed = false;
            $(form).on('submit', function (e) {
                if (confirmed) { return; }
                e.preventDefault();

                var option = select.options[select.selectedIndex];
                var targetName = option ? (option.getAttribute('data-name') || '') : '';
                var deleting = $(form).find('input[type="checkbox"][name="delete_source"]').is(':checked');

                Swal.fire({
                    title: @json(__('branches.merge.merge_confirm_title')),
                    html: bxConfirm.body(
                        @json($mergeConfirmQuestion).replace(':s', @json($mergeSourceName)).replace(':t', targetName),
                        [
                            { label: @json(__('branches.merge.from_source')), value: @json($mergeSourcePath) },
                            { label: @json(__('branches.merge.to_target')), value: option ? (option.getAttribute('data-path') || '') : '' }
                        ],
                        deleting ? @json(__('branches.merge.confirm_delete_note')) : @json($mergeConfirmNote)
                    ),
                    icon: 'warning',
                    showCancelButton: true,
                    reverseButtons: true,
                    confirmButtonColor: bxConfirm.danger(),
                    cancelButtonColor: bxConfirm.muted,
                    confirmButtonText: @json(__('branches.merge.merge_confirm_ok')),
                    cancelButtonText: @json(__('branches.common.cancel'))
                }).then(function (result) {
                    if (result.isConfirmed) {
                        confirmed = true;
                        form.submit();
                    }
                });
            });
        });
    </script>
@endsection
