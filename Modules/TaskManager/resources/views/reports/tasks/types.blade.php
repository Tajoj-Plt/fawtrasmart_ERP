@extends('taskmanager::master')

@section('title')
    تقرير توزيع المهام حسب النوع
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير توزيع المهام حسب النوع</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('tasks.manager') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.reports') }}">تقارير المهام</a></li>
                            <li class="breadcrumb-item active">توزيع المهام حسب النوع</li>
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
                    <form class="form" id="reportForm" method="GET" action="{{ route('tasks.reports.task-types') }}">
                        <div class="form-body row">
                            <div class="form-group col-md-3">
                                <label for="">الحالة</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">جميع الحالات</option>
                                    <option value="not_started" {{ request('status') == 'not_started' ? 'selected' : '' }}>لم تبدأ</option>
                                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>قيد التنفيذ</option>
                                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>مكتملة</option>
                                    <option value="overdue" {{ request('status') == 'overdue' ? 'selected' : '' }}>متأخرة</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">الأولوية</label>
                                <select name="priority" id="priority" class="form-control">
                                    <option value="">جميع الأولويات</option>
                                    <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>منخفضة</option>
                                    <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>متوسطة</option>
                                    <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>عالية</option>
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">المستخدم</label>
                                <select name="user_id" id="user_id" class="form-control select2">
                                    <option value="">جميع المستخدمين</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="">المشروع</label>
                                <select name="project_id" id="project_id" class="form-control select2">
                                    <option value="">جميع المشاريع</option>
                                    @foreach ($projects as $project)
                                        <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>{{ $project->title }}</option>
                                    @endforeach
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
                                <div class="form-group col-4">
                                    <label for="">مساحة العمل</label>
                                    <select name="workspace_id" id="workspace_id" class="form-control select2">
                                        <option value="">جميع مساحات العمل</option>
                                        @foreach ($workspaces as $workspace)
                                            <option value="{{ $workspace->id }}" {{ request('workspace_id') == $workspace->id ? 'selected' : '' }}>{{ $workspace->title }}</option>
                                        @endforeach
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
                        <h2 class="text-bold-700 mt-1" id="totalTasks">
                            {{ number_format($totalTasks, 0) }}
                        </h2>
                        <p class="mb-0">إجمالي المهام</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card expense-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-primary p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-layers text-primary font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="taskTypesCount">
                            {{ number_format($taskTypesCount, 0) }}
                        </h2>
                        <p class="mb-0">أنواع المهام</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card account-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-warning p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-check text-warning font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="mostCommonTypeCount">
                            {{ number_format($mostCommonTypeCount, 0) }}
                        </h2>
                        <p class="mb-0">أكثر نوع شيوعاً</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 col-12">
                <div class="card stat-card treasury-card">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar bg-rgba-danger p-50 m-0">
                            <div class="avatar-content">
                                <i class="feather icon-clock text-danger font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="leastCommonTypeCount">
                            {{ number_format($leastCommonTypeCount, 0) }}
                        </h2>
                        <p class="mb-0">أقل نوع شيوعاً</p>
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
                        <h4 class="card-title">توزيع المهام حسب النوع</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 400px;">
                            <canvas id="taskTypesChart"></canvas>
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
                        <h4 class="card-title">تفاصيل توزيع المهام حسب النوع</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>نوع المهمة</th>
                                        <th>العدد</th>
                                        <th>النسبة</th>
                                        <th>قيد التنفيذ</th>
                                        <th>مكتملة</th>
                                        <th>متأخرة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($taskTypesDetail as $type)
                                        <tr>
                                            <td>{{ $type->name }}</td>
                                            <td>{{ $type->count }}</td>
                                            <td>{{ number_format($type->percentage, 2) }}%</td>
                                            <td>{{ $type->in_progress }}</td>
                                            <td>{{ $type->completed }}</td>
                                            <td>{{ $type->overdue }}</td>
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
                        <h4 class="card-title">مقارنة بين أنواع المهام</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 400px;">
                            <canvas id="taskTypesComparisonChart"></canvas>
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
        let taskTypesChart, taskTypesComparisonChart;

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
            // Task Types Chart
            const taskTypesCtx = document.getElementById('taskTypesChart').getContext('2d');
            if (taskTypesChart) {
                taskTypesChart.destroy();
            }

            taskTypesChart = new Chart(taskTypesCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($taskTypeLabels),
                    datasets: [{
                        data: @json($taskTypeCounts),
                        backgroundColor: [
                            'rgba(76, 175, 80, 0.8)',
                            'rgba(33, 150, 243, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(156, 39, 176, 0.8)',
                            'rgba(255, 87, 34, 0.8)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Task Types Comparison Chart
            const taskTypesComparisonCtx = document.getElementById('taskTypesComparisonChart').getContext('2d');
            if (taskTypesComparisonChart) {
                taskTypesComparisonChart.destroy();
            }

            taskTypesComparisonChart = new Chart(taskTypesComparisonCtx, {
                type: 'bar',
                data: {
                    labels: @json($taskTypeLabels),
                    datasets: [
                        {
                            label: 'قيد التنفيذ',
                            data: @json($inProgressCounts),
                            backgroundColor: 'rgba(255, 193, 7, 0.8)',
                            borderWidth: 1
                        },
                        {
                            label: 'مكتملة',
                            data: @json($completedCounts),
                            backgroundColor: 'rgba(76, 175, 80, 0.8)',
                            borderWidth: 1
                        },
                        {
                            label: 'متأخرة',
                            data: @json($overdueCounts),
                            backgroundColor: 'rgba(244, 67, 54, 0.8)',
                            borderWidth: 1
                        }
                    ]
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
                ['تقرير توزيع المهام حسب النوع'],
                ['التاريخ:', `من ${$('#from_date').val()} إلى ${$('#to_date').val()}`],
                [''],
                ['البيان', 'المبلغ'],
                ['إجمالي المهام', $('#totalTasks').text()],
                ['أنواع المهام', $('#taskTypesCount').text()],
                ['أكثر نوع شيوعاً', $('#mostCommonTypeCount').text()],
                ['أقل نوع شيوعاً', $('#leastCommonTypeCount').text()]
            ];

            const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, summaryWs, 'ملخص التقرير');

            // Save file
            const today = new Date();
            const fileName = `تقرير_أنواع_المهام_${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.xlsx`;

            XLSX.writeFile(wb, fileName);
            alert('تم تصدير الملف بنجاح!');
        }
    </script>
@endsection