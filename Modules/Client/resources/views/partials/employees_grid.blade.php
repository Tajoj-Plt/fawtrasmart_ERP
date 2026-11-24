{{--
    شبكة/جدول موظفي العملاء — تُستدعى عند التحميل الأول من
    `client_employees_directory.blade.php` وعند كل تحديث AJAX من
    `ClientEmployeeController::clientEmployeesDirectory()`، فالتصميم واحد
    في الحالتين ولا حاجة لتكرار الترميز.
--}}
@if ($employees->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="employeesTable">
            <thead>
                <tr>
                    <th style="width:44px">#</th>
                    <th>الموظف</th>
                    <th style="width:18%">الهاتف</th>
                    <th style="width:24%">يعمل لدى</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($employees as $employee)
                    <tr>
                        <td>{{ $employees->firstItem() + $loop->index }}</td>
                        <td>
                            <div class="cl-media">
                                @if ($employee->image)
                                    <img src="{{ asset($employee->image) }}" alt="" class="cl-media__thumb"
                                         style="cursor:zoom-in" onclick="openImageModal(this.src)">
                                @else
                                    <span class="cl-media__thumb"><i class="feather icon-user"></i></span>
                                @endif
                                <div class="cl-media__body">
                                    <strong class="cl-media__title">
                                        {{ $employee->employee_name ?? 'غير محدد' }}
                                        @if ($employee->is_primary)
                                            <span class="cl-state cl-state--on" style="margin-inline-start:.4rem">
                                                <i class="fa fa-star"></i> رئيسي
                                            </span>
                                        @endif
                                    </strong>
                                    <span class="cl-media__sub">
                                        <i class="fa fa-briefcase"></i> {{ $employee->employee_position ?? 'موظف' }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @php $employeePhone = \Modules\Client\Support\ContactNumbers::first($employee->employee_phone); @endphp
                            @if ($employeePhone)
                                <a href="tel:{{ $employeePhone }}" class="cl-state cl-state--neutral" dir="ltr">
                                    <i class="fa fa-phone"></i> {{ $employeePhone }}
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary" style="padding:.1rem .4rem"
                                        onclick="copyPhone('{{ $employeePhone }}', this)" title="نسخ الرقم">
                                    <i class="fa fa-copy"></i>
                                </button>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($employee->client)
                                <a href="{{ route('clients.show', $employee->client_id) }}">
                                    <i class="fa fa-building"></i> {{ $employee->client->trade_name }}
                                </a>
                                <span class="cl-count">{{ $employee->client->code }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- نفسُ ترقيم «إدارة العملاء»؛ روابطُه تلتقطها `loadEmployees()` بقراءة `href` --}}
    <div class="cl-table-foot" id="paginationContainer">
        {{ $employees->appends(request()->except('page'))->links('client::partials.link-pagination') }}
    </div>
@else
    <div class="cl-empty">
        <span class="cl-empty__icon"><i class="fas fa-users-slash"></i></span>
        <p class="cl-empty__title">لا يوجد موظفين</p>
        <p class="cl-empty__hint">لم يتم العثور على أي موظفين للعملاء بناءً على معايير البحث.</p>
    </div>
@endif
