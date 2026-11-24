@extends('taskmanager::master')

@section('title')
    لوحة التحكم - إدارة المهام
@stop

@section('content')
    @include('taskmanager::partials.page_hero', [
        'heroTitle' => 'أهلاً',
        'heroHighlight' => auth()->user()->name,
        'heroSubtitle' => \Carbon\Carbon::now()->translatedFormat('l، d F Y') . ' — إليك نظرة سريعة على أعمالك اليوم',
        'heroStats' => [
            ['icon' => 'fas fa-list-check', 'value' => $totalTasks, 'label' => 'إجمالي المهام'],
            ['icon' => 'fas fa-circle-check', 'value' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) . '%' : '0%', 'label' => 'نسبة الإنجاز'],
            ['icon' => 'fas fa-triangle-exclamation', 'value' => $overdueTasks, 'label' => 'مهام متأخرة'],
            ['icon' => 'fas fa-diagram-project', 'value' => $totalProjects, 'label' => 'المشاريع'],
        ],
    ])

    <div class="content-body">
        <!-- Filter Section -->
        <div class="tm-toolbar">
            <div class="board-filter-row">
                <select class="form-control select2 board-quick-filter" id="filterProject" data-placeholder="المشروع">
                    <option value="">جميع المشاريع</option>
                    @foreach($allProjects as $project)
                        <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                            {{ $project->title }}
                        </option>
                    @endforeach
                </select>

                <select class="form-control select2 board-quick-filter" id="filterSprint" data-placeholder="السبرنت">
                    <option value="">جميع السبرنتات</option>
                    <option value="current" {{ request('sprint_id') == 'current' ? 'selected' : '' }}>السبرنت الحالي</option>
                    @foreach($allSprints as $sprint)
                        <option value="{{ $sprint->id }}" {{ request('sprint_id') == $sprint->id ? 'selected' : '' }}>
                            {{ $sprint->name }}
                            @if($sprint->status === 'closed')
                                (مغلق)
                            @endif
                        </option>
                    @endforeach
                </select>

                <select class="form-control select2 board-quick-filter" id="filterWorkspace" data-placeholder="مساحة العمل">
                    <option value="">جميع مساحات العمل</option>
                    @foreach($allWorkspaces as $workspace)
                        <option value="{{ $workspace->id }}" {{ request('workspace_id') == $workspace->id ? 'selected' : '' }}>
                            {{ $workspace->title }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="board-toolbar-actions">
                <button class="btn btn-primary btn-sm" id="applyFilters"><i class="feather icon-filter"></i> تطبيق الفلاتر</button>
                <button class="btn btn-flat-secondary btn-sm" id="resetFilters"><i class="feather icon-refresh-cw"></i> إعادة تعيين</button>
            </div>
        </div>

        <section id="task-dashboard">
            <!-- Main Task Summary Cards -->
            <div class="row">
                <div class="col-lg-3 col-md-6 col-12 tm-fade-up">
                    <div class="tm-stat-tile">
                        <div class="tm-stat-tile-icon bg-success-grad"><i class="feather icon-check-circle"></i></div>
                        <div>
                            <div class="tm-stat-tile-value">{{ $totalTasks }}</div>
                            <div class="tm-stat-tile-label">إجمالي المهام</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12 tm-fade-up">
                    <div class="tm-stat-tile">
                        <div class="tm-stat-tile-icon bg-primary-grad"><i class="feather icon-loader"></i></div>
                        <div>
                            <div class="tm-stat-tile-value">{{ $inProgressTasks }}</div>
                            <div class="tm-stat-tile-label">قيد التنفيذ</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12 tm-fade-up">
                    <div class="tm-stat-tile">
                        <div class="tm-stat-tile-icon bg-warning-grad"><i class="feather icon-check"></i></div>
                        <div>
                            <div class="tm-stat-tile-value">{{ $completedTasks }}</div>
                            <div class="tm-stat-tile-label">المهام المكتملة</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 col-12 tm-fade-up">
                    <div class="tm-stat-tile">
                        <div class="tm-stat-tile-icon bg-danger-grad"><i class="feather icon-clock"></i></div>
                        <div>
                            <div class="tm-stat-tile-value">{{ $overdueTasks }}</div>
                            <div class="tm-stat-tile-label">المهام المتأخرة</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row mt-2">
                <div class="col-lg-8 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-trending-up"></i> الاتجاه الزمني للمهام</div>
                        <div class="tm-panel-body">
                            <div id="tasks-trend-chart" class="chart-container"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-pie-chart"></i> توزيع المهام حسب الحالة</div>
                        <div class="tm-panel-body">
                            <div id="tasks-status-chart" class="chart-container"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Workspace Section -->
            <h4 class="tm-section-title mt-3"><i class="fas fa-th-large"></i> مساحات العمل</h4>
            <div class="row">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-layers"></i> ملخص مساحات العمل</div>
                        <div class="tm-panel-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>إجمالي مساحات العمل</span>
                                <span class="font-weight-bold">{{ $totalWorkspaces }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>مساحات العمل النشطة</span>
                                <span class="font-weight-bold">{{ $activeWorkspaces }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>معدل إنجاز مساحات العمل</span>
                                <span class="font-weight-bold">{{ number_format($workspaceCompletionRate, 2) }}%</span>
                            </div>

                            <div class="progress progress-bar-primary mb-2 mt-1">
                                <div class="progress-bar" role="progressbar" style="width:{{ $workspaceCompletionRate }}%"
                                     aria-valuenow="{{ $workspaceCompletionRate }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-list"></i> أحدث المهام</div>
                        <div class="tm-panel-body">
                            <div class="table-responsive">
                                <table class="table table-hover tm-table">
                                    <thead>
                                        <tr>
                                            <th>المهمة</th>
                                            <th>النوع</th>
                                            <th>المشروع</th>
                                            <th>السبرنت</th>
                                            <th>الحالة</th>
                                            <th>التاريخ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentTasks as $task)
                                            <tr>
                                                <td title="{{ $task->title }}">{{ Str::limit($task->title, 30) }}</td>
                                                <td>
                                                    @if($task->type == 'user_story')
                                                        <span class="badge badge-primary">قصة مستخدم</span>
                                                    @elseif($task->type == 'epic')
                                                        <span class="badge badge-info">إبيك</span>
                                                    @elseif($task->type == 'component')
                                                        <span class="badge badge-success">مكون</span>
                                                    @elseif($task->type == 'bug')
                                                        <span class="badge badge-danger">خلل</span>
                                                    @else
                                                        <span class="badge badge-secondary">{{ $task->type }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ Str::limit($task->project ? $task->project->title : 'N/A', 20) }}</td>
                                                <td>{{ Str::limit($task->sprint ? $task->sprint->name : 'بدون سبرنت', 15) }}</td>
                                                <td>
                                                    @if($task->status == 'not_started')
                                                        <span class="badge badge-secondary">لم تبدأ</span>
                                                    @elseif($task->status == 'in_progress')
                                                        <span class="badge badge-warning">قيد التنفيذ</span>
                                                    @elseif($task->status == 'completed')
                                                        <span class="badge badge-success">مكتملة</span>
                                                    @elseif($task->status == 'overdue')
                                                        <span class="badge badge-danger">متأخرة</span>
                                                    @endif
                                                </td>
                                                <td>{{ $task->created_at ? (is_string($task->created_at) ? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d') : $task->created_at->format('Y-m-d')) : 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Projects Section -->
            <h4 class="tm-section-title mt-3"><i class="fas fa-diagram-project"></i> الإحصائيات حسب المشروع</h4>
            <div class="row">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-briefcase"></i> ملخص المشاريع</div>
                        <div class="tm-panel-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>إجمالي المشاريع</span>
                                <span class="font-weight-bold">{{ $totalProjects }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>المشاريع النشطة</span>
                                <span class="font-weight-bold">{{ $activeProjects }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>معدل إنجاز المشاريع</span>
                                <span class="font-weight-bold">{{ number_format($projectCompletionRate, 2) }}%</span>
                            </div>

                            <div class="progress progress-bar-primary mb-2 mt-1">
                                <div class="progress-bar" role="progressbar" style="width:{{ $projectCompletionRate }}%"
                                     aria-valuenow="{{ $projectCompletionRate }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-award"></i> أهم المشاريع حسب الإنجاز</div>
                        <div class="tm-panel-body">
                            <div class="table-responsive">
                                <table class="table table-hover tm-table">
                                    <thead>
                                        <tr>
                                            <th>المشروع</th>
                                            <th>إجمالي المهام</th>
                                            <th>المهام المكتملة</th>
                                            <th>نسبة الإنجاز</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topProjects as $project)
                                            <tr>
                                                <td>{{ $project->title?? 'غير محدد' }}</td>
                                                <td>{{ $project->total_tasks?? 'غير محدد' }}</td>
                                                <td>{{ $project->completed_tasks?? 'غير محدد' }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="mr-1">{{ number_format($project->completion_rate, 2) }}%</span>
                                                        <div class="progress progress-bar-success mb-0" style="height: 8px; width: 100px;">
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
            </div>

            <!-- Sprints Section -->
            <h4 class="tm-section-title mt-3"><i class="fas fa-repeat"></i> الإحصائيات حسب السبرنت</h4>
            <div class="row">
                <div class="col-lg-4 col-md-6 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-repeat"></i> ملخص السبرنتات</div>
                        <div class="tm-panel-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>إجمالي السبرنتات</span>
                                <span class="font-weight-bold">{{ $totalSprints }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>السبرنتات النشطة</span>
                                <span class="font-weight-bold">{{ $activeSprints }}</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>السبرنتات المكتملة</span>
                                <span class="font-weight-bold">{{ $completedSprints }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>معدل إنجاز السبرنتات</span>
                                <span class="font-weight-bold">{{ number_format($sprintCompletionRate, 2) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-activity"></i> السبرنتات الحالية</div>
                        <div class="tm-panel-body">
                            <div class="table-responsive">
                                <table class="table table-hover tm-table">
                                    <thead>
                                        <tr>
                                            <th>السبرنت</th>
                                            <th>المهام</th>
                                            <th>المكتملة</th>
                                            <th>النسبة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($currentSprints as $sprint)
                                            <tr>
                                                <td>{{ $sprint->name }}</td>
                                                <td>{{ $sprint->total_tasks }}</td>
                                                <td>{{ $sprint->completed_tasks }}</td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="mr-1">{{ number_format($sprint->completion_rate, 2) }}%</span>
                                                        <div class="progress progress-bar-success mb-0" style="height: 8px; width: 80px;">
                                                            <div class="progress-bar" role="progressbar" style="width: {{ $sprint->completion_rate }}%" aria-valuenow="{{ $sprint->completion_rate }}" aria-valuemin="0" aria-valuemax="100"></div>
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

            <!-- Task Types Section -->
            <h4 class="tm-section-title mt-3"><i class="fas fa-layer-group"></i> أنواع المهام</h4>
            <div class="row">
                <div class="col-lg-6 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-pie-chart"></i> توزيع المهام حسب النوع</div>
                        <div class="tm-panel-body">
                            <div id="tasks-type-chart" class="chart-container"></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 col-12">
                    <div class="tm-panel">
                        <div class="tm-panel-header"><i class="feather icon-users"></i> أهم المستخدمين حسب المهام</div>
                        <div class="tm-panel-body">
                            <div class="table-responsive">
                                <table class="table table-hover tm-table">
                                    <thead>
                                        <tr>
                                            <th>المستخدم</th>
                                            <th>إجمالي المهام</th>
                                            <th>المهام المكتملة</th>
                                            <th>نسبة الإنجاز</th>
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
            </div>
        </section>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Select2
            $('.select2').select2();

            // Apply filters
            $('#applyFilters').on('click', function() {
                var projectId = $('#filterProject').val();
                var sprintId = $('#filterSprint').val();
                var workspaceId = $('#filterWorkspace').val();

                var url = new URL(window.location);
                url.searchParams.delete('project_id');
                url.searchParams.delete('sprint_id');
                url.searchParams.delete('workspace_id');

                if (projectId) {
                    url.searchParams.set('project_id', projectId);
                }

                if (sprintId) {
                    url.searchParams.set('sprint_id', sprintId);
                }

                if (workspaceId) {
                    url.searchParams.set('workspace_id', workspaceId);
                }

                window.location.href = url.toString();
            });

            // Reset filters
            $('#resetFilters').on('click', function() {
                var url = new URL(window.location);
                url.searchParams.delete('project_id');
                url.searchParams.delete('sprint_id');
                url.searchParams.delete('workspace_id');
                window.location.href = url.toString();
            });

            // Tasks Status Chart
            try {
                var tasksStatusChartElement = document.querySelector("#tasks-status-chart");
                if (tasksStatusChartElement) {
                    var tasksStatusOptions = {
                        chart: {
                            type: 'donut',
                            height: 300
                        },
                        series: [{{ $notStartedTasks }}, {{ $inProgressTasks }}, {{ $completedTasks }}, {{ $overdueTasks }}],
                        labels: ['لم تبدأ', 'قيد التنفيذ', 'مكتملة', 'متأخرة'],
                        colors: ['#6c757d', '#ffc107', '#28a745', '#dc3545'],
                        legend: {
                            position: 'bottom'
                        },
                        responsive: [{
                            breakpoint: 480,
                            options: {
                                chart: {
                                    width: 200
                                },
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }]
                    };

                    if (tasksStatusOptions.series.length > 0) {
                        var tasksStatusChart = new ApexCharts(tasksStatusChartElement, tasksStatusOptions);
                        tasksStatusChart.render();
                    }
                }
            } catch (e) {
                console.error('Error rendering tasks status chart:', e);
            }

            // Tasks Trend Chart
            try {
                var tasksTrendChartElement = document.querySelector("#tasks-trend-chart");
                if (tasksTrendChartElement) {
                    var tasksTrendOptions = {
                        chart: {
                            type: 'area',
                            height: 300
                        },
                        series: [{
                            name: 'المهام الجديدة',
                            data: [@foreach($monthlyTasks as $tasks) {{ $tasks }}, @endforeach]
                        }, {
                            name: 'المهام المكتملة',
                            data: [@foreach($monthlyCompletedTasks as $tasks) {{ $tasks }}, @endforeach]
                        }],
                        xaxis: {
                            categories: @json($months)
                        },
                        colors: ['#007bff', '#28a745'],
                        dataLabels: {
                            enabled: false
                        },
                        stroke: {
                            curve: 'smooth'
                        },
                        fill: {
                            type: 'gradient',
                            gradient: {
                                shadeIntensity: 1,
                                opacityFrom: 0.7,
                                opacityTo: 0.9,
                                stops: [0, 90, 100]
                            }
                        }
                    };

                    if (tasksTrendOptions.series[0].data.length > 0 || tasksTrendOptions.series[1].data.length > 0) {
                        var tasksTrendChart = new ApexCharts(tasksTrendChartElement, tasksTrendOptions);
                        tasksTrendChart.render();
                    }
                }
            } catch (e) {
                console.error('Error rendering tasks trend chart:', e);
            }

            // Tasks Type Chart
            try {
                var tasksTypeChartElement = document.querySelector("#tasks-type-chart");
                if (tasksTypeChartElement) {
                    var tasksTypeOptions = {
                        chart: {
                            type: 'pie',
                            height: 300
                        },
                        series: [@foreach($taskTypes as $type) {{ $type->count }}, @endforeach],
                        labels: @json($taskTypes->pluck('type')),
                        colors: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1'],
                        legend: {
                            position: 'bottom'
                        },
                        responsive: [{
                            breakpoint: 480,
                            options: {
                                chart: {
                                    width: 200
                                },
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }]
                    };

                    if (tasksTypeOptions.series.length > 0 && tasksTypeOptions.labels.length > 0) {
                        var tasksTypeChart = new ApexCharts(tasksTypeChartElement, tasksTypeOptions);
                        tasksTypeChart.render();
                    }
                }
            } catch (e) {
                console.error('Error rendering tasks type chart:', e);
            }
        });
    </script>
@endsection