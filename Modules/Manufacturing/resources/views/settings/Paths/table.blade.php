{{--
    جدول مسارات الإنتاج — يُستبدل وحده عند البحث والتنقّل بـAjax.

    على قياس جدول فواتير المبيعات: مُنتقي عدد السجلّات فوق الجدول، وعمودُ
    «تابع لـ»، وترقيمٌ بأرقام الصفحات في الذيل.

    @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paths
--}}

@if ($paths->count() > 0)
    <div class="d-flex justify-content-end p-2 pb-0">
        @include('stock::components.per-page', ['onchange' => 'loadData(1)'])
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0" id="reportTable">
            <thead>
                <tr>
                    <th style="width:26%">المسار</th>
                    <th>المراحل</th>
                    <th style="width:16%">تابع لـ</th>
                    <th style="width:18%">أُضيف</th>
                    <th class="text-center" style="width:8%">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paths as $path)
                    <tr>
                        <td>
                            <div class="stk-media">
                                <span class="stk-media__thumb"><i class="feather icon-git-merge"></i></span>
                                <div class="stk-media__body">
                                    <a href="{{ route('manufacturing.paths.show', $path->id) }}" class="stk-media__title">
                                        {{ $path->name }}
                                    </a>
                                    <span class="stk-media__sub stk-num">#{{ $path->code }}</span>
                                </div>
                            </div>
                        </td>

                        <td>
                            <span class="stk-count">{{ $path->stages->count() }}</span>
                            <span class="stk-media__sub">
                                {{ \Illuminate\Support\Str::limit($path->stages->pluck('stage_name')->implode(' ← '), 70) ?: '—' }}
                            </span>
                        </td>

                        <td>{{ \Modules\Manufacturing\Support\BranchTree::name($path->branch_id) }}</td>

                        <td>
                            <span class="stk-num">{{ optional($path->created_at)->format('Y-m-d') ?: '—' }}</span>
                            <span class="stk-media__sub d-block">{{ optional($path->createdBy)->name ?: '—' }}</span>
                        </td>

                        <td class="text-center">
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false" aria-label="خيارات {{ $path->name }}">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="{{ route('manufacturing.paths.show', $path->id) }}">
                                        <i class="feather icon-eye"></i> عرض
                                    </a>
                                    @permission('stock.production_paths.edit')
                                        <a class="dropdown-item" href="{{ route('manufacturing.paths.edit', $path->id) }}">
                                            <i class="feather icon-edit-2"></i> تعديل
                                        </a>
                                    @endpermission
                                    @permission('stock.production_paths.delete')
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item text-danger delete-path" href="#"
                                           data-id="{{ $path->id }}" data-name="{{ $path->name }}">
                                            <i class="feather icon-trash-2"></i> حذف
                                        </a>
                                    @endpermission
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('manufacturing::partials.table-foot', ['paginator' => $paths])
@else
    <div class="stk-empty">
        <div class="stk-empty__icon"><i class="feather icon-git-merge"></i></div>
        <h5 class="stk-empty__title">لا مسارات إنتاج</h5>
        <p class="stk-empty__hint">لا مسارات تطابق البحث في هذه الجهة — غيّر الفلتر أو أضف مساراً جديداً</p>
    </div>
@endif
