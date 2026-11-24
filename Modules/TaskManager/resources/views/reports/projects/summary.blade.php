@extends('taskmanager::master')

@section('title')
    تقرير ملخص المشاريع
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير ملخص المشاريع</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('tasks.manager') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.reports') }}">تقارير المهام</a></li>
                            <li class="breadcrumb-item active">ملخص المشاريع</li>
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
                    <form class="form" id="reportForm" method="GET" action="{{ route('tasks.reports.projects') }}">
                        <div class="form-body row">
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
                                <label for="">مساحة العمل</label>
                                <select name="workspace_id" id="workspace_id" class="form-control select2">
                                    <option value="">جميع مساحات العمل</option>
                                    @foreach ($workspaces as $workspace)
                                        <option value="{{ $workspace->id }}" {{ request('workspace_id') == $workspace->id ? 'selected' : '' }}>{{ $workspace->title }}</option>
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
                                <i class="feather icon-loader text-primary font-medium-5"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="activeProjects">
                            {{ number_format($activeProjects, 0) }}
                        </h2>
                        <p class="mb-0">المشاريع النشطة</p>
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
                        <h2 class="text-bold-700 mt-1" id="completedProjects">
                            {{ number_format($completedProjects, 0) }}
                        </h2>
                        <p class="mb-0">المشاريع المكتملة</p>
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
                        <h2 class="text-bold-700 mt-1" id="overdueProjects">
                            {{ number_format($overdueProjects, 0) }}
                        </h2>
                        <p class="mb-0">المشاريع المتأخرة</p>
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
                        <p class="mb-0">معدل إنجاز المشاريع</p>
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
                        <h2 class="text-bold-700 mt-1" id="totalTasks">
                            {{ number_format($totalTasks, 0) }}
                        </h2>
                        <p class="mb-0">إجمالي المهام</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">توزيع المشاريع حسب الحالة</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <h6 class="text-center mb-3">توزيع المشاريع حسب الحالة</h6>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="projectStatusChart"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-6 col-12">
                                <h6 class="text-center mb-3">توزيع المشاريع حسب الأولوية</h6>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="projectPriorityChart"></canvas>
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
                        <h4 class="card-title">الاتجاه الزمني للمشاريع</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="projectsTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">توزيع المشاريع حسب مساحات العمل</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="workspaceDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performers Section -->
        <div class="row mt-2">
            <div class="col-lg-6 col-md-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">أهم المشاريع حسب الإنجاز</h4>
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

            <div class="col-lg-6 col-md-6 col-12">
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
                                        <th>المشاريع</th>
                                        <th>المكتملة</th>
                                        <th>النسبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topWorkspaces as $workspace)
                                        <tr>
                                            <td>{{ $workspace->title }}</td>
                                            <td>{{ $workspace->total_projects }}</td>
                                            <td>{{ $workspace->completed_projects }}</td>
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
        let projectStatusChart, projectPriorityChart, projectsTrendChart, workspaceDistributionChart;

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
            // Project Status Chart
            const projectStatusCtx = document.getElementById('projectStatusChart').getContext('2d');
            if (projectStatusChart) {
                projectStatusChart.destroy();
            }

            projectStatusChart = new Chart(projectStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($projectStatusLabels),
                    datasets: [{
                        data: @json($projectStatusCounts),
                        backgroundColor: [
                            'rgba(76, 175, 80, 0.8)',
                            'rgba(33, 150, 243, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
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

            // Project Priority Chart
            const projectPriorityCtx = document.getElementById('projectPriorityChart').getContext('2d');
            if (projectPriorityChart) {
                projectPriorityChart.destroy();
            }

            projectPriorityChart = new Chart(projectPriorityCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($projectPriorityLabels),
                    datasets: [{
                        data: @json($projectPriorityCounts),
                        backgroundColor: [
                            'rgba(76, 175, 80, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
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

            // Projects Trend Chart
            const projectsTrendCtx = document.getElementById('projectsTrendChart').getContext('2d');
            if (projectsTrendChart) {
                projectsTrendChart.destroy();
            }

            projectsTrendChart = new Chart(projectsTrendCtx, {
                type: 'line',
                data: {
                    labels: @json($months),
                    datasets: [{
                        label: 'المشاريع الجديدة',
                        data: @json($monthlyProjects),
                        borderColor: 'rgba(33, 150, 243, 1)',
                        backgroundColor: 'rgba(33, 150, 243, 0.2)',
                        fill: true
                    }, {
                        label: 'المشاريع المكتملة',
                        data: @json($monthlyCompletedProjects),
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

            // Workspace Distribution Chart
            const workspaceDistributionCtx = document.getElementById('workspaceDistributionChart').getContext('2d');
            if (workspaceDistributionChart) {
                workspaceDistributionChart.destroy();
            }

            workspaceDistributionChart = new Chart(workspaceDistributionCtx, {
                type: 'bar',
                data: {
                    labels: @json($workspaceLabels),
                    datasets: [{
                        label: 'عدد المشاريع',
                        data: @json($workspaceCounts),
                        backgroundColor: 'rgba(156, 39, 176, 0.8)',
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
                ['تقرير ملخص المشاريع'],
                ['التاريخ:', `من ${$('#from_date').val()} إلى ${$('#to_date').val()}`],
                [''],
                ['البيان', 'المبلغ'],
                ['إجمالي المشاريع', $('#totalProjects').text()],
                ['المشاريع النشطة', $('#activeProjects').text()],
                ['المشاريع المكتملة', $('#completedProjects').text()],
                ['المشاريع المتأخرة', $('#overdueProjects').text()],
                ['معدل إنجاز المشاريع', $('#completionRate').text()],
                ['إجمالي المهام', $('#totalTasks').text()]
            ];

            const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, summaryWs, 'ملخص التقرير');

            // Save file
            const today = new Date();
            const fileName = `تقرير_المشاريع_${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.xlsx`;

            XLSX.writeFile(wb, fileName);
            alert('تم تصدير الملف بنجاح!');
        }
    </script>
@endsection