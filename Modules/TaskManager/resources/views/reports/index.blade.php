@extends('taskmanager::master')

@section('title')
    تقارير المهام والمشاريع ومساحات العمل
@stop

@section('content')
    <div class="content-header row">
        <div class="content-header-left col-md-9 col-12 mb-2">
            <div class="row breadcrumbs-top">
                <div class="col-12">
                    <h2 class="content-header-title float-left mb-0">تقارير المهام والمشاريع ومساحات العمل</h2>
                    <div class="breadcrumb-wrapper col-12">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('tasks.manager') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item active">تقارير المهام</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="reports-wrapper">
        {{-- Hero header --}}
        <div class="reports-hero">
            <div class="reports-hero-content">
                <h1 class="reports-hero-title">
                    تقارير <span class="reports-hero-highlight">المهام والمشاريع</span>
                </h1>
                <p class="reports-hero-subtitle">
                    تقارير شاملة ومفصلة لجميع المهام والمشاريع ومساحات العمل، لحظة بلحظة
                </p>
            </div>

            <div class="reports-stat-row">
                <div class="reports-stat-pill">
                    <i class="fas fa-list-check"></i>
                    <div>
                        <span class="reports-stat-value">{{ $quickStats['tasks'] }}</span>
                        <span class="reports-stat-label">إجمالي المهام</span>
                    </div>
                </div>
                <div class="reports-stat-pill">
                    <i class="fas fa-circle-check"></i>
                    <div>
                        <span class="reports-stat-value">
                            {{ $quickStats['tasks'] > 0 ? round(($quickStats['tasks_completed'] / $quickStats['tasks']) * 100) : 0 }}%
                        </span>
                        <span class="reports-stat-label">نسبة الإنجاز</span>
                    </div>
                </div>
                <div class="reports-stat-pill">
                    <i class="fas fa-diagram-project"></i>
                    <div>
                        <span class="reports-stat-value">{{ $quickStats['projects'] }}</span>
                        <span class="reports-stat-label">المشاريع ({{ $quickStats['projects_active'] }} نشط)</span>
                    </div>
                </div>
                <div class="reports-stat-pill">
                    <i class="fas fa-layer-group"></i>
                    <div>
                        <span class="reports-stat-value">{{ $quickStats['workspaces'] }}</span>
                        <span class="reports-stat-label">مساحات العمل</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Interactive toolbar: search + category filter --}}
        <div class="reports-toolbar">
            <div class="reports-search">
                <i class="fas fa-search"></i>
                <input type="text" id="reportsSearchInput" placeholder="ابحث في التقارير..." autocomplete="off">
            </div>
            <div class="reports-filter-chips" id="reportsFilterChips">
                <button type="button" class="reports-chip active" data-filter="all">
                    <i class="fas fa-border-all"></i> الكل
                </button>
                <button type="button" class="reports-chip" data-filter="tasks">
                    <i class="fas fa-tasks"></i> المهام
                </button>
                <button type="button" class="reports-chip" data-filter="projects">
                    <i class="fas fa-project-diagram"></i> المشاريع
                </button>
                <button type="button" class="reports-chip" data-filter="workspaces">
                    <i class="fas fa-th-large"></i> مساحات العمل
                </button>
            </div>
        </div>

        <div id="reportsEmptyState" class="reports-empty-state" hidden>
            <i class="fas fa-folder-open"></i>
            <p>لا توجد تقارير مطابقة لبحثك</p>
        </div>

        {{-- Task Reports --}}
        <div class="reports-section" data-section="tasks">
            <h3 class="reports-section-title">
                <i class="fas fa-tasks"></i>
                تقارير المهام
            </h3>
            <div class="reports-grid">
                <a href="{{ route('tasks.reports.tasks') }}" class="report-card" data-category="tasks" data-title="تقرير ملخص المهام">
                    <span class="report-card-accent report-accent-tasks"></span>
                    <div class="report-card-icon report-icon-tasks"><i class="fas fa-chart-bar"></i></div>
                    <div class="report-card-body">
                        <h4 class="report-card-title">تقرير ملخص المهام</h4>
                        <p class="report-card-desc">تحليل شامل لجميع المهام حسب الحالة والأولوية</p>
                        <span class="report-card-stat">{{ $quickStats['tasks'] }} مهمة</span>
                    </div>
                    <span class="report-card-arrow"><i class="fas fa-arrow-left"></i></span>
                </a>

                <a href="{{ route('tasks.reports.task-types') }}" class="report-card" data-category="tasks" data-title="توزيع المهام حسب النوع">
                    <span class="report-card-accent report-accent-tasks"></span>
                    <div class="report-card-icon report-icon-tasks"><i class="fas fa-layer-group"></i></div>
                    <div class="report-card-body">
                        <h4 class="report-card-title">توزيع المهام حسب النوع</h4>
                        <p class="report-card-desc">تحليل توزيع المهام حسب الأنواع المختلفة</p>
                        <span class="report-card-stat">مكوّن، إبيك، قصة مستخدم...</span>
                    </div>
                    <span class="report-card-arrow"><i class="fas fa-arrow-left"></i></span>
                </a>

                <a href="{{ route('tasks.reports.status') }}" class="report-card" data-category="tasks" data-title="تقرير حالة المهام">
                    <span class="report-card-accent report-accent-tasks"></span>
                    <div class="report-card-icon report-icon-tasks"><i class="fas fa-clipboard-list"></i></div>
                    <div class="report-card-body">
                        <h4 class="report-card-title">تقرير حالة المهام</h4>
                        <p class="report-card-desc">تحليل تفصيلي لحالة المهام المختلفة</p>
                        <span class="report-card-stat">{{ $quickStats['tasks_completed'] }} مكتملة</span>
                    </div>
                    <span class="report-card-arrow"><i class="fas fa-arrow-left"></i></span>
                </a>

                <a href="{{ route('tasks.reports.priority') }}" class="report-card" data-category="tasks" data-title="تقرير أولوية المهام">
                    <span class="report-card-accent report-accent-tasks"></span>
                    <div class="report-card-icon report-icon-tasks"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="report-card-body">
                        <h4 class="report-card-title">تقرير أولوية المهام</h4>
                        <p class="report-card-desc">تحليل توزيع المهام حسب الأولويات</p>
                        <span class="report-card-stat">منخفضة، متوسطة، عالية، عاجلة</span>
                    </div>
                    <span class="report-card-arrow"><i class="fas fa-arrow-left"></i></span>
                </a>
            </div>
        </div>

        {{-- Project Reports --}}
        <div class="reports-section" data-section="projects">
            <h3 class="reports-section-title">
                <i class="fas fa-project-diagram"></i>
                تقارير المشاريع
            </h3>
            <div class="reports-grid">
                <a href="{{ route('tasks.reports.projects') }}" class="report-card" data-category="projects" data-title="تقرير ملخص المشاريع">
                    <span class="report-card-accent report-accent-projects"></span>
                    <div class="report-card-icon report-icon-projects"><i class="fas fa-chart-pie"></i></div>
                    <div class="report-card-body">
                        <h4 class="report-card-title">تقرير ملخص المشاريع</h4>
                        <p class="report-card-desc">تحليل شامل لجميع المشاريع حسب الحالة والإنجاز</p>
                        <span class="report-card-stat">{{ $quickStats['projects'] }} مشروع</span>
                    </div>
                    <span class="report-card-arrow"><i class="fas fa-arrow-left"></i></span>
                </a>

                <a href="{{ route('tasks.reports.project-completion') }}" class="report-card" data-category="projects" data-title="تقرير إنجاز المشاريع">
                    <span class="report-card-accent report-accent-projects"></span>
                    <div class="report-card-icon report-icon-projects"><i class="fas fa-percentage"></i></div>
                    <div class="report-card-body">
                        <h4 class="report-card-title">تقرير إنجاز المشاريع</h4>
                        <p class="report-card-desc">تحليل نسب إنجاز المشاريع المختلفة</p>
                        <span class="report-card-stat">{{ $quickStats['projects_active'] }} نشط الآن</span>
                    </div>
                    <span class="report-card-arrow"><i class="fas fa-arrow-left"></i></span>
                </a>

                <a href="{{ route('tasks.reports.project-timeline') }}" class="report-card" data-category="projects" data-title="تقرير جدول زمني المشاريع">
                    <span class="report-card-accent report-accent-projects"></span>
                    <div class="report-card-icon report-icon-projects"><i class="fas fa-calendar-alt"></i></div>
                    <div class="report-card-body">
                        <h4 class="report-card-title">تقرير جدول زمني المشاريع</h4>
                        <p class="report-card-desc">تحليل جداول زمنية المشاريع والمواعيد النهائية</p>
                        <span class="report-card-stat">مواعيد البدء والانتهاء</span>
                    </div>
                    <span class="report-card-arrow"><i class="fas fa-arrow-left"></i></span>
                </a>
            </div>
        </div>

        {{-- Workspace Reports --}}
        <div class="reports-section" data-section="workspaces">
            <h3 class="reports-section-title">
                <i class="fas fa-th-large"></i>
                تقارير مساحات العمل
            </h3>
            <div class="reports-grid">
                <a href="{{ route('tasks.reports.workspaces') }}" class="report-card" data-category="workspaces" data-title="تقرير ملخص مساحات العمل">
                    <span class="report-card-accent report-accent-workspaces"></span>
                    <div class="report-card-icon report-icon-workspaces"><i class="fas fa-chart-area"></i></div>
                    <div class="report-card-body">
                        <h4 class="report-card-title">تقرير ملخص مساحات العمل</h4>
                        <p class="report-card-desc">تحليل شامل لجميع مساحات العمل والمشاريع</p>
                        <span class="report-card-stat">{{ $quickStats['workspaces'] }} مساحة عمل</span>
                    </div>
                    <span class="report-card-arrow"><i class="fas fa-arrow-left"></i></span>
                </a>

                <a href="{{ route('tasks.reports.workspace-performance') }}" class="report-card" data-category="workspaces" data-title="تقرير أداء مساحات العمل">
                    <span class="report-card-accent report-accent-workspaces"></span>
                    <div class="report-card-icon report-icon-workspaces"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="report-card-body">
                        <h4 class="report-card-title">تقرير أداء مساحات العمل</h4>
                        <p class="report-card-desc">تحليل أداء مساحات العمل المختلفة</p>
                        <span class="report-card-stat">مقياس أداء مركّب</span>
                    </div>
                    <span class="report-card-arrow"><i class="fas fa-arrow-left"></i></span>
                </a>
            </div>
        </div>
    </div>
