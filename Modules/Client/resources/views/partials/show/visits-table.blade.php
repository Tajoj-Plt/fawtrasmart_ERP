{{-- @if (isset($visits) && $visits->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>رقم الزيارة</th>
                    <th>تاريخ الزيارة</th>
                    <th>وقت الانصراف</th>
                    <th>الموظف</th>
                    <th>ملاحظات</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($visits as $visit)
                    <tr>
                        <td><strong>#{{ $visit->id }}</strong></td>
                        <td>{{ $visit->visit_date }}</td>
                        <td>{{ $visit->departure_time ?? 'لم ينصرف بعد' }}</td>
                        <td>{{ $visit->employee->name ?? 'غير محدد' }}</td>
                        <td>{{ Str::limit($visit->notes, 50) ?? '--' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($visits->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="pagination-info text-muted">
                عرض {{ $visits->firstItem() ?? 0 }} إلى {{ $visits->lastItem() ?? 0 }} من {{ $visits->total() }} زيارة
            </div>
            <nav aria-label="Page navigation">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ $visits->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $visits->currentPage() - 1 }}"
                           data-tab="visits"
                           {{ $visits->onFirstPage() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-right"></i>
                        </a>
                    </li>

                    @php
                        $start = max($visits->currentPage() - 2, 1);
                        $end = min($start + 4, $visits->lastPage());
                        if ($end - $start < 4) {
                            $start = max($end - 4, 1);
                        }
                    @endphp

                    @for ($i = $start; $i <= $end; $i++)
                        <li class="page-item {{ $visits->currentPage() == $i ? 'active' : '' }}">
                            <a class="page-link pagination-link"
                               href="#"
                               data-page="{{ $i }}"
                               data-tab="visits">
                                {{ $i }}
                            </a>
                        </li>
                    @endfor

                    <li class="page-item {{ !$visits->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link pagination-link"
                           href="#"
                           data-page="{{ $visits->currentPage() + 1 }}"
                           data-tab="visits"
                           {{ !$visits->hasMorePages() ? 'aria-disabled="true"' : '' }}>
                            <i class="fa fa-chevron-left"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    @endif
@else
    <div class="empty-state">
        <i class="fas fa-walking"></i>
        <h5>لا توجد زيارات</h5>
        <p>لم يتم تسجيل أي زيارات لهذا العميل بعد</p>
    </div>
@endif --}}