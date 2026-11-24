@extends('branches::layouts.master')
@section('title', __('branches.merge.logs_title'))

@section('content')
    @php
        use App\Constants\planFeatures\BranchesPlanFeatures;
        use App\Support\PlanLock;

        $lockMerge = PlanLock::locked(BranchesPlanFeatures::MERGE);
    @endphp

    {{--
        كانت هذه الشاشة تكتب ترويستها بيدها بـ `style="display:flex"`
        وفتاتِ تنقّلٍ ناقص، وتعرض السجلَّ بطاقاتٍ ملوّنة بأيقونات
        FontAwesome بينما بقيّة الوحدة Feather.

        والسجلُّ صفوفٌ متجانسة — تاريخٌ ومصدرٌ وهدفٌ وعدد — فهو جدولٌ
        بطبعه لا بطاقات؛ وقد صار بنفس بنية جدول المصروفات.
    --}}
    <x-branches::page-head
        icon="rotate-ccw"
        :title="__('branches.merge.logs_title')"
        :subtitle="__('branches.merge.logs_subtitle')"
        :crumbs="[__('branches.tree.breadcrumb') => route('branches.index'), __('branches.merge.logs_link') => null]">
        <x-slot:actions>
            @can('branches.groups.merge')
                <a href="{{ $lockMerge ? PlanLock::url() : route('groups.merge_select_page') }}"
                   class="btn btn-primary {{ $lockMerge ? 'stk-plan-locked' : '' }}"
                   @if ($lockMerge) title="{{ PlanLock::reason(BranchesPlanFeatures::MERGE) }}" @endif>
                    <i class="feather icon-{{ $lockMerge ? 'lock' : 'git-merge' }}"></i> {{ __('branches.merge.tab_merge') }}
                </a>
            @endcan
            @can('branches.view')
                <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">
                    <i class="feather icon-git-branch"></i> {{ __('branches.show.the_tree') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-branches::page-head>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="card sl-table-card">
            <div class="sl-table-head">
                <h6 class="sl-table-head__title">
                    <i class="feather icon-list"></i> {{ __('branches.merge.logs_title') }}
                    <span class="sl-count">{{ $logs->total() }}</span>
                </h6>
            </div>

            @if ($logs->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('branches.merge.log_source') }}</th>
                                <th>{{ __('branches.merge.log_target') }}</th>
                                <th style="width:10%">{{ __('branches.merge.log_clients') }}</th>
                                <th style="width:14%">{{ __('branches.dashboard.since') }}</th>
                                <th style="width:12%">{{ __('branches.common.status') }}</th>
                                <th class="text-center" style="width:14%">{{ __('branches.common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td>{{ $log->source_group_name }}</td>
                                    <td>{{ $log->target_group_name }}</td>
                                    <td><span class="stk-num">{{ number_format($log->moved_count) }}</span></td>
                                    <td><span class="stk-num">{{ optional($log->created_at)->format('Y-m-d H:i') ?? '—' }}</span></td>
                                    <td>
                                        <span class="sl-state {{ $log->is_reversed ? 'sl-state--neutral' : 'sl-state--on' }}">
                                            {{ $log->is_reversed ? __('branches.merge.log_reverted') : __('branches.common.active') }}
                                        </span>
                                        @if ($log->is_reversed && $log->reversed_at)
                                            <small class="sl-help d-block">
                                                {{ __('branches.merge.log_at') }}: {{ $log->reversed_at->format('Y-m-d H:i') }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{-- التراجع صلاحيةٌ مستقلّة عن الدمج في المتحكّم
                                             (branches.groups.revert)، فلا يكفي أن يبلغ
                                             المستخدمُ الشاشةَ ليتراجع عمّا دمج غيرُه. --}}
                                        @if (! $log->is_reversed)
                                            @can('branches.groups.revert')
                                                <form method="POST" action="{{ route('groups.revert_merge', $log->id) }}" class="revert-form">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary revert-btn"
                                                            data-name="{{ $log->source_group_name }}">
                                                        <i class="feather icon-rotate-ccw"></i> {{ __('branches.merge.revert') }}
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endcan
                                        @else
                                            <span class="sl-help">{{ __('branches.merge.reverted_ok') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($logs->hasPages())
                    <div class="sl-table-foot">
                        {{ $logs->links() }}
                    </div>
                @endif
            @else
                <div class="p-1">
                    <x-branches::empty icon="inbox" :title="__('branches.merge.logs_empty')"
                                       :text="__('branches.merge.logs_empty_hint')">
                        @can('branches.groups.merge')
                            <a href="{{ $lockMerge ? PlanLock::url() : route('groups.merge_select_page') }}"
                               class="btn btn-primary {{ $lockMerge ? 'stk-plan-locked' : '' }}"
                               @if ($lockMerge) title="{{ PlanLock::reason(BranchesPlanFeatures::MERGE) }}" @endif>
                                <i class="feather icon-{{ $lockMerge ? 'lock' : 'git-merge' }}"></i> {{ __('branches.merge.start_merging') }}
                            </a>
                        @endcan
                    </x-branches::empty>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('.revert-form').on('submit', function(e) {
                const $form = $(this);
                if ($form.data('confirmed')) {
                    return; // مرّ التأكيد — دع الإرسال يكمل
                }
                e.preventDefault();
                const groupName = $form.find('.revert-btn').data('name');

                /*
                    نصُّ الحوار كان مكتوباً `' + نصّ + '` داخل قالبٍ نصّيّ
                    بعلامات الاقتباس المائلة — وذاك تركيبُ الجمع في السلاسل
                    العادية لا في القوالب النصّية. فكان الحوار يعرض علامات
                    الاقتباس وإشارات الجمع حرفياً حول السؤال. و`${...}` هي
                    صيغةُ الإقحام التي يفهمها القالب النصّيّ.
                */
                const question = @json(__('branches.merge.revert_confirm_q', ['name' => ':n'])).replace(':n', groupName);
                const note = @json(__('branches.merge.revert_confirm_note'));

                Swal.fire({
                    title: @json(__('branches.merge.revert_confirm_title')),
                    html: `
                        <div style="text-align: start;">
                            <p style="font-size: 16px; margin: 15px 0;">${question}</p>
                            <p style="color: #ff9f43; margin: 15px 0;">
                                <i class="feather icon-info"></i> ${note}
                            </p>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ff9f43',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: @json(__('branches.merge.revert_ok')),
                    cancelButtonText: @json(__('branches.common.cancel')),
                }).then((result) => {
                    if (result.isConfirmed) {
                        $form.data('confirmed', true);
                        $form.trigger('submit');
                    }
                });
            });
        });
    </script>
@endsection
