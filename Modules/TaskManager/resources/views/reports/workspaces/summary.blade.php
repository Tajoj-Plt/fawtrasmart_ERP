@extends('taskmanager::master')

@section('title')
    تقرير ملخص مساحات العمل
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقرير ملخص مساحات العمل</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('tasks.manager') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.reports') }}">تقارير المهام</a></li>
                            <li class="breadcrumb-item active">ملخص مساحات العمل</li>
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
                    <form class="form" id="reportForm" method="GET" action="{{ route('tasks.reports.workspaces') }}">
                        <div class="form-body row">
                            <div class="form-group col-md-6">
                                <label for="">المستخدم</label>
                                <select name="user_id" id="user_id" class="form-control select2">
                                    <option value="">جميع المستخدمين</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="">الحالة</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">جميع الحالات</option>
                                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>نشط</option>
                                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>غير نشط</option>
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
                        <h2 class="text-bold-700 mt-1" id="totalWorkspaces">
                            {{ number_format($totalWorkspaces, 0) }}
                        </h2>
                        <p class="mb-0">إجمالي مساحات العمل</p>
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
                        <h2 class="text-bold-700 mt-1" id="activeWorkspaces">
                            {{ number_format($activeWorkspaces, 0) }}
                        </h2>
                        <p class="mb-0">مساحات العمل النشطة</p>
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
                        <h2 class="text-bold-700 mt-1" id="totalProjects">
                            {{ number_format($totalProjects, 0) }}
                        </h2>
                        <p class="mb-0">إجمالي المشاريع</p>
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
                        <h2 class="text-bold-700 mt-1" id="totalTasks">
                            {{ number_format($totalTasks, 0) }}
                        </h2>
                        <p class="mb-0">إجمالي المهام</p>
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
                                <i class="feather icon-users font-medium-5" style="color: #20c997;"></i>
                            </div>
                        </div>
                        <h2 class="text-bold-700 mt-1" id="totalUsers">
                            {{ number_format($totalUsers, 0) }}
                        </h2>
                        <p class="mb-0">إجمالي المستخدمين</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="row mt-2">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">توزيع مساحات العمل حسب الحالة</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-6 col-12">
                                <h6 class="text-center mb-3">توزيع مساحات العمل حسب الحالة</h6>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="workspaceStatusChart"></canvas>
                                </div>
                            </div>
                            <div class="col-lg-6 col-12">
                                <h6 class="text-center mb-3">توزيع المشاريع حسب مساحات العمل</h6>
                                <div class="chart-container" style="position: relative; height: 300px;">
                                    <canvas id="projectsPerWorkspaceChart"></canvas>
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
                        <h4 class="card-title">الاتجاه الزمني لمساحات العمل</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="workspacesTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">توزيع المهام حسب مساحات العمل</h4>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px;">
                            <canvas id="tasksPerWorkspaceChart"></canvas>
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
                        <h4 class="card-title">أهم مساحات العمل حسب المشاريع</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>مساحة العمل</th>
                                        <th>المشاريع</th>
                                        <th>المهام</th>
                                        <th>النسبة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topWorkspaces as $workspace)
                                        <tr>
                                            <td>{{ $workspace->title }}</td>
                                            <td>{{ $workspace->total_projects }}</td>
                                            <td>{{ $workspace->total_tasks }}</td>
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

            <div class="col-lg-6 col-md-6 col-12">
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
                                        <th>المشاريع</th>
                                        <th>المساحات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topUsers as $user)
                                        <tr>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->total_tasks }}</td>
                                            <td>{{ $user->total_projects }}</td>
                                            <td>{{ $user->total_workspaces }}</td>
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
        let workspaceStatusChart, projectsPerWorkspaceChart, workspacesTrendChart, tasksPerWorkspaceChart;

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
            // Workspace Status Chart
            const workspaceStatusCtx = document.getElementById('workspaceStatusChart').getContext('2d');
            if (workspaceStatusChart) {
                workspaceStatusChart.destroy();
            }

            workspaceStatusChart = new Chart(workspaceStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: @json($workspaceStatusLabels),
                    datasets: [{
                        data: @json($workspaceStatusCounts),
                        backgroundColor: [
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

            // Projects Per Workspace Chart
            const projectsPerWorkspaceCtx = document.getElementById('projectsPerWorkspaceChart').getContext('2d');
            if (projectsPerWorkspaceChart) {
                projectsPerWorkspaceChart.destroy();
            }

            projectsPerWorkspaceChart = new Chart(projectsPerWorkspaceCtx, {
                type: 'bar',
                data: {
                    labels: @json($workspaceLabels),
                    datasets: [{
                        label: 'عدد المشاريع',
                        data: @json($workspaceProjectCounts),
                        backgroundColor: 'rgba(33, 150, 243, 0.8)',
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

            // Workspaces Trend Chart
            const workspacesTrendCtx = document.getElementById('workspacesTrendChart').getContext('2d');
            if (workspacesTrendChart) {
                workspacesTrendChart.destroy();
            }

            workspacesTrendChart = new Chart(workspacesTrendCtx, {
                type: 'line',
                data: {
                    labels: @json($months),
                    datasets: [{
                        label: 'مساحات العمل الجديدة',
                        data: @json($monthlyWorkspaces),
                        borderColor: 'rgba(156, 39, 176, 1)',
                        backgroundColor: 'rgba(156, 39, 176, 0.2)',
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

            // Tasks Per Workspace Chart
            const tasksPerWorkspaceCtx = document.getElementById('tasksPerWorkspaceChart').getContext('2d');
            if (tasksPerWorkspaceChart) {
                tasksPerWorkspaceChart.destroy();
            }

            tasksPerWorkspaceChart = new Chart(tasksPerWorkspaceCtx, {
                type: 'bar',
                data: {
                    labels: @json($workspaceLabels),
                    datasets: [{
                        label: 'عدد المهام',
                        data: @json($workspaceTaskCounts),
                        backgroundColor: 'rgba(255, 193, 7, 0.8)',
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
                ['تقرير ملخص مساحات العمل'],
                ['التاريخ:', `من ${$('#from_date').val()} إلى ${$('#to_date').val()}`],
                [''],
                ['البيان', 'المبلغ'],
                ['إجمالي مساحات العمل', $('#totalWorkspaces').text()],
                ['مساحات العمل النشطة', $('#activeWorkspaces').text()],
                ['إجمالي المشاريع', $('#totalProjects').text()],
                ['إجمالي المهام', $('#totalTasks').text()],
                ['معدل إنجاز المشاريع', $('#completionRate').text()],
                ['إجمالي المستخدمين', $('#totalUsers').text()]
            ];

            const summaryWs = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, summaryWs, 'ملخص التقرير');

            // Save file
            const today = new Date();
            const fileName = `تقرير_مساحات_العمل_${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}.xlsx`;

            XLSX.writeFile(wb, fileName);
            alert('تم تصدير الملف بنجاح!');
        }
    </script>
@endsection