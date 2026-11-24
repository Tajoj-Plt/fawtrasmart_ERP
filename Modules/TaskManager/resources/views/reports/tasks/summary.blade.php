@extends('taskmanager::master')

@section('title')
    تقرير ملخص المهام
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير ملخص المهام</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('tasks.manager') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.reports') }}">تقارير المهام</a></li>
                            <li class="breadcrumb-item active">ملخص المهام</li>
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
                    <form class="form" id="reportForm" method="GET" action="{{ route('tasks.reports.tasks') }}">
                        <div class="form-body row">
                            <div class="form-group col-md-3">
                                <label for="">نوع المهمة</label>
                                <select name="task_type" id="task_type" class="form-control">
                                    <option value="">جميع أنواع المهام</option>
                                    <option value="component" {{ request('task_type') == 'component' ? 'selected' : '' }}>مكون</option>
                                    <option value="epic" {{ request('task_type') == 'epic' ? 'selected' : '' }}>إبيك</option>
                                    <option value="user_story" {{ request('task_type') == 'user_story' ? 'selected' : '' }}>قصة مستخدم</option>
                                    <option value="sub_task" {{ request('task_type') == 'sub_task' ? 'selected' : '' }}>مهمة فرعية</option>
                                    <option value="bug" {{ request('task_type') == 'bug' ? 'selected' : '' }}>خلل</option>
                                </select>
                            </div>
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
                                    <label for="">المشروع</label>
                                    <select name="project_id" id="project_id" class="form-control select2">
                                        <option value="">جميع المشاريع</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>{{ $project->title }}</option>
                                        @endforeach
                                    </select>
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
                                <i class="feather icon-loader text-primary font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="inProgressTasks">
                            {{ number_format($inProgressTasks, 0) }}
                        </h2>
                        <p class="mb-0">قيد التنفيذ</p>
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
                        <h2 class="text-bold-700 mt-1" id="completedTasks">
                            {{ number_format($completedTasks, 0) }}
                        </h2>
                        <p class="mb-0">المهام المكتملة</p>
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
                        <h2 class="text-bold-700 mt-1" id="overdueTasks">
                            {{ number_format($overdueTasks, 0) }}
                        </h2>
                        <p class="mb-0">المهام المتأخرة</p>
                    </div>
                </div>
            </div>
        </div>
    <br>
        <!-- Additional Statistics Cards -->
        <div class="row">
            <div class="col-lg-6 col-md-6 col-12">
                <div class="card stat-card" style="border-left: 4px solid #6610f2;">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar p-50 m-0" style="background-color: rgba(102, 16, 242, 0.12);">
                            <div class="avatar-content">
                                <i class="feather icon-bar-chart-2 font-medium-5" style="color: #6610f2;"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="completionRate">
                            {{ number_format($completionRate, 2) }}%
                        </h2>
                        <p class="mb-0">معدل الإنجاز</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6 col-12">
                <div class="card stat-card" style="border-left: 4px solid #20c997;">
                    <div class="card-header d-flex flex-column align-items-start pb-0">
                        <div class="avatar p-50 m-0" style="background-color: rgba(32, 201, 151, 0.12);">
                            <div class="avatar-content">
                                <i class="feather icon-layers font-medium-5" style="color: #20c997;"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="totalProjects">
                            {{ $totalProjects }}
                        </h2>
                        <p class="mb-0">إجمالي المشاريع</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">التوزيع حسب نوع المهام</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <h6 class="text-center mb-3">توزيع المهام حسب النوع</h6>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="taskTypeChart"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-6 col-12">
                                <h6 class="text-center mb-3">توزيع المهام حسب الحالة</h6>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="taskStatusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Charts -->
        <div class="row mt-2">
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">الاتجاه الزمني للمهام</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="tasksTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">توزيع المهام حسب الأولوية</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="taskPriorityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performers Section -->
        <div class="row mt-2">
            <div class="col-lg-4 col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">أهم المستخدمين</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>المستخدم</th>
                                        <th>المهام</th>
                                        <th>المكتملة</th>
                                        <th>النسبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topUsers as $user)
                                        <tr>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->total_tasks }}</td>
                                            <td>{{ $user->completed_tasks }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="mr-1">{{ number_format($user->completion_rate, 2) }}%</span>
                                                    <div class="progress progress-bar-success mb-0" style="height: 8px; width: 80px;">
                                                        <div class="progress-bar" role="progressbar" style="width: {{ $user->completion_rate }}%" aria-valuenow="{{ $user->completion_rate }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">أهم المشاريع</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>المشروع</th>
                                        <th>المهام</th>
                                        <th>المكتملة</th>
                                        <th>النسبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topProjects as $project)
                                        <tr>
                                            <td>{{ $project->title }}</td>
                                            <td>{{ $project->total_tasks }}</td>
                                            <td>{{ $project->completed_tasks }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="mr-1">{{ number_format($project->completion_rate, 2) }}%</span>
                                                    <div class="progress progress-bar-success mb-0" style="height: 8px; width: 80px;">
                                                        <div class="progress-bar" role="progressbar" style="width: {{ $project->completion_rate }}%" aria-valuenow="{{ $project->completion_rate }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">أهم مساحات العمل</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>مساحة العمل</th>
                                        <th>المهام</th>
                                        <th>المكتملة</th>
                                        <th>النسبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topWorkspaces as $workspace)
                                        <tr>
                                            <td>{{ $workspace->title }}</td>
                                            <td>{{ $workspace->total_tasks }}</td>
                                            <td>{{ $workspace->completed_tasks }}</td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="mr-1">{{ number_format($workspace->completion_rate, 2) }}%</span>
                                                    <div class="progress progress-bar-success mb-0" style="height: 8px; width: 80px;">
                                                        <div class="progress-bar" role="progressbar" style="width: {{ $workspace->completion_rate }}%" aria-valuenow="{{ $workspace->completion_rate }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                </div>
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
    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        let taskTypeChart, taskStatusChart, tasksTrendChart, taskPriorityChart;

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
            // Task Type Chart
            const taskTypeCtx = document.getElementById('taskTypeChart').getContext('2d');
            if (taskTypeChart) {
                taskTypeChart.destroy();
            }

            taskTypeChart = new Chart(taskTypeCtx, {
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

            // Task Status Chart
            const taskStatusCtx = document.getElementById('taskStatusChart').getContext('2d');
            if (taskStatusChart) {
                taskStatusChart.destroy();
            }

            taskStatusChart = new Chart(taskStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($taskStatusLabels),
                    datasets: [{
                        data: @json($taskStatusCounts),
                        backgroundColor: [
                            'rgba(158, 158, 158, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(76, 175, 80, 0.8)',
                            'rgba(244, 67, 54, 0.8)'
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

            // Tasks Trend Chart
            const tasksTrendCtx = document.getElementById('tasksTrendChart').getContext('2d');
            if (tasksTrendChart) {
                tasksTrendChart.destroy();
            }

            tasksTrendChart = new Chart(tasksTrendCtx, {
                type: 'line',
                data: {
                    labels: @json($months),
                    datasets: [{
                        label: 'المهام الجديدة',
                        data: @json($monthlyTasks),
                        borderColor: 'rgba(33, 150, 243, 1)',
                        backgroundColor: 'rgba(33, 150, 243, 0.2)',
                        fill: true
                    }, {
                        label: 'المهام المكتملة',
                        data: @json($monthlyCompletedTasks),
                        borderColor: 'rgba(76, 175, 80, 1)',
                        backgroundColor: 'rgba(76, 175, 80, 0.2)',
                        fill: true
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

            // Task Priority Chart
            const taskPriorityCtx = document.getElementById('taskPriorityChart').getContext('2d');
            if (taskPriorityChart) {
                taskPriorityChart.destroy();
            }

            taskPriorityChart = new Chart(taskPriorityCtx, {
                type: 'bar',
                data: {
                    labels: @json($taskPriorityLabels),
                    datasets: [{
                        label: 'عدد المهام',
                        data: @json($taskPriorityCounts),
                        backgroundColor: [
                            'rgba(76, 175, 80, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(244, 67, 54, 0.8)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
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
                ['تقارير المهام والمشاريع ومساحات العمل'],
                ['التاريخ:', `من ${$('#from_date').val()} إلى ${$('#to_date').val()}`],
                [''],
                ['البيان', 'المبلغ'],
                ['إجمالي المهام', $('#totalTasks').text()],
                ['قيد التنفيذ', $('#inProgressTasks').text()],
                ['المهام المكتملة', $('#completedTasks').text()],
                ['المهام المتأخرة', $('#overdueTasks').text()],
                ['معدل الإنجاز', $('#completionRate').text()],
                ['إجمالي المشاريع', $('#totalProjects').text()]
            ];

            const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, summaryWs, 'ملخص التقرير');

            // Save file
            const today = new Date();
            const fileName = `تقرير_المهام_${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.xlsx`;

            XLSX.writeFile(wb, fileName);
            alert('تم تصدير الملف بنجاح!');
        }
    </script>
@endsection