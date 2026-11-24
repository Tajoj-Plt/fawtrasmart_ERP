@extends('taskmanager::master')

@section('title')
    تقرير جدول زمني المشاريع
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير جدول زمني المشاريع</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('tasks.manager') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.reports') }}">تقارير المهام</a></li>
                            <li class="breadcrumb-item active">جدول زمني المشاريع</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content-body">
        @include('layouts.alerts.success')
        @include('layouts.alerts.error')

        <div class="card">
            <div class="card-content">
                <div class="card-body">
                    <div class="card-title">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>بحث وفلترة</div>
                            <div>
                                <button class="btn btn-outline-success" id="exportExcel">
                                    <i class="fa fa-file-excel me-2"></i>تصدير إكسل
                                </button>
                                <button class="btn btn-outline-primary" id="printBtn">
                                    <i class="fa fa-print me-2"></i>طباعة
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <form class="form" id="reportForm" method="GET" action="{{ route('tasks.reports.project-timeline') }}">
                        <div class="form-body row">
                            <div class="form-group col-md-4">
                                <label for="">مساحة العمل</label>
                                <select name="workspace_id" id="workspace_id" class="form-control select2">
                                    <option value="">جميع مساحات العمل</option>
                                    @foreach ($workspaces as $workspace)
                                        <option value="{{ $workspace->id }}" {{ request('workspace_id') == $workspace->id ? 'selected' : '' }}>{{ $workspace->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="">المستخدم</label>
                                <select name="user_id" id="user_id" class="form-control select2">
                                    <option value="">جميع المستخدمين</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="">الحالة</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">جميع الحالات</option>
                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>نشط</option>
                                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>مكتمل</option>
                                    <option value="on_hold" {{ request('status') == 'on_hold' ? 'selected' : '' }}>معلق</option>
                                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغى</option>
                                </select>
                            </div>
                        </div>

                        <!-- Hidden Div للبحث المتقدم -->
                        <div class="collapse" id="advancedSearchForm">
                            <div class="form-body row">
                                <div class="col-md-4">
                                    <label for="" class="form-label">من تاريخ</label>
                                    <input type="date" name="from_date" id="from_date" class="form-control" value="{{ request('from_date', now()->subDays(30)->format('Y-m-d')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="" class="form-label">إلى تاريخ</label>
                                    <input type="date" name="to_date" id="to_date" class="form-control" value="{{ request('to_date', now()->format('Y-m-d')) }}">
                                </div>
                                <div class="col-md-4">
                                    <label for="">الأولوية</label>
                                    <select name="priority" id="priority" class="form-control">
                                        <option value="">جميع الأولويات</option>
                                        <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>منخفضة</option>
                                        <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>متوسطة</option>
                                        <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>عالية</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-primary mr-1 waves-effect waves-light" id="filterBtn">بحث</button>
                            <a class="btn btn-outline-secondary ml-2 mr-2" data-toggle="collapse" data-target="#advancedSearchForm">
                                <i class="bi bi-sliders"></i> بحث متقدم
                            </a>
                            <button type="button" class="btn btn-outline-danger waves-effect waves-light" id="resetBtn">الغاء الفلترة</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card income-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-success p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-trending-up text-success font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="totalProjects">
                            {{ number_format($totalProjects, 0) }}
                        </h2>
                        <p class="mb-0">إجمالي المشاريع</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card expense-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-primary p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-check text-primary font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="onTimeProjects">
                            {{ number_format($onTimeProjects, 0) }}
                        </h2>
                        <p class="mb-0">مشاريع في الوقت المحدد</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card account-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-warning p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-clock text-warning font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="delayedProjects">
                            {{ number_format($delayedProjects, 0) }}
                        </h2>
                        <p class="mb-0">مشاريع متأخرة</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card treasury-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-danger p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-alert-triangle text-danger font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="completionRate">
                            {{ number_format($onTimeProjects > 0 ? ($onTimeProjects / max($totalProjects, 1)) * 100 : 0, 2) }}%
                        </h2>
                        <p class="mb-0">معدل الإنجاز في الوقت</p>
                    </div>
                </div>
            </div>
        </div>
    <br>
        <!-- Chart Section -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">معدل الإنجاز في الوقت المحدد</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 400px;">
                            <canvas id="timelineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">تفاصيل جدول زمني المشاريع</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>المشروع</th>
                                        <th>مساحة العمل</th>
                                        <th>تاريخ البدء</th>
                                        <th>تاريخ الانتهاء المخطط</th>
                                        <th>تاريخ الانتهاء الفعلي</th>
                                        <th>المدة المخططة (أيام)</th>
                                        <th>المدة الفعلية (أيام)</th>
                                        <th>أيام التأخير</th>
                                        <th>في الوقت المحدد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($projectTimelineDetails as $project)
                                        <tr>
                                            <td>{{ $project->title }}</td>
                                            <td>{{ $project->workspace }}</td>
                                            <td>{{ $project->start_date ? $project->start_date->format('Y-m-d') : '-' }}</td>
                                            <td>{{ $project->end_date ? $project->end_date->format('Y-m-d') : '-' }}</td>
                                            <td>{{ $project->actual_end_date ? $project->actual_end_date->format('Y-m-d') : '-' }}</td>
                                            <td>{{ $project->planned_duration }}</td>
                                            <td>{{ $project->actual_duration }}</td>
                                            <td>{{ $project->delay_days }}</td>
                                            <td>
                                                @if($project->on_time)
                                                    <span class="badge badge-success">نعم</span>
                                                @else
                                                    <span class="badge badge-danger">لا</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Comparison Chart -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">مقارنة معدل الإنجاز في الوقت المحدد حسب مساحة العمل</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 400px;">
                            <canvas id="workspaceTimelineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        let timelineChart, workspaceTimelineChart;

        $(document).ready(function() {
            // Initialize charts with server-side data
            updateCharts();

            // Filter button
            $('#filterBtn').click(function() {
                $('#reportForm').submit();
            });

            // Reset button
            $('#resetBtn').click(function() {
                $('#reportForm')[0].reset();
                $('#from_date').val('{{ now()->subDays(30)->format('Y-m-d') }}');
                $('#to_date').val('{{ now()->format('Y-m-d') }}');
                $('#reportForm').submit();
            });

            // Export to Excel
            $('#exportExcel').click(function() {
                exportToExcel();
            });

            // Print
            $('#printBtn').click(function() {
                window.print();
            });
            
            // Initialize Select2
            $('.select2').select2();
        });

        // Update charts
        function updateCharts() {
            // Timeline Chart
            const timelineCtx = document.getElementById('timelineChart').getContext('2d');
            if (timelineChart) {
                timelineChart.destroy();
            }

            timelineChart = new Chart(timelineCtx, {
                type: 'line',
                data: {
                    labels: @json($months),
                    datasets: [{
                        label: 'معدل الإنجاز في الوقت المحدد',
                        data: @json($onTimeRates),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });

            // Workspace Timeline Chart
            const workspaceTimelineCtx = document.getElementById('workspaceTimelineChart').getContext('2d');
            if (workspaceTimelineChart) {
                workspaceTimelineChart.destroy();
            }

            workspaceTimelineChart = new Chart(workspaceTimelineCtx, {
                type: 'bar',
                data: {
                    labels: @json($workspaceLabels),
                    datasets: [{
                        label: 'معدل الإنجاز في الوقت المحدد',
                        data: @json($workspaceOnTimeRates),
                        backgroundColor: 'rgba(153, 102, 255, 0.8)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    }
                }
            });
        }

        // Export to Excel
        function exportToExcel() {
            const wb = XLSX.utils.book_new();

            // Summary data
            const summaryData = [
                ['تقرير جدول زمني المشاريع'],
                ['التاريخ:', `من ${$('#from_date').val()} إلى ${$('#to_date').val()}`],
                [''],
                ['البيان', 'المبلغ'],
                ['إجمالي المشاريع', $('#totalProjects').text()],
                ['مشاريع في الوقت المحدد', $('#onTimeProjects').text()],
                ['مشاريع متأخرة', $('#delayedProjects').text()],
                ['معدل الإنجاز في الوقت', $('#completionRate').text()]
            ];

            const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, summaryWs, 'ملخص التقرير');

            // Save file
            const today = new Date();
            const fileName = `تقرير_جدول_زمني_المشاريع_${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.xlsx`;

            XLSX.writeFile(wb, fileName);
            alert('تم تصدير الملف بنجاح!');
        }
    </script>
@endsection