@endsection

@section('styles')
    <style>
        .reports-wrapper {
            padding: 1.5rem 0.5rem 2rem;
        }

        /* ---------- Hero ---------- */
        .reports-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 18px;
            padding: 2.5rem 2rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 12px 32px rgba(102, 126, 234, 0.28);
            color: white;
            text-align: center;
            animation: reportsFadeUp 0.5s ease both;
        }

        .reports-hero-content { max-width: 800px; margin: 0 auto; }

        .reports-hero-title {
            font-size: 2.25rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
        }

        .reports-hero-highlight {
            background: linear-gradient(120deg, #ffe082, #ffd54f);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .reports-hero-subtitle {
            font-size: 1.05rem;
            opacity: 0.92;
            margin-bottom: 0;
        }

        .reports-stat-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.9rem;
            margin-top: 1.75rem;
        }

        .reports-stat-pill {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            background: rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 14px;
            padding: 0.7rem 1.1rem;
            min-width: 165px;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .reports-stat-pill:hover {
            transform: translateY(-3px);
            background: rgba(255, 255, 255, 0.2);
        }

        .reports-stat-pill i {
            font-size: 1.3rem;
            opacity: 0.9;
        }

        .reports-stat-pill > div { display: flex; flex-direction: column; text-align: right; }
        .reports-stat-value { font-size: 1.25rem; font-weight: 800; line-height: 1.1; }
        .reports-stat-label { font-size: 0.75rem; opacity: 0.85; }

        /* ---------- Toolbar ---------- */
        .reports-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: #fff;
            border: 1px solid #edf0f5;
            border-radius: 14px;
            padding: 0.9rem 1.1rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
            position: sticky;
            top: 8px;
            z-index: 20;
        }

        .reports-search {
            position: relative;
            flex: 1 1 240px;
            max-width: 340px;
        }

        .reports-search i {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            color: #a5adba;
            font-size: 0.9rem;
        }

        .reports-search input {
            width: 100%;
            border: 1px solid #e1e6ee;
            border-radius: 10px;
            padding: 0.55rem 2.4rem 0.55rem 0.9rem;
            font-size: 0.9rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .reports-search input:focus {
            outline: none;
            border-color: #7367f0;
            box-shadow: 0 0 0 3px rgba(115, 103, 240, 0.14);
        }

        .reports-filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .reports-chip {
            border: 1px solid #e1e6ee;
            background: #f8f9fc;
            color: #5e6c84;
            border-radius: 999px;
            padding: 0.45rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .reports-chip:hover { background: #eef0fb; color: #667eea; }

        .reports-chip.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.35);
        }

        .reports-chip:focus-visible {
            outline: 2px solid #7367f0;
            outline-offset: 2px;
        }

        /* ---------- Sections & Grid ---------- */
        .reports-section { margin-bottom: 2.25rem; }
        .reports-section.is-hidden { display: none; }

        .reports-section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1.25rem;
            padding-bottom: 0.6rem;
            border-bottom: 3px solid #eef0fb;
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        .reports-section-title i { color: #667eea; }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        /* ---------- Cards ---------- */
        .report-card {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #fff;
            border-radius: 14px;
            border: 1px solid #eef0f5;
            padding: 1.25rem 1.25rem 1.25rem 2.4rem;
            box-shadow: 0 3px 14px rgba(23, 43, 77, 0.06);
            text-decoration: none;
            color: inherit;
            overflow: hidden;
            transition: transform 0.22s ease, box-shadow 0.22s ease, border-color 0.22s ease;
            opacity: 0;
            animation: reportsFadeUp 0.45s ease forwards;
        }

        .reports-grid .report-card:nth-child(1) { animation-delay: 0.03s; }
        .reports-grid .report-card:nth-child(2) { animation-delay: 0.08s; }
        .reports-grid .report-card:nth-child(3) { animation-delay: 0.13s; }
        .reports-grid .report-card:nth-child(4) { animation-delay: 0.18s; }

        .report-card:hover, .report-card:focus-visible {
            transform: translateY(-4px);
            box-shadow: 0 14px 30px rgba(23, 43, 77, 0.12);
            border-color: #d8dcf5;
            color: inherit;
            text-decoration: none;
        }

        .report-card:focus-visible {
            outline: 2px solid #7367f0;
            outline-offset: 2px;
        }

        .report-card-accent {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 6px;
        }

        .report-accent-tasks { background: linear-gradient(180deg, #4CAF50, #2E7D32); }
        .report-accent-projects { background: linear-gradient(180deg, #2196F3, #1565C0); }
        .report-accent-workspaces { background: linear-gradient(180deg, #7367f0, #5b4fd6); }

        .report-card-icon {
            flex: 0 0 52px;
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #fff;
        }

        .report-icon-tasks { background: linear-gradient(135deg, #4CAF50, #2E7D32); }
        .report-icon-projects { background: linear-gradient(135deg, #2196F3, #1565C0); }
        .report-icon-workspaces { background: linear-gradient(135deg, #7367f0, #5b4fd6); }

        .report-card-body { flex: 1; min-width: 0; }

        .report-card-title {
            font-size: 1.02rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }

        .report-card-desc {
            color: #7f8c8d;
            font-size: 0.83rem;
            line-height: 1.5;
            margin-bottom: 0.5rem;
        }

        .report-card-stat {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 700;
            color: #667eea;
            background: #eef0fb;
            border-radius: 999px;
            padding: 0.2rem 0.6rem;
        }

        .report-card-arrow {
            flex: 0 0 auto;
            color: #c2c8d6;
            transition: transform 0.22s ease, color 0.22s ease;
        }

        .report-card:hover .report-card-arrow {
            transform: translateX(-4px);
            color: #667eea;
        }

        /* ---------- Empty state ---------- */
        .reports-empty-state {
            text-align: center;
            padding: 3.5rem 1rem;
            color: #a5adba;
            background: #fff;
            border: 1px dashed #dfe1e6;
            border-radius: 14px;
            margin-bottom: 2rem;
        }

        .reports-empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; }

        @keyframes reportsFadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .reports-hero, .report-card { animation: none; opacity: 1; }
        }

        /* ---------- Responsive ---------- */
        @media (max-width: 768px) {
            .reports-wrapper { padding: 1rem 0.25rem 1.5rem; }
            .reports-hero { padding: 1.75rem 1.25rem; border-radius: 14px; }
            .reports-hero-title { font-size: 1.6rem; }
            .reports-hero-subtitle { font-size: 0.92rem; }
            .reports-stat-pill { min-width: 130px; flex: 1 1 130px; }
            .reports-toolbar { position: static; flex-direction: column; align-items: stretch; }
            .reports-search { flex: none; max-width: none; }
            .reports-filter-chips { justify-content: center; }
            .reports-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 420px) {
            .reports-stat-row { gap: 0.6rem; }
            .reports-stat-pill { min-width: 100%; }
        }
    </style>
@endsection

@section('scripts')
    <script>
        (function () {
            var searchInput = document.getElementById('reportsSearchInput');
            var chips = document.querySelectorAll('.reports-chip');
            var sections = document.querySelectorAll('.reports-section');
            var cards = document.querySelectorAll('.report-card');
            var emptyState = document.getElementById('reportsEmptyState');
            var activeFilter = 'all';

            function applyFilters() {
                var query = (searchInput.value || '').trim().toLowerCase();
                var visibleCount = 0;

                cards.forEach(function (card) {
                    var matchesCategory = activeFilter === 'all' || card.dataset.category === activeFilter;
                    var matchesSearch = !query || (card.dataset.title || '').toLowerCase().indexOf(query) !== -1;
                    var visible = matchesCategory && matchesSearch;
                    card.style.display = visible ? '' : 'none';
                    if (visible) visibleCount++;
                });

                sections.forEach(function (section) {
                    var hasVisibleCard = section.querySelectorAll('.report-card:not([style*="display: none"])').length > 0;
                    section.classList.toggle('is-hidden', !hasVisibleCard);
                });

                emptyState.hidden = visibleCount !== 0;
            }

            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (c) { c.classList.remove('active'); });
                    chip.classList.add('active');
                    activeFilter = chip.dataset.filter;
                    applyFilters();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }
        })();
    </script>
@endsection
