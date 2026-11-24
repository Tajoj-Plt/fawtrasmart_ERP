/**
 * Product Backlog JavaScript
 */

// Check if jQuery and Bootstrap are loaded


/**
 * Product Backlog JavaScript - Fixed Version
 * This version ensures buttons appear correctly and includes improved styling
 */

// Ensure jQuery is loaded
if (typeof jQuery === 'undefined') {
    console.error('jQuery is required but not loaded!');
}

// Global initialization flag to prevent multiple initializations
window.backlogInitialized = window.backlogInitialized || false;

// Safe initialization wrapper
(function($) {
    'use strict';

    // Wait for DOM to be fully ready
    $(document).ready(function() {
        console.log('Product Backlog: Document Ready');

        // Give Bootstrap time to initialize
        setTimeout(function() {
            initializeProductBacklog();
        }, 200);
    });

    function initializeProductBacklog() {
        if (window.backlogInitialized) {
            console.log('Product Backlog: Already initialized, skipping...');
            return;
        }

        console.log('Product Backlog: Initializing...');

        // Check for required elements
        const requiredElements = [
            '#btnAddComponent',
            '#btnAddEpic',
            '#btnAddStory',
            '#btnAddSubTask',
            '#btnAddBug',
            '#componentModal',
            '#epicModal',
            '#storyModal',
            '#subTaskModal',
            '#bugModal'
        ];

        let allElementsFound = true;
        requiredElements.forEach(function(selector) {
            if ($(selector).length === 0) {
                console.warn('Product Backlog: Element not found:', selector);
                allElementsFound = false;
            } else {
                console.log('Product Backlog: Element found:', selector);
            }
        });

        if (!allElementsFound) {
            console.error('Product Backlog: Some required elements are missing. Retrying in 500ms...');
            setTimeout(initializeProductBacklog, 500);
            return;
        }

        // Mark as initialized
        window.backlogInitialized = true;

        // Initialize components
        initializeSelect2();
        initializeEventListeners();
        loadInitialContent();

        console.log('Product Backlog: Initialization complete');
    }

    function loadInitialContent() {
        // Load the active tab content
        const activeTab = $('.tab-pane.active').attr('id');
        console.log('Loading initial tab:', activeTab);

        if (activeTab) {
            loadTabContent(activeTab);
        } else {
            // Default to backlog overview
            loadTabContent('backlog-overview');
        }
    }

    function initializeSelect2() {
        // Destroy existing select2 instances first
        $('.select2').select2('destroy');

        // Reinitialize with proper settings
        $('.select2').select2({
            dir: 'rtl',
            language: {
                noResults: () => "لا توجد نتائج",
                searching: () => "جاري البحث..."
            },
            width: '100%'
        });

        // Special handling for modal select2
        $('.modal').on('shown.bs.modal', function () {
            $(this).find('.select2').select2('destroy').select2({
                dropdownParent: $(this),
                dir: 'rtl',
                width: '100%'
            });
        });
    }

    function initializeEventListeners() {
        console.log('Initializing event listeners');

        // Remove all existing event listeners first to prevent duplicates
        $('#btnAddComponent').off('click');
        $('#btnAddEpic').off('click');
        $('#btnAddStory').off('click');
        $('#btnAddSubTask').off('click');
        $('#btnAddBug').off('click');

        // Add Component button
        $('#btnAddComponent').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Add Component button clicked');
            openComponentModal();
            return false;
        });

        // Add Epic button
        $('#btnAddEpic').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Add Epic button clicked');
            loadComponentsForEpic();
            openEpicModal();
            return false;
        });

        // Add Story button
        $('#btnAddStory').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Add Story button clicked');
            loadEpicsForStory();
            openStoryModal();
            return false;
        });

        // Add SubTask button
        $('#btnAddSubTask').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Add SubTask button clicked');
            loadStoriesForSubTask();
            openSubTaskModal();
            return false;
        });

        // Add Bug button
        $('#btnAddBug').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Add Bug button clicked');
            loadEpicsForBug();
            openBugModal();
            return false;
        });

        // Form submissions
        $('#componentForm').off('submit').on('submit', (e) => {
            e.preventDefault();
            saveComponent();
        });

        $('#epicForm').off('submit').on('submit', (e) => {
            e.preventDefault();
            saveEpic();
        });

        $('#storyForm').off('submit').on('submit', (e) => {
            e.preventDefault();
            saveStory();
        });

        $('#subTaskForm').off('submit').on('submit', (e) => {
            e.preventDefault();
            saveSubTask();
        });

        $('#bugForm').off('submit').on('submit', (e) => {
            e.preventDefault();
            saveBug();
        });

        // Tab changes
        $('a[data-toggle="tab"]').off('shown.bs.tab').on('shown.bs.tab', function (e) {
            const target = $(e.target).attr('href').substring(1);
            console.log('Tab changed to:', target);
            loadTabContent(target);
        });

        // Modal cleanup
        $('.modal').off('hidden.bs.modal').on('hidden.bs.modal', function () {
            $(this).find('form')[0].reset();
            $('.select2').val(null).trigger('change');
            $('.alert').hide();
        });

        console.log('Event listeners initialized successfully');
    }

    // Make functions available globally
    window.openComponentModal = openComponentModal;
    window.openEpicModal = openEpicModal;
    window.openStoryModal = openStoryModal;
    window.openSubTaskModal = openSubTaskModal;
    window.openBugModal = openBugModal;

    // Continue with the rest of your existing functions...
    // (Keep all your existing functions from the original file)

})(jQuery);

// Add debugging function
window.checkBacklogStatus = function() {
    console.log('=== Product Backlog Status ===');
    console.log('Initialized:', window.backlogInitialized);
    console.log('jQuery version:', $.fn.jquery);
    console.log('Add Component button exists:', $('#btnAddComponent').length > 0);
    console.log('Add Component button visible:', $('#btnAddComponent').is(':visible'));
    console.log('Component modal exists:', $('#componentModal').length > 0);
    console.log('==============================');
};
if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded');
} else {
    console.log('jQuery version:', $.fn.jquery);
}

// Check if Bootstrap is loaded
if (typeof bootstrap === 'undefined' && typeof $.fn.modal === 'undefined') {
    console.error('Bootstrap is not loaded');
} else {
    console.log('Bootstrap is loaded');
}

// Test function to verify modal opening

$(document).ready(function() {
    console.log('Document ready');
    console.log('Product backlog initialized');

    // Check if required elements exist
    if ($('#btnAddComponent').length === 0) {
        console.error('Add Component button not found');
    } else {
        console.log('Add Component button found');
    }

    // Use a slight delay to ensure DOM is fully ready
    setTimeout(function() {
        initializeSelect2();
        initializeEventListeners();

        // Load initial tab content
        const activeTab = $('.tab-pane.active').attr('id');
        console.log('Loading initial tab:', activeTab);
        loadTabContent(activeTab);
    }, 100);

    // Test if functions are available

    // Add a test button to the page for debugging

});

// Also initialize when the page is fully loaded
$(window).on('load', function() {
    console.log('Window loaded');
    // Add a slight delay to ensure all elements are loaded
    setTimeout(function() {
        initializeEventListeners();
    }, 150);
});

function loadTabContent(tab) {
    console.log('Loading tab content for:', tab);
    switch(tab) {
        case 'backlog-overview':
            loadBacklogOverview();
            break;
        case 'components':
            loadComponents();
            break;
        case 'epics':
            loadEpics();
            break;
        case 'stories':
            loadStories();
            break;
        case 'subtasks':
            loadSubTasks();
            break;
        case 'bugs':
            loadBugs();
            break;
        default:
            loadComponents();
    }
}

// Function to load backlog overview content with performance improvements



// Helper function to render a sprint section
function renderSprintSection(sprint, statusType) {
    // Group sprint tasks by type
    const tasksByType = {};
    sprint.tasks.forEach(function(task) {
        if (!tasksByType[task.type]) {
            tasksByType[task.type] = [];
        }
        tasksByType[task.type].push(task);
    });

    // Format dates properly
    let dateRange = '';
    if (sprint.start_date && sprint.end_date) {
        const startDate = new Date(sprint.start_date).toLocaleDateString('ar-SA');
        const endDate = new Date(sprint.end_date).toLocaleDateString('ar-SA');
        dateRange = startDate + ' - ' + endDate;
    } else if (sprint.start_date) {
        dateRange = 'يبدأ من: ' + new Date(sprint.start_date).toLocaleDateString('ar-SA');
    } else if (sprint.end_date) {
        dateRange = 'ينتهي في: ' + new Date(sprint.end_date).toLocaleDateString('ar-SA');
    }

    // Status translation
    let statusText = '';
    let statusClass = '';
    switch(sprint.status) {
        case 'active':
            statusText = 'نشط';
            statusClass = 'success';
            break;
        case 'completed':
            statusText = 'مكتمل';
            statusClass = 'secondary';
            break;
        case 'planned':
            statusText = 'مخطط';
            statusClass = 'warning';
            break;
        default:
            statusText = sprint.status;
            statusClass = 'info';
    }

    let html = '<div class="card mb-4 sprint-card">';
    html += '<div class="card-header bg-' + (statusType === 'active' ? 'success' : statusType === 'completed' ? 'secondary' : 'warning') + ' text-white">';
    html += '<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">';
    html += '<h5 class="mb-2 mb-md-0">';
    html += '<i class="feather icon-calendar"></i> ' + sprint.name;
    html += '</h5>';
    html += '<div class="d-flex flex-wrap">';
    if (dateRange) {
        html += '<span class="badge badge-light mr-2 mb-2 mb-md-0">' + dateRange + '</span>';
    }
    html += '<span class="badge badge-' + statusClass + '">' + statusText + '</span>';
    // Add close/reopen button based on sprint status
    if (sprint.status === 'closed') {
        html += '<button class="btn btn-sm btn-success ml-2" onclick="reopenSprint(' + sprint.id + ')" title="إعادة فتح السبرنت"><i class="feather icon-rotate-ccw"></i> إعادة الفتح</button>';
    } else {
        html += '<button class="btn btn-sm btn-danger ml-2" onclick="closeSprint(' + sprint.id + ')" title="إغلاق السبرنت"><i class="feather icon-x"></i> إغلاق</button>';
    }
    html += '</div>';
    html += '</div>';
    if (sprint.goal) {
        html += '<p class="mb-0 mt-2 text-white-50">' + sprint.goal + '</p>';
    }
    html += '</div>';
    html += '<div class="card-body">';

    // Display each task type in the sprint
    for (const type in tasksByType) {
        const tasks = tasksByType[type];
        let typeName = '';
        let typeIcon = '';

        switch(type) {
            case 'component':
                typeName = 'Components';
                typeIcon = 'package';
                break;
            case 'epic':
                typeName = 'Epics';
                typeIcon = 'layers';
                break;
            case 'user_story':
                typeName = 'User Stories';
                typeIcon = 'file-text';
                break;
            case 'sub_task':
                typeName = 'Sub Tasks';
                typeIcon = 'list';
                break;
            case 'bug':
                typeName = 'Bugs';
                typeIcon = 'alert-triangle';
                break;
            default:
                typeName = type;
                typeIcon = 'file';
        }

        // Special handling for User Stories to group by schedule status
        if (type === 'user_story') {
            // Separate scheduled and unscheduled user stories
            const scheduledStories = tasks.filter(task => task.is_scheduled === true);
            const unscheduledStories = tasks.filter(task => task.is_scheduled === false);

            // Render scheduled user stories
            if (scheduledStories.length > 0) {
                html += '<div class="mb-4">';
                html += '<h6 class="mb-3"><i class="feather icon-' + typeIcon + '"></i> ' + typeName + ' المجدولة ';
                html += '<button class="btn btn-sm btn-outline-primary ml-2" type="button" data-toggle="collapse" data-target="#scheduled-' + sprint.id + '" aria-expanded="true" aria-controls="scheduled-' + sprint.id + '">';
                html += '<i class="feather icon-chevron-up"></i>';
                html += '</button>';
                html += '<span class="badge badge-success mr-2">' + scheduledStories.length + '</span>';
                html += '</h6>';
                html += '<div class="collapse show" id="scheduled-' + sprint.id + '">';
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped mb-0">';
                html += '<thead class="thead-light">';
                html += '<tr>';
                html += '<th width="35%">المهمة</th>';
                html += '<th width="15%">الأولوية</th>';
                html += '<th width="15%">الحالة</th>';
                html += '<th width="15%">الجدولة</th>';
                html += '<th width="15%">المستخدمون</th>';
                html += '<th width="10%">الإجراءات</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';

                scheduledStories.forEach(function(task) {
                    html += '<tr class="task-row user-story-scheduled">';
                    html += '<td class="font-weight-bold">' + task.title + '</td>';
                    html += '<td>' + getPriorityBadge(task.priority) + '</td>';
                    html += '<td>' + getStatusBadge(task.status) + '</td>';
                    html += '<td>';
                    html += '<span class="schedule-badge">';
                    html += '<i class="feather icon-check-circle text-success schedule-icon"></i> مجدولة';
                    html += '</span>';
                    html += '</td>';
                    html += '<td>' + getUsersBadges(task.assigned_users) + '</td>';
                    html += '<td>';
                    html += '<button class="btn btn-sm btn-outline-primary" onclick="window.assignSprintToTask(' + task.id + ', \'' + task.type + '\')" title="تغيير Sprint">';
                    html += '<i class="feather icon-calendar"></i>';
                    html += '</button>';
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody>';
                html += '</table>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }

            // Render unscheduled user stories
            if (unscheduledStories.length > 0) {
                html += '<div class="mb-4">';
                html += '<h6 class="mb-3"><i class="feather icon-' + typeIcon + '"></i> ' + typeName + ' غير المجدولة ';
                html += '<button class="btn btn-sm btn-outline-primary ml-2" type="button" data-toggle="collapse" data-target="#unscheduled-' + sprint.id + '" aria-expanded="true" aria-controls="unscheduled-' + sprint.id + '">';
                html += '<i class="feather icon-chevron-up"></i>';
                html += '</button>';
                html += '<span class="badge badge-warning">' + unscheduledStories.length + '</span>';
                html += '</h6>';
                html += '<div class="collapse show" id="unscheduled-' + sprint.id + '">';
                html += '<div class="table-responsive">';
                html += '<table class="table table-striped mb-0">';
                html += '<thead class="thead-light">';
                html += '<tr>';
                html += '<th width="35%">المهمة</th>';
                html += '<th width="15%">الأولوية</th>';
                html += '<th width="15%">الحالة</th>';
                html += '<th width="15%">الجدولة</th>';
                html += '<th width="15%">المستخدمون</th>';
                html += '<th width="10%">الإجراءات</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';

                unscheduledStories.forEach(function(task) {
                    html += '<tr class="task-row user-story-unscheduled">';
                    html += '<td class="font-weight-bold">' + task.title + '</td>';
                    html += '<td>' + getPriorityBadge(task.priority) + '</td>';
                    html += '<td>' + getStatusBadge(task.status) + '</td>';
                    html += '<td>';
                    html += '<span class="schedule-badge">';
                    html += '<i class="feather icon-alert-circle text-warning schedule-icon"></i> غير مجدولة';
                    html += '</span>';
                    html += '</td>';
                    html += '<td>' + getUsersBadges(task.assigned_users) + '</td>';
                    html += '<td>';
                    html += '<button class="btn btn-sm btn-outline-primary" onclick="window.assignSprintToTask(' + task.id + ', \'' + task.type + '\')" title="تغيير Sprint">';
                    html += '<i class="feather icon-calendar"></i>';
                    html += '</button>';
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody>';
                html += '</table>';
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }
        } else {
            // Render other task types normally
            html += '<div class="mb-4">';
            html += '<h6 class="mb-3"><i class="feather icon-' + typeIcon + '"></i> ' + typeName + '</h6>';
            html += '<div class="table-responsive">';
            html += '<table class="table table-striped mb-0">';
            html += '<thead class="thead-light">';
            html += '<tr>';
            html += '<th width="35%">المهمة</th>';
            html += '<th width="15%">الأولوية</th>';
            html += '<th width="15%">الحالة</th>';
            html += '<th width="20%">المستخدمون</th>';
            html += '<th width="15%">الإجراءات</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            tasks.forEach(function(task) {
                html += '<tr class="task-row">';
                html += '<td class="font-weight-bold">' + task.title + '</td>';
                html += '<td>' + getPriorityBadge(task.priority) + '</td>';
                html += '<td>' + getStatusBadge(task.status) + '</td>';
                html += '<td>' + getUsersBadges(task.assigned_users) + '</td>';
                html += '<td>';
                html += '<button class="btn btn-sm btn-outline-primary" onclick="window.assignSprintToTask(' + task.id + ', \'' + task.type + '\')" title="تغيير Sprint">';
                html += '<i class="feather icon-calendar"></i>';
                html += '</button>';
                html += '</td>';
                html += '</tr>';
            });

            html += '</tbody>';
            html += '</table>';
            html += '</div>';
            html += '</div>';
        }
    }

    html += '</div>';
    html += '</div>';

    return html;
}

function initializeSelect2() {
    $('.select2').select2({
        dir: 'rtl',
        language: {
            noResults: () => "لا توجد نتائج",
            searching: () => "جاري البحث..."
        }
    });

    $('.modal').on('shown.bs.modal', function () {
        $(this).find('.select2').select2({
            dropdownParent: $(this),
            dir: 'rtl'
        });
    });
}

function initializeEventListeners() {
    console.log('Initializing event listeners');

    // Check if buttons exist, if not wait a bit and try again
    if ($('#btnAddComponent').length === 0) {
        console.warn('Add Component button not found, retrying in 100ms...');
        setTimeout(initializeEventListeners, 100);
        return;
    }

    // Remove any existing event listeners to prevent duplicates
    $('#btnAddComponent').off('click');
    $('#btnAddEpic').off('click');
    $('#btnAddStory').off('click');
    $('#btnAddSubTask').off('click');
    $('#btnAddBug').off('click');

    // Add buttons
    $('#btnAddComponent').on('click', function(e) {
        e.preventDefault();
        console.log('Add Component button clicked');
        openComponentModal();
    });

    $('#btnAddEpic').on('click', function(e) {
        e.preventDefault();
        console.log('Add Epic button clicked');
        loadComponentsForEpic();
        openEpicModal();
    });

    $('#btnAddStory').on('click', function(e) {
        e.preventDefault();
        console.log('Add Story button clicked');
        loadEpicsForStory();
        openStoryModal();
    });

    $('#btnAddSubTask').on('click', function(e) {
        e.preventDefault();
        console.log('Add SubTask button clicked');
        loadStoriesForSubTask();
        openSubTaskModal();
    });

    $('#btnAddBug').on('click', function(e) {
        e.preventDefault();
        console.log('Add Bug button clicked');
        loadEpicsForBug();
        openBugModal();
    });

    // Form submissions
    $('#componentForm').off('submit').on('submit', (e) => { e.preventDefault(); saveComponent(); });
    $('#epicForm').off('submit').on('submit', (e) => { e.preventDefault(); saveEpic(); });
    $('#storyForm').off('submit').on('submit', (e) => { e.preventDefault(); saveStory(); });
    $('#subTaskForm').off('submit').on('submit', (e) => { e.preventDefault(); saveSubTask(); });
    $('#bugForm').off('submit').on('submit', (e) => { e.preventDefault(); saveBug(); });

    // Tab changes
    $('a[data-toggle="tab"]').off('shown.bs.tab').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr('href').substring(1);
        loadTabContent(target);
    });

    // Pagination
    $(document).off('click', '.page-link').on('click', '.page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        const tab = $('.tab-pane.active').attr('id');

        if (page) {
            loadTabContent(tab, page);
            $('html, body').animate({ scrollTop: $('.tab-content').offset().top - 100 }, 500);
        }
    });

    // Modal cleanup - Ensure all close methods work properly
    $('.modal .close, .modal .btn-secondary').off('click').on('click', function() {
        $(this).closest('.modal').modal('hide');
    });

    // Ensure modals can be closed by backdrop click and escape key
    $('.modal').each(function() {
        var modalId = $(this).attr('id');
        if (modalId) {
            $('#' + modalId).modal({
                backdrop: true,  // Enable backdrop click to close
                keyboard: true   // Enable escape key to close
            });
        }
    });

    $('.modal').off('hidden.bs.modal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
        $('.select2').val(null).trigger('change');
        $('.alert').hide();
    });

    // Load components for epic filter when epics tab is shown
    $('#epics-tab').on('shown.bs.tab', function (e) {
        loadComponentsForEpicFilter();
    });

    console.log('Event listeners initialized');
}

// ==================== Task Loading Functions ====================

function loadComponents(page = 1) {
    showSkeletonLoader('#componentsList');

    const filters = {
        page: page,
        per_page: 12,
        sprint_id: $('#componentFilterSprint').val(),
        user_id: $('#componentFilterUser').val(),
        priority: $('#componentFilterPriority').val(),
        status: $('#componentFilterStatus').val()
    };

    $.ajax({
        url: route('tasks.get-components'),
        method: 'GET',
        data: filters,
        cache: false,
        success: (response) => {
            if (response.success) {
                renderComponents(response.tasks);
                updatePagination('components', response.pagination);
            } else {
                showError('#componentsList', 'Components');
            }
        },
        error: () => showError('#componentsList', 'Components')
    });
}

function loadEpics(page = 1) {
    showSkeletonLoader('#epicsList');

    const filters = {
        page: page,
        per_page: 12,
        sprint_id: $('#epicFilterSprint').val(),
        user_id: $('#epicFilterUser').val(),
        priority: $('#epicFilterPriority').val(),
        status: $('#epicFilterStatus').val()
    };

    $.ajax({
        url: route('tasks.get-epics'),
        method: 'GET',
        data: filters,
        cache: false,
        success: (response) => {
            if (response.success) {
                renderEpics(response.tasks);
                updatePagination('epics', response.pagination);
            } else {
                showError('#epicsList', 'Epics');
            }
        },
        error: () => showError('#epicsList', 'Epics')
    });
}

function loadStories(page = 1) {
    showSkeletonLoader('#storiesList');

    const filters = {
        page: page,
        per_page: 12,
        sprint_id: $('#storyFilterSprint').val(),
        user_id: $('#storyFilterUser').val(),
        priority: $('#storyFilterPriority').val(),
        status: $('#storyFilterStatus').val()
    };

    $.ajax({
        url: route('tasks.get-stories'),
        method: 'GET',
        data: filters,
        cache: false,
        success: (response) => {
            if (response.success) {
                renderStories(response.tasks);
                updatePagination('stories', response.pagination);
            } else {
                showError('#storiesList', 'User Stories');
            }
        },
        error: () => showError('#storiesList', 'User Stories')
    });
}

function loadSubTasks(page = 1) {
    showSkeletonLoader('#subtasksList');

    const filters = {
        page: page,
        per_page: 12,
        sprint_id: $('#subtaskFilterSprint').val(),
        user_id: $('#subtaskFilterUser').val(),
        priority: $('#subtaskFilterPriority').val(),
        status: $('#subtaskFilterStatus').val()
    };

    $.ajax({
        url: route('tasks.get-subtasks'),
        method: 'GET',
        data: filters,
        cache: false,
        success: (response) => {
            if (response.success) {
                renderSubTasks(response.tasks);
                updatePagination('subtasks', response.pagination);
            } else {
                showError('#subtasksList', 'Sub Tasks');
            }
        },
        error: () => showError('#subtasksList', 'Sub Tasks')
    });
}

function loadBugs(page = 1) {
    showSkeletonLoader('#bugsList');

    const filters = {
        page: page,
        per_page: 12,
        sprint_id: $('#bugFilterSprint').val(),
        user_id: $('#bugFilterUser').val(),
        priority: $('#bugFilterPriority').val(),
        status: $('#bugFilterStatus').val()
    };

    $.ajax({
        url: route('tasks.get-bugs'),
        method: 'GET',
        data: filters,
        cache: false,
        success: (response) => {
            if (response.success) {
                renderBugs(response.tasks);
                updatePagination('bugs', response.pagination);
            } else {
                showError('#bugsList', 'Bugs');
            }
        },
        error: () => showError('#bugsList', 'Bugs')
    });
}

// ==================== Filter Functions ====================

// Improved filter functions with debounce for better performance


function resetBacklogFilters() {
    $('#backlogFilterSprint, #backlogFilterUser').val('').trigger('change');
    $('#backlogFilterPriority, #backlogFilterStatus').val('');
    filterBacklog();
}

function filterComponents() {
    clearTimeout(window.componentsFilterTimeout);
    window.componentsFilterTimeout = setTimeout(function() {
        loadComponents(1);
    }, 300);
}

// Enhanced filter function to show children when filtering components
function filterComponentsWithChildren() {
    clearTimeout(window.componentsFilterTimeout);
    window.componentsFilterTimeout = setTimeout(function() {
        loadComponents(1);
        // Load children for filtered components
        const componentId = $('#componentFilter').val();
        if (componentId) {
            showComponentChildren(componentId);
        }
    }, 300);
}

function resetComponentFilters() {
    $('#componentFilterSprint, #componentFilterUser').val('').trigger('change');
    $('#componentFilterPriority, #componentFilterStatus').val('');
    loadComponents(1);
}

function filterEpics() {
    clearTimeout(window.epicsFilterTimeout);
    window.epicsFilterTimeout = setTimeout(function() {
        loadEpics(1);
        // Load children when filtering by component
        const componentId = $('#epicFilterComponent').val();
        if (componentId) {
            loadChildrenForComponent(componentId);
        }
    }, 300);
}

// Enhanced filter function to show children when filtering epics
function filterEpicsWithChildren() {
    clearTimeout(window.epicsFilterTimeout);
    window.epicsFilterTimeout = setTimeout(function() {
        loadEpics(1);
        // Load children for filtered epics
        const epicId = $('#epicFilter').val();
        if (epicId) {
            showEpicChildren(epicId);
        }
    }, 300);
}

// Add this new function to load children for a component
function loadChildrenForComponent(componentId) {
    // Load epics for this component
    $.get(route('api.epics-for-filter'), { component_id: componentId }, (response) => {
        if (response.success) {
            // You can use this data to show epics related to the selected component
            console.log('Epics for component:', response.data);
        }
    });
}

function resetEpicFilters() {
    $('#epicFilterComponent, #epicFilterSprint, #epicFilterUser').val('').trigger('change');
    $('#epicFilterPriority, #epicFilterStatus').val('');
    loadEpics(1);
    loadComponentsForEpicFilter(); // Reload components list
}

function filterStories() {
    clearTimeout(window.storiesFilterTimeout);
    window.storiesFilterTimeout = setTimeout(function() {
        loadStories(1);
    }, 300);
}

function resetStoryFilters() {
    $('#storyFilterSprint, #storyFilterUser').val('').trigger('change');
    $('#storyFilterPriority, #storyFilterStatus').val('');
    loadStories(1);
}

function filterSubTasks() {
    clearTimeout(window.subtasksFilterTimeout);
    window.subtasksFilterTimeout = setTimeout(function() {
        loadSubTasks(1);
    }, 300);
}

function resetSubTaskFilters() {
    $('#subtaskFilterSprint, #subtaskFilterUser').val('').trigger('change');
    $('#subtaskFilterPriority, #subtaskFilterStatus').val('');
    loadSubTasks(1);
}

function filterBugs() {
    clearTimeout(window.bugsFilterTimeout);
    window.bugsFilterTimeout = setTimeout(function() {
        loadBugs(1);
    }, 300);
}

function resetBugFilters() {
    $('#bugFilterSprint, #bugFilterUser').val('').trigger('change');
    $('#bugFilterPriority, #bugFilterStatus').val('');
    loadBugs(1);
}

// ==================== Rendering Functions ====================

function getSprintBadge(sprint) {
    if (!sprint) {
        return '<span class="badge badge-secondary">Backlog</span>';
    }
    return `<span class="badge badge-primary" title="${sprint.goal || ''}">${sprint.name}</span>`;
}

function renderComponents(components) {
    if (components.length === 0) {
        $('#componentsList').html(getEmptyState('package', 'Components'));
        return;
    }

    let html = `<div class="table-responsive"><table class="table table-striped table-bordered">
        <thead><tr>
            <th>العنوان</th><th>Sprint</th><th>الأولوية</th><th>الحالة</th>
            <th>المستخدمون</th><th>الإجراءات</th>
        </tr></thead><tbody>`;

    components.forEach(c => {
        html += `<tr data-component-id="${c.id}" class="component-row">
            <td>
                <span class="component-title" style="cursor: pointer;" onclick="showComponentChildren(${c.id})">
                    ${c.title}
                    <i class="feather icon-chevron-down ml-1"></i>
                </span>
            </td>
            <td>${getSprintBadge(c.sprint)}
                <button class="btn btn-sm btn-outline-primary ml-1" onclick="window.assignSprintToTask(${c.id}, 'component')" title="تعيين Sprint"><i class="feather icon-calendar"></i></button>
            </td>
            <td>${getPriorityBadge(c.priority)}</td>
            <td>${getStatusBadge(c.status)}</td>
            <td>${getUsersBadges(c.assigned_users)}</td>
            <td>
                <button class="btn btn-sm btn-success mr-1" onclick="addEpicToComponent(${c.id})" title="إضافة Epic">
                    <i class="feather icon-plus"></i>
                </button>
                <button class="btn btn-sm btn-light mr-1" onclick="editComponent(${c.id})" title="تعديل">
                    <i class="feather icon-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteComponent(${c.id})" title="حذف">
                    <i class="feather icon-trash"></i>
                </button>
            </td>
        </tr>
        <tr id="component-children-${c.id}" style="display: none;">
            <td colspan="6" class="p-0">
                <div id="component-children-content-${c.id}" class="p-3 bg-light"></div>
            </td>
        </tr>`;
    });

    html += `</tbody></table></div>`;
    $('#componentsList').html(html);
}

function renderEpics(epics) {
    if (epics.length === 0) {
        $('#epicsList').html(getEmptyState('layers', 'Epics'));
        return;
    }

    let html = `<div class="table-responsive"><table class="table table-striped table-bordered">
        <thead><tr>
            <th>العنوان</th><th>Sprint</th><th>الأولوية</th><th>الحالة</th>
            <th>الميزانية</th><th>Bugs</th><th>المستخدمون</th><th>الإجراءات</th>
        </tr></thead><tbody>`;

    epics.forEach(e => {
        html += `<tr data-epic-id="${e.id}" class="epic-row">
            <td>
                <span class="epic-title" style="cursor: pointer;" onclick="showEpicChildren(${e.id})">
                    ${e.title}
                    <i class="feather icon-chevron-down ml-1"></i>
                </span>
            </td>
            <td>${getSprintBadge(e.sprint)}
                <button class="btn btn-sm btn-outline-primary ml-1" onclick="window.assignSprintToTask(${e.id}, 'epic')" title="تعيين Sprint"><i class="feather icon-calendar"></i></button>
            </td>
            <td>${getPriorityBadge(e.priority)}</td>
            <td>${getStatusBadge(e.status)}</td>
            <td>${e.budget ? parseFloat(e.budget).toFixed(2) : '-'}</td>
            <td>${e.bug_count || 0}</td>
            <td>${getUsersBadges(e.assigned_users)}</td>
            <td>
                <button class="btn btn-sm btn-success mr-1" onclick="addStoryToEpic(${e.id})" title="إضافة Story">
                    <i class="feather icon-file-text"></i>
                </button>
                <button class="btn btn-sm btn-warning mr-1" onclick="addBugToEpic(${e.id})" title="إضافة Bug">
                    <i class="feather icon-alert-triangle"></i>
                </button>
                <button class="btn btn-sm btn-light mr-1" onclick="editEpic(${e.id})" title="تعديل">
                    <i class="feather icon-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteEpic(${e.id})" title="حذف">
                    <i class="feather icon-trash"></i>
                </button>
            </td>
        </tr>
        <tr id="epic-children-${e.id}" style="display: none;">
            <td colspan="8" class="p-0">
                <div id="epic-children-content-${e.id}" class="p-3 bg-light"></div>
            </td>
        </tr>`;
    });

    html += `</tbody></table></div>`;
    $('#epicsList').html(html);
}

function renderStories(stories) {
    if (stories.length === 0) {
        $('#storiesList').html(getEmptyState('file-text', 'User Stories'));
        return;
    }

    let html = `<div class="table-responsive"><table class="table table-striped table-bordered">
        <thead><tr>
            <th>العنوان</th><th>Sprint</th><th>الأولوية</th><th>الحالة</th>
            <th>النسبة المكتملة</th><th>المستخدمون</th><th>الإجراءات</th>
        </tr></thead><tbody>`;

    stories.forEach(s => {
        html += `<tr>
            <td>${s.title}</td>
            <td>${getSprintBadge(s.sprint)}
                <button class="btn btn-sm btn-outline-primary ml-1" onclick="window.assignSprintToTask(${s.id}, 'story')" title="تعيين Sprint"><i class="feather icon-calendar"></i></button>
            </td>
            <td>${getPriorityBadge(s.priority)}</td>
            <td>${getStatusBadge(s.status)}</td>
            <td>${s.completion_percentage || 0}%</td>
            <td>${getUsersBadges(s.assigned_users)}</td>
            <td>
                <button class="btn btn-sm btn-success mr-1" onclick="addSubTaskToStory(${s.id})" title="إضافة Sub Task">
                    <i class="feather icon-plus"></i>
                </button>
                <button class="btn btn-sm btn-light mr-1" onclick="editStory(${s.id})" title="تعديل">
                    <i class="feather icon-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteStory(${s.id})" title="حذف">
                    <i class="feather icon-trash"></i>
                </button>
            </td>
        </tr>`;
    });

    html += `</tbody></table></div>`;
    $('#storiesList').html(html);
}

function renderSubTasks(subtasks) {
    if (subtasks.length === 0) {
        $('#subtasksList').html(getEmptyState('list', 'Sub Tasks'));
        return;
    }

    let html = `<div class="table-responsive"><table class="table table-striped table-bordered">
        <thead><tr>
            <th>العنوان</th><th>Sprint</th><th>الأولوية</th><th>الحالة</th>
            <th>النسبة المكتملة</th><th>الوقت المقدر</th><th>المستخدمون</th><th>الإجراءات</th>
        </tr></thead><tbody>`;

    subtasks.forEach(st => {
        html += `<tr>
            <td>${st.title}</td>
            <td>${getSprintBadge(st.sprint)}
                <button class="btn btn-sm btn-outline-primary ml-1" onclick="window.assignSprintToTask(${st.id}, 'subtask')" title="تعيين Sprint"><i class="feather icon-calendar"></i></button>
            </td>
            <td>${getPriorityBadge(st.priority)}</td>
            <td>${getStatusBadge(st.status)}</td>
            <td>${st.completion_percentage || 0}%</td>
            <td>${st.estimated_hours ? st.estimated_hours + ' ساعة' : '-'}</td>
            <td>${getUsersBadges(st.assigned_users)}</td>
            <td>
                <button class="btn btn-sm btn-light mr-1" onclick="editSubTask(${st.id})" title="تعديل">
                    <i class="feather icon-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteSubTask(${st.id})" title="حذف">
                    <i class="feather icon-trash"></i>
                </button>
            </td>
        </tr>`;
    });

    html += `</tbody></table></div>`;
    $('#subtasksList').html(html);
}

function renderBugs(bugs) {
    if (bugs.length === 0) {
        $('#bugsList').html(getEmptyState('alert-triangle', 'Bugs'));
        return;
    }

    let html = `<div class="table-responsive"><table class="table table-striped table-bordered">
        <thead><tr>
            <th>العنوان</th><th>Sprint</th><th>الأولوية</th><th>الحالة</th>
            <th>النسبة المكتملة</th><th>الوقت المقدر</th><th>المستخدمون</th><th>الإجراءات</th>
        </tr></thead><tbody>`;

    bugs.forEach(b => {
        html += `<tr>
            <td>${b.title}</td>
            <td>${getSprintBadge(b.sprint)}
                <button class="btn btn-sm btn-outline-primary ml-1" onclick="window.assignSprintToTask(${b.id}, 'bug')" title="تعيين Sprint"><i class="feather icon-calendar"></i></button>
            </td>
            <td>${getPriorityBadge(b.priority)}</td>
            <td>${getStatusBadge(b.status)}</td>
            <td>${b.completion_percentage || 0}%</td>
            <td>${b.estimated_hours ? b.estimated_hours + ' ساعة' : '-'}</td>
            <td>${getUsersBadges(b.assigned_users)}</td>
            <td>
                <button class="btn btn-sm btn-light mr-1" onclick="editBug(${b.id})" title="تعديل">
                    <i class="feather icon-edit"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteBug(${b.id})" title="حذف">
                    <i class="feather icon-trash"></i>
                </button>
            </td>
        </tr>`;
    });

    html += `</tbody></table></div>`;
    $('#bugsList').html(html);
}

// ==================== Helper Functions ====================

function getPriorityBadge(priority) {
    const classes = { low: 'success', medium: 'warning', high: 'danger', urgent: 'dark' };
    const names = { low: 'منخفضة', medium: 'متوسطة', high: 'عالية', urgent: 'عاجلة' };
    return `<span class="badge badge-${classes[priority] || 'secondary'}">${names[priority] || priority}</span>`;
}

// Add new functions to show children
function showComponentChildren(componentId) {
    const row = $(`#component-children-${componentId}`);
    const content = $(`#component-children-content-${componentId}`);
    const icon = $(`tr[data-component-id="${componentId}"] .component-title i`);

    if (row.is(':visible')) {
        // Hide children
        row.hide();
        icon.removeClass('icon-chevron-up').addClass('icon-chevron-down');
    } else {
        // Show loading state
        content.html('<div class="text-center"><i class="feather icon-loader"></i> جاري تحميل الإpics...</div>');
        row.show();
        icon.removeClass('icon-chevron-down').addClass('icon-chevron-up');

        // Load epics for this component
        $.get(route('api.epics-for-filter'), { component_id: componentId }, (response) => {
            if (response.success) {
                if (response.data.length > 0) {
                    let html = '<h6>الـ Epics المرتبطة بهذا Component:</h6><ul class="list-group">';
                    response.data.forEach(epic => {
                        html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                            ${epic.title}
                            <button class="btn btn-sm btn-primary" onclick="showEpicChildren(${epic.id})">عرض التفاصيل</button>
                        </li>`;
                    });
                    html += '</ul>';
                    content.html(html);
                } else {
                    content.html('<div class="alert alert-info">لا توجد Epics مرتبطة بهذا Component</div>');
                }
            } else {
                content.html('<div class="alert alert-danger">حدث خطأ أثناء تحميل الإpics</div>');
            }
        }).fail(() => {
            content.html('<div class="alert alert-danger">حدث خطأ أثناء تحميل الإpics</div>');
        });
    }
}

function showEpicChildren(epicId) {
    const row = $(`#epic-children-${epicId}`);
    const content = $(`#epic-children-content-${epicId}`);
    const icon = $(`tr[data-epic-id="${epicId}"] .epic-title i`);

    if (row.is(':visible')) {
        // Hide children
        row.hide();
        icon.removeClass('icon-chevron-up').addClass('icon-chevron-down');
    } else {
        // Show loading state
        content.html('<div class="text-center"><i class="feather icon-loader"></i> جاري تحميل الـ Stories والـ Bugs...</div>');
        row.show();
        icon.removeClass('icon-chevron-down').addClass('icon-chevron-up');

        // Load stories and bugs for this epic
        Promise.all([
            $.get(route('backlog.get-stories'), { epic_id: epicId }),
            $.get(route('backlog.get-bugs'), { epic_id: epicId })
        ]).then(([storiesResponse, bugsResponse]) => {
            let html = '<div class="row">';
            
            // Stories section
            html += '<div class="col-md-6"><h6>الـ User Stories:</h6>';
            if (storiesResponse.success && storiesResponse.tasks.length > 0) {
                html += '<ul class="list-group">';
                storiesResponse.tasks.forEach(story => {
                    html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        ${story.title}
                        <span>${getPriorityBadge(story.priority)} ${getStatusBadge(story.status)}</span>
                    </li>`;
                });
                html += '</ul>';
            } else {
                html += '<div class="alert alert-info">لا توجد User Stories</div>';
            }
            html += '</div>';

            // Bugs section
            html += '<div class="col-md-6"><h6>الـ Bugs:</h6>';
            if (bugsResponse.success && bugsResponse.tasks.length > 0) {
                html += '<ul class="list-group">';
                bugsResponse.tasks.forEach(bug => {
                    html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        ${bug.title}
                        <span>${getPriorityBadge(bug.priority)} ${getStatusBadge(bug.status)}</span>
                    </li>`;
                });
                html += '</ul>';
            } else {
                html += '<div class="alert alert-info">لا توجد Bugs</div>';
            }
            html += '</div>';

            html += '</div>';
            content.html(html);
        }).catch(() => {
            content.html('<div class="alert alert-danger">حدث خطأ أثناء تحميل الـ Stories والـ Bugs</div>');
        });
    }
}

// Function to close a sprint
function closeSprint(sprintId) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'سيتم إغلاق هذا السبرنت وإخفاء المهام المرتبطة به',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، أغلقه!',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: route('sprints.close', { sprint: sprintId }),
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire(
                            'تم!',
                            'تم إغلاق السبرنت بنجاح.',
                            'success'
                        );
                        // Reload the current tab
                        const activeTab = $('.tab-pane.active').attr('id');
                        loadTabContent(activeTab);
                    } else {
                        Swal.fire(
                            'خطأ!',
                            response.message || 'حدث خطأ أثناء إغلاق السبرنت.',
                            'error'
                        );
                    }
                },
                error: function() {
                    Swal.fire(
                        'خطأ!',
                        'حدث خطأ أثناء إغلاق السبرنت.',
                        'error'
                    );
                }
            });
        }
    });
}

// Function to reopen a closed sprint
function reopenSprint(sprintId) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: 'سيتم إعادة فتح هذا السبرنت وإظهار المهام المرتبطة به',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'نعم، أعد فتحه!',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: route('sprints.reopen', { sprint: sprintId }),
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire(
                            'تم!',
                            'تم إعادة فتح السبرنت بنجاح.',
                            'success'
                        );
                        // Reload the current tab
                        const activeTab = $('.tab-pane.active').attr('id');
                        loadTabContent(activeTab);
                    } else {
                        Swal.fire(
                            'خطأ!',
                            response.message || 'حدث خطأ أثناء إعادة فتح السبرنت.',
                            'error'
                        );
                    }
                },
                error: function() {
                    Swal.fire(
                        'خطأ!',
                        'حدث خطأ أثناء إعادة فتح السبرنت.',
                        'error'
                    );
                }
            });
        }
    });
}

function getStatusBadge(status) {
    const classes = { not_started: 'secondary', in_progress: 'warning', completed: 'success', overdue: 'danger' };
    const names = { not_started: 'لم تبدأ', in_progress: 'قيد التنفيذ', completed: 'مكتملة', overdue: 'متأخرة' };
    return `<span class="badge badge-${classes[status] || 'secondary'}">${names[status] || status}</span>`;
}

function getUsersBadges(users) {
    if (!users || users.length === 0) return '-';
    return users.map(u => `<span class="badge badge-secondary">${u.name}</span>`).join(' ');
}

function getEmptyState(icon, type) {
    return `<div class="empty-state">
        <i class="feather icon-${icon}"></i>
        <h4>لا توجد ${type}</h4>
        <p>ابدأ بإضافة ${type} جديدة</p>
    </div>`;
}

function showError(container, type) {
    $(container).html(`<div class="empty-state">
        <i class="feather icon-alert-circle"></i>
        <h4>خطأ في تحميل ${type}</h4>
        <p>يرجى المحاولة مرة أخرى</p>
    </div>`);
}

function showSkeletonLoader(containerId) {
    let html = '';
    for (let i = 0; i < 3; i++) {
        html += `<div class="skeleton-loader">
            <div class="skeleton-header"></div>
            <div class="skeleton-content"></div>
            <div class="skeleton-content"></div>
        </div>`;
    }
    $(containerId).html(html);
}

function updatePagination(type, pagination) {
    const container = `#${type}PaginationContainer`;
    const list = `#${type}Pagination`;
    const info = `#${type}Info`;

    if (pagination.last_page <= 1) {
        $(container).hide();
        return;
    }

    const curr = pagination.current_page;
    const total = pagination.last_page;
    let html = '';

    // First & Previous
    if (pagination.has_previous_pages) {
        html += `<li class="page-item"><a class="page-link" href="#" data-page="1"><i class="fa fa-angle-double-right"></i></a></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" data-page="${curr - 1}"><i class="fa fa-angle-right"></i></a></li>`;
    } else {
        html += `<li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-double-right"></i></span></li>`;
        html += `<li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-right"></i></span></li>`;
    }

    // Current page
    html += `<li class="page-item active"><span class="page-link">${curr}</span></li>`;

    // Next & Last
    if (pagination.has_more_pages) {
        html += `<li class="page-item"><a class="page-link" href="#" data-page="${curr + 1}"><i class="fa fa-angle-left"></i></a></li>`;
        html += `<li class="page-item"><a class="page-link" href="#" data-page="${total}"><i class="fa fa-angle-double-left"></i></a></li>`;
    } else {
        html += `<li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-left"></i></span></li>`;
        html += `<li class="page-item disabled"><span class="page-link"><i class="fa fa-angle-double-left"></i></span></li>`;
    }

    $(list).html(html);
    $(info).text(`عرض ${pagination.from || 0} إلى ${pagination.to || 0} من ${pagination.total || 0} نتيجة`);
    $(container).show();
}

// ==================== Quick Add Functions ====================

function addEpicToComponent(componentId) {
    loadComponentsForEpic();
    openEpicModal();
    // Automatically select the component when adding an epic to a specific component
    setTimeout(() => {
        $('#epic_parent_task_id').val(componentId).trigger('change');
    }, 300);
}

function addStoryToEpic(epicId) {
    loadEpicsForStory();
    openStoryModal();
    setTimeout(() => {
        $('#story_parent_task_id').val(epicId).trigger('change');
    }, 300);
}

function addSubTaskToStory(storyId) {
    loadStoriesForSubTask();
    openSubTaskModal();
    setTimeout(() => {
        $('#subtask_parent_task_id').val(storyId).trigger('change');
    }, 300);
}

function addBugToEpic(epicId) {
    loadEpicsForBug();
    openBugModal();
    setTimeout(() => {
        $('#bug_parent_task_id').val(epicId).trigger('change');
    }, 300);
}

// ==================== Component CRUD ====================

function openComponentModal(componentId = null) {
    console.log('Opening component modal with ID:', componentId);

    // Check if modal exists
    if ($('#componentModal').length === 0) {
        console.error('Component modal not found');
        return;
    }

    $('#componentFormErrors').hide();
    $('#componentForm')[0].reset();
    $('.select2').val(null).trigger('change');

    if (componentId) {
        $('#componentModalTitle').text('تعديل Component');
        $('#saveComponentBtn').html('<i class="feather icon-save"></i> تحديث');
        $.get(route('tasks.show', {id: componentId}), (response) => {
            if (response.success) fillComponentForm(response.task);
        });
    } else {
        $('#componentModalTitle').text('إضافة Component جديد');
        $('#saveComponentBtn').html('<i class="feather icon-save"></i> حفظ');
        $('#component_id').val('');
    }

    $('#componentModal').modal('show');
}

function fillComponentForm(task) {
    $('#component_id').val(task.id);
    $('#component_project_id').val(task.project_id).trigger('change');
    $('#component_sprint_id').val(task.sprint_id || '').trigger('change');
    $('#component_title').val(task.title);
    $('#component_description').val(task.description);
    $('#component_priority').val(task.priority);
    if (task.assigned_users && task.assigned_users.length > 0) {
        $('#component_assigned_users').val(task.assigned_users.map(u => u.id)).trigger('change');
    }
}

function saveComponent() {
    const formData = new FormData($('#componentForm')[0]);
    $('#saveComponentBtn').prop('disabled', true).html('<i class="feather icon-loader"></i> جاري الحفظ...');

    $.ajax({
        url: route('tasks.store'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: (response) => {
            if (response.success) {
                $('#componentModal').modal('hide');
                showToast('success', response.message);
                loadComponents();
            }
        },
        error: (xhr) => {
            if (xhr.status === 422) {
                showFormErrors('#componentFormErrors', xhr.responseJSON.errors);
            } else {
                showToast('error', xhr.responseJSON?.message || 'حدث خطأ');
            }
        },
        complete: () => {
            $('#saveComponentBtn').prop('disabled', false).html('<i class="feather icon-save"></i> حفظ');
        }
    });
}

function editComponent(id) {
    console.log('Editing component with ID:', id);
    openComponentModal(id);
}

function deleteComponent(id) {
    confirmDelete('Component', () => {
        $.ajax({
            url: route('tasks.destroy', {id: id}),
            type: 'DELETE',
            data: { _token: $('meta[name="csrf-token"]').attr('content') },
            success: (response) => {
                if (response.success) {
                    showToast('success', response.message);
                    loadComponents();
                }
            },
            error: () => showToast('error', 'حدث خطأ أثناء الحذف')
        });
    });
}

// ==================== Epic CRUD ====================

function loadComponentsForEpic() {
    $.get(route('tasks.get-components'), (response) => {
        if (response.success) {
            let options = '<option value="">اختر Component</option>';
            response.tasks.forEach(c => options += `<option value="${c.id}">${c.title}</option>`);
            $('#epic_parent_task_id').html(options).trigger('change');
        }
    });
}

// Add this new function to load components for epic filter
function loadComponentsForEpicFilter() {
    $.get(route('api.components-for-filter'), (response) => {
        if (response.success) {
            let options = '<option value="">جميع Components</option>';
            response.data.forEach(c => options += `<option value="${c.id}">${c.title}</option>`);
            $('#epicFilterComponent').html(options).trigger('change');
        }
    });
}

function openEpicModal(epicId = null) {
    console.log('Opening epic modal with ID:', epicId);

    // Check if modal exists
    if ($('#epicModal').length === 0) {
        console.error('Epic modal not found');
        return;
    }

    $('#epicFormErrors').hide();
    $('#epicForm')[0].reset();
    $('.select2').val(null).trigger('change');

    if (epicId) {
        $('#epicModalTitle').text('تعديل Epic');
        $('#saveEpicBtn').html('<i class="feather icon-save"></i> تحديث');
        $.get(route('tasks.show', {id: epicId}), (response) => {
            if (response.success) fillEpicForm(response.task);
        });
    } else {
        $('#epicModalTitle').text('إضافة Epic جديد');
        $('#saveEpicBtn').html('<i class="feather icon-save"></i> حفظ');
        $('#epic_id').val('');
    }

    $('#epicModal').modal('show');
}

function fillEpicForm(task) {
    $('#epic_id').val(task.id);
    $('#epic_parent_task_id').val(task.parent_task_id).trigger('change');
    $('#epic_sprint_id').val(task.sprint_id || '').trigger('change');
    $('#epic_title').val(task.title);
    $('#epic_description').val(task.description);
    $('#epic_priority').val(task.priority);
    $('#epic_budget').val(task.budget);

}

function saveEpic() {
    const formData = new FormData($('#epicForm')[0]);
    $('#saveEpicBtn').prop('disabled', true).html('<i class="feather icon-loader"></i> جاري الحفظ...');

    $.ajax({
        url: route('tasks.store'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: (response) => {
            if (response.success) {
                $('#epicModal').modal('hide');
                showToast('success', response.message);
                loadEpics();
                loadComponents();
            }
        },
        error: (xhr) => {
            if (xhr.status === 422) {
                showFormErrors('#epicFormErrors', xhr.responseJSON.errors);
            } else {
                showToast('error', xhr.responseJSON?.message || 'حدث خطأ');
            }
        },
        complete: () => {
            $('#saveEpicBtn').prop('disabled', false).html('<i class="feather icon-save"></i> حفظ');
        }
    });
}

function editEpic(id) {
    console.log('Editing epic with ID:', id);
    loadComponentsForEpic();
    openEpicModal(id);
}

function deleteEpic(id) {
    confirmDelete('Epic', () => {
        $.ajax({
            url: route('tasks.destroy', {id: id}),
            type: 'DELETE',
            data: { _token: $('meta[name="csrf-token"]').attr('content') },
            success: (response) => {
                if (response.success) {
                    showToast('success', response.message);
                    loadEpics();
                    loadComponents();
                }
            },
            error: () => showToast('error', 'حدث خطأ أثناء الحذف')
        });
    });
}

// ==================== Story CRUD ====================

function loadEpicsForStory() {
    $.get(route('tasks.get-epics'), (response) => {
        if (response.success) {
            let options = '<option value="">اختر Epic</option>';
            response.tasks.forEach(e => options += `<option value="${e.id}">${e.title}</option>`);
            $('#story_parent_task_id').html(options).trigger('change');
        }
    });
}

function openStoryModal(storyId = null) {
    console.log('Opening story modal with ID:', storyId);

    // Check if modal exists
    if ($('#storyModal').length === 0) {
        console.error('Story modal not found');
        return;
    }

    $('#storyFormErrors').hide();
    $('#storyForm')[0].reset();
    $('.select2').val(null).trigger('change');

    if (storyId) {
        $('#storyModalTitle').text('تعديل User Story');
        $('#saveStoryBtn').html('<i class="feather icon-save"></i> تحديث');
        $.get(route('tasks.show', {id: storyId}), (response) => {
            if (response.success) fillStoryForm(response.task);
        });
    } else {
        $('#storyModalTitle').text('إضافة User Story جديدة');
        $('#saveStoryBtn').html('<i class="feather icon-save"></i> حفظ');
        $('#story_id').val('');
    }

    $('#storyModal').modal('show');
}

function fillStoryForm(task) {
    $('#story_id').val(task.id);
    $('#story_parent_task_id').val(task.parent_task_id).trigger('change');
    $('#story_sprint_id').val(task.sprint_id || '').trigger('change');
    $('#story_title').val(task.title);
    $('#story_description').val(task.description);
    $('#story_priority').val(task.priority);
    $('#story_estimated_hours').val(task.estimated_hours);
    $('#story_completion_percentage').val(task.completion_percentage);

}

function saveStory() {
    const formData = new FormData($('#storyForm')[0]);
    $('#saveStoryBtn').prop('disabled', true).html('<i class="feather icon-loader"></i> جاري الحفظ...');

    $.ajax({
        url: route('tasks.store'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: (response) => {
            if (response.success) {
                $('#storyModal').modal('hide');
                showToast('success', response.message);
                loadStories();
                loadEpics();
            }
        },
        error: (xhr) => {
            if (xhr.status === 422) {
                showFormErrors('#storyFormErrors', xhr.responseJSON.errors);
            } else {
                showToast('error', xhr.responseJSON?.message || 'حدث خطأ');
            }
        },
        complete: () => {
            $('#saveStoryBtn').prop('disabled', false).html('<i class="feather icon-save"></i> حفظ');
        }
    });
}

function editStory(id) {
    console.log('Editing story with ID:', id);
    loadEpicsForStory();
    openStoryModal(id);
}

function deleteStory(id) {
    confirmDelete('Story', () => {
        $.ajax({
            url: route('tasks.destroy', {id: id}),
            type: 'DELETE',
            data: { _token: $('meta[name="csrf-token"]').attr('content') },
            success: (response) => {
                if (response.success) {
                    showToast('success', response.message);
                    loadStories();
                    loadEpics();
                }
            },
            error: () => showToast('error', 'حدث خطأ أثناء الحذف')
        });
    });
}

// ==================== SubTask & Bug CRUD (Similar Pattern) ====================

function loadStoriesForSubTask() {
    $.get(route('tasks.get-stories'), (response) => {
        if (response.success) {
            let options = '<option value="">اختر User Story</option>';
            response.tasks.forEach(s => options += `<option value="${s.id}">${s.title}</option>`);
            $('#subtask_parent_task_id').html(options).trigger('change');
        }
    });
}

function openSubTaskModal(id = null) {
    console.log('Opening subtask modal with ID:', id);

    // Check if modal exists
    if ($('#subTaskModal').length === 0) {
        console.error('SubTask modal not found');
        return;
    }

    $('#subTaskFormErrors').hide();
    $('#subTaskForm')[0].reset();
    $('.select2').val(null).trigger('change');
    if (id) {
        $('#subTaskModalTitle').text('تعديل Sub Task');
        $('#saveSubTaskBtn').html('<i class="feather icon-save"></i> تحديث');
        $.get(route('tasks.show', {id: id}), (r) => {
            if (r.success) {
                $('#subtask_id').val(r.task.id);
                $('#subtask_parent_task_id').val(r.task.parent_task_id).trigger('change');
                $('#subtask_sprint_id').val(r.task.sprint_id || '').trigger('change');
                $('#subtask_title').val(r.task.title);
                $('#subtask_description').val(r.task.description);
                $('#subtask_priority').val(r.task.priority);
                $('#subtask_estimated_hours').val(r.task.estimated_hours);
                $('#subtask_completion_percentage').val(r.task.completion_percentage);
            }
        });
    } else {
        $('#subTaskModalTitle').text('إضافة Sub Task جديدة');
        $('#saveSubTaskBtn').html('<i class="feather icon-save"></i> حفظ');
        $('#subtask_id').val('');
    }
    $('#subTaskModal').modal('show');
}

function fillSubTaskForm(task) {
    $('#subtask_id').val(task.id);
    $('#subtask_parent_task_id').val(task.parent_task_id).trigger('change');
    $('#subtask_sprint_id').val(task.sprint_id || '').trigger('change');
    $('#subtask_title').val(task.title);
    $('#subtask_description').val(task.description);
    $('#subtask_priority').val(task.priority);
    $('#subtask_estimated_hours').val(task.estimated_hours);
    $('#subtask_completion_percentage').val(task.completion_percentage);

}

function saveSubTask() {
    const formData = new FormData($('#subTaskForm')[0]);
    $('#saveSubTaskBtn').prop('disabled', true).html('<i class="feather icon-loader"></i> جاري الحفظ...');
    $.ajax({
        url: route('tasks.store'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: (r) => {
            if (r.success) {
                $('#subTaskModal').modal('hide');
                showToast('success', r.message);
                loadSubTasks();
                loadStories();
            }
        },
        error: (xhr) => {
            if (xhr.status === 422) showFormErrors('#subTaskFormErrors', xhr.responseJSON.errors);
            else showToast('error', xhr.responseJSON?.message || 'حدث خطأ');
        },
        complete: () => $('#saveSubTaskBtn').prop('disabled', false).html('<i class="feather icon-save"></i> حفظ')
    });
}

function editSubTask(id) {
    console.log('Editing subtask with ID:', id);
    loadStoriesForSubTask();
    openSubTaskModal(id);
}
function deleteSubTask(id) {
    confirmDelete('Sub Task', () => {
        $.ajax({
            url: route('tasks.destroy', {id: id}),
            type: 'DELETE',
            data: { _token: $('meta[name="csrf-token"]').attr('content') },
            success: (r) => { if (r.success) { showToast('success', r.message); loadSubTasks(); loadStories(); }},
            error: () => showToast('error', 'حدث خطأ')
        });
    });
}

function loadEpicsForBug() {
    $.get(route('tasks.get-epics'), (r) => {
        if (r.success) {
            let opts = '<option value="">اختر Epic</option>';
            r.tasks.forEach(e => opts += `<option value="${e.id}">${e.title}</option>`);
            $('#bug_parent_task_id').html(opts).trigger('change');
        }
    });
}

function openBugModal(id = null) {
    console.log('Opening bug modal with ID:', id);

    // Check if modal exists
    if ($('#bugModal').length === 0) {
        console.error('Bug modal not found');
        return;
    }

    $('#bugFormErrors').hide();
    $('#bugForm')[0].reset();
    $('.select2').val(null).trigger('change');
    if (id) {
        $('#bugModalTitle').text('تعديل Bug');
        $('#saveBugBtn').html('<i class="feather icon-save"></i> تحديث');
        $.get(route('tasks.show', {id: id}), (r) => {
            if (r.success) {
                $('#bug_id').val(r.task.id);
                $('#bug_parent_task_id').val(r.task.parent_task_id).trigger('change');
                $('#bug_sprint_id').val(r.task.sprint_id || '').trigger('change');
                $('#bug_title').val(r.task.title);
                $('#bug_description').val(r.task.description);
                $('#bug_priority').val(r.task.priority);
                $('#bug_estimated_hours').val(r.task.estimated_hours);
                $('#bug_completion_percentage').val(r.task.completion_percentage);
            }
        });
    } else {
        $('#bugModalTitle').text('إضافة Bug جديد');
        $('#saveBugBtn').html('<i class="feather icon-save"></i> حفظ');
        $('#bug_id').val('');
    }
    $('#bugModal').modal('show');
}

function fillBugForm(task) {
    $('#bug_id').val(task.id);
    $('#bug_parent_task_id').val(task.parent_task_id).trigger('change');
    $('#bug_sprint_id').val(task.sprint_id || '').trigger('change');
    $('#bug_title').val(task.title);
    $('#bug_description').val(task.description);
    $('#bug_priority').val(task.priority);
    $('#bug_estimated_hours').val(task.estimated_hours);
    $('#bug_completion_percentage').val(task.completion_percentage);

}

function saveBug() {
    const formData = new FormData($('#bugForm')[0]);
    $('#saveBugBtn').prop('disabled', true).html('<i class="feather icon-loader"></i> جاري الحفظ...');
    $.ajax({
        url: route('tasks.store'),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: (r) => {
            if (r.success) {
                $('#bugModal').modal('hide');
                showToast('success', r.message);
                loadBugs();
                loadEpics();
            }
        },
        error: (xhr) => {
            if (xhr.status === 422) showFormErrors('#bugFormErrors', xhr.responseJSON.errors);
            else showToast('error', xhr.responseJSON?.message || 'حدث خطأ');
        },
        complete: () => $('#saveBugBtn').prop('disabled', false).html('<i class="feather icon-save"></i> حفظ')
    });
}

function editBug(id) {
    console.log('Editing bug with ID:', id);
    loadEpicsForBug();
    openBugModal(id);
}
function deleteBug(id) {
    confirmDelete('Bug', () => {
        $.ajax({
            url: route('tasks.destroy', {id: id}),
            type: 'DELETE',
            data: { _token: $('meta[name="csrf-token"]').attr('content') },
            success: (r) => { if (r.success) { showToast('success', r.message); loadBugs(); loadEpics(); }},
            error: () => showToast('error', 'حدث خطأ')
        });
    });
}

// ==================== Utility Functions ====================

function showFormErrors(container, errors) {
    let html = '<ul class="mb-0">';
    $.each(errors, (key, value) => html += `<li>${value[0]}</li>`);
    html += '</ul>';
    $(container).html(html).show();
}

function confirmDelete(type, callback) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: `سيتم حذف هذا ${type} وكل ما يتعلق به!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'نعم، احذف!',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) callback();
    });
}

function showToast(type, message) {
    const icons = { success: 'success', error: 'error', warning: 'warning', info: 'info' };
    Swal.fire({
        icon: icons[type] || 'info',
        title: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: type === 'error' ? 4000 : 2500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
}

// Add a new function to assign sprint to any task with hierarchical inheritance
function assignSprintToTask(taskId, taskType) {
    // Create a simple prompt for sprint selection
    const sprintOptions = $('#componentFilterSprint').html() || '';
    const sprintSelect = `
        <div class="form-group">
            <label>اختر Sprint:</label>
            <select id="sprintSelect" class="form-control" style="width: 100%;">
                <option value="">بدون Sprint - Backlog</option>
                ${sprintOptions}
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="createNewSprintBtn">
                <i class="feather icon-plus"></i> إنشاء Sprint جديد
            </button>
        </div>
        <div class="form-group" id="newSprintForm" style="display: none; margin-top: 15px;">
            <label>اسم Sprint الجديد:</label>
            <input type="text" id="newSprintName" class="form-control" placeholder="أدخل اسم Sprint">
            <label class="mt-2">الهدف:</label>
            <textarea id="newSprintGoal" class="form-control" placeholder="أدخل هدف Sprint"></textarea>
            <label class="mt-2">تاريخ البدء:</label>
            <input type="date" id="newSprintStartDate" class="form-control">
            <label class="mt-2">تاريخ الانتهاء:</label>
            <input type="date" id="newSprintEndDate" class="form-control">
        </div>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="applyToChildren" checked>
            <label class="form-check-label" for="applyToChildren">
                تطبيق على جميع العناصر الفرعية
            </label>
        </div>
    `;

    Swal.fire({
        title: 'تعيين Sprint',
        html: sprintSelect,
        showCancelButton: true,
        confirmButtonText: 'حفظ',
        cancelButtonText: 'إلغاء',
        didOpen: () => {
            // Load task current sprint if exists
            $.get('/tasks/' + taskId, (response) => {
                if (response.success && response.task.sprint_id) {
                    $('#sprintSelect').val(response.task.sprint_id);
                }
            });

            // Toggle new sprint form
            $('#createNewSprintBtn').on('click', function() {
                $('#newSprintForm').toggle();
                if ($('#newSprintForm').is(':visible')) {
                    $(this).html('<i class="feather icon-x"></i> إلغاء إنشاء Sprint');
                } else {
                    $(this).html('<i class="feather icon-plus"></i> إنشاء Sprint جديد');
                    // Clear form fields
                    $('#newSprintName').val('');
                    $('#newSprintGoal').val('');
                    $('#newSprintStartDate').val('');
                    $('#newSprintEndDate').val('');
                }
            });
        },
        preConfirm: () => {
            // Check if creating new sprint
            if ($('#newSprintForm').is(':visible')) {
                const name = $('#newSprintName').val();
                const goal = $('#newSprintGoal').val();
                const startDate = $('#newSprintStartDate').val();
                const endDate = $('#newSprintEndDate').val();

                if (!name) {
                    Swal.showValidationMessage('يرجى إدخال اسم Sprint');
                    return false;
                }

                return {
                    createNew: true,
                    name: name,
                    goal: goal,
                    start_date: startDate,
                    end_date: endDate,
                    applyToChildren: $('#applyToChildren').is(':checked')
                };
            } else {
                const sprintId = $('#sprintSelect').val();
                const applyToChildren = $('#applyToChildren').is(':checked');
                return {
                    createNew: false,
                    sprintId: sprintId,
                    applyToChildren: applyToChildren
                };
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            if (result.value.createNew) {
                // Create new sprint and assign it
                createAndAssignSprint(
                    taskId,
                    result.value.name,
                    result.value.goal,
                    result.value.start_date,
                    result.value.end_date,
                    result.value.applyToChildren
                );
            } else {
                // Update the task with the selected sprint
                const formData = new FormData();
                formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                if (result.value.sprintId) {
                    formData.append('sprint_id', result.value.sprintId);
                }

                if (result.value.applyToChildren) {
                    // Use route helper for updating children sprint - POST method
                    $.ajax({
                        url: route('product-backlog.update-children-sprint', { taskId: taskId }),
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: (response) => {
                            if (response.success) {
                                showToast('success', 'تم تحديث Sprint لجميع العناصر الفرعية');
                                // Reload the current tab
                                const activeTab = $('.tab-pane.active').attr('id');
                                loadTabContent(activeTab);
                            } else {
                                showToast('error', response.message || 'حدث خطأ أثناء تحديث العناصر الفرعية');
                                // Still reload the current tab
                                const activeTab = $('.tab-pane.active').attr('id');
                                loadTabContent(activeTab);
                            }
                        },
                        error: (xhr) => {
                            showToast('error', 'حدث خطأ أثناء تحديث العناصر الفرعية: ' + (xhr.responseJSON?.message || 'خطأ غير معروف'));
                            // Still reload the current tab
                            const activeTab = $('.tab-pane.active').attr('id');
                            loadTabContent(activeTab);
                        }
                    });
                } else {
                    // Update only the current task - PUT method
                    const putData = new FormData();
                    putData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                    putData.append('_method', 'PUT');
                    if (result.value.sprintId) {
                        putData.append('sprint_id', result.value.sprintId);
                    }

                    $.ajax({
                        url: '/tasks/' + taskId,
                        type: 'POST',
                        data: putData,
                        processData: false,
                        contentType: false,
                        success: (response) => {
                            if (response.success) {
                                showToast('success', 'تم تحديث Sprint للمهمة');
                                // Reload the current tab
                                const activeTab = $('.tab-pane.active').attr('id');
                                loadTabContent(activeTab);
                            }
                        },
                        error: () => {
                            showToast('error', 'حدث خطأ أثناء تحديث Sprint');
                        }
                    });
                }
            }
        }
    });
}

// Function to create a new sprint and assign it to a task
function createAndAssignSprint(taskId, name, goal, startDate, endDate, applyToChildren) {
    // Show loading message
    Swal.fire({
        title: 'جاري إنشاء Sprint...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Get the project ID from the task (we need to fetch the task first)
    $.get('/tasks/' + taskId, (taskResponse) => {
        if (taskResponse.success && taskResponse.task.project_id) {
            // Create the new sprint
            const sprintData = new FormData();
            sprintData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            sprintData.append('project_id', taskResponse.task.project_id);
            sprintData.append('name', name);
            if (goal) sprintData.append('goal', goal);
            if (startDate) sprintData.append('start_date', startDate);
            if (endDate) sprintData.append('end_date', endDate);
            // Set default status to planned
            sprintData.append('status', 'planned');

            $.ajax({
                url: '/sprints',
                type: 'POST',
                data: sprintData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success && response.sprint) {
                        // Add the new sprint to all sprint dropdowns
                        const newOption = new Option(response.sprint.name, response.sprint.id, true, true);

                        $('#componentFilterSprint').append(newOption.cloneNode(true));
                        $('#epicFilterSprint').append(newOption.cloneNode(true));
                        $('#storyFilterSprint').append(newOption.cloneNode(true));
                        $('#subtaskFilterSprint').append(newOption.cloneNode(true));
                        $('#bugFilterSprint').append(newOption.cloneNode(true));

                        // Now assign this sprint to the task
                        const formData = new FormData();
                        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                        formData.append('sprint_id', response.sprint.id);

                        if (applyToChildren) {
                            // Use route helper for updating children sprint - POST method
                            $.ajax({
                                url: route('product-backlog.update-children-sprint', { taskId: taskId }),
                                type: 'POST',
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: (response) => {
                                    if (response.success) {
                                        Swal.fire('نجاح', 'تم إنشاء Sprint وتعيينه لجميع العناصر الفرعية', 'success');
                                        // Reload the current tab
                                        const activeTab = $('.tab-pane.active').attr('id');
                                        loadTabContent(activeTab);
                                    } else {
                                        Swal.fire('خطأ', response.message || 'حدث خطأ أثناء تحديث العناصر الفرعية', 'error');
                                        // Still reload the current tab
                                        const activeTab = $('.tab-pane.active').attr('id');
                                        loadTabContent(activeTab);
                                    }
                                },
                                error: (xhr) => {
                                    Swal.fire('خطأ', 'حدث خطأ أثناء تحديث العناصر الفرعية: ' + (xhr.responseJSON?.message || 'خطأ غير معروف'), 'error');
                                    // Still reload the current tab
                                    const activeTab = $('.tab-pane.active').attr('id');
                                    loadTabContent(activeTab);
                                }
                            });
                        } else {
                            // Update only the current task - PUT method
                            const putData = new FormData();
                            putData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                            putData.append('_method', 'PUT');
                            putData.append('sprint_id', response.sprint.id);

                            $.ajax({
                                url: '/tasks/' + taskId,
                                type: 'POST',
                                data: putData,
                                processData: false,
                                contentType: false,
                                success: (response) => {
                                    if (response.success) {
                                        Swal.fire('نجاح', 'تم إنشاء Sprint وتعيينه للمهمة', 'success');
                                        // Reload the current tab
                                        const activeTab = $('.tab-pane.active').attr('id');
                                        loadTabContent(activeTab);
                                    }
                                },
                                error: () => {
                                    Swal.fire('خطأ', 'حدث خطأ أثناء تحديث Sprint', 'error');
                                }
                            });
                        }
                    } else {
                        Swal.fire('خطأ', response.message || 'حدث خطأ أثناء إنشاء Sprint', 'error');
                    }
                },
                error: (xhr) => {
                    Swal.fire('خطأ', 'حدث خطأ أثناء إنشاء Sprint: ' + (xhr.responseJSON?.message || 'خطأ غير معروف'), 'error');
                }
            });
        } else {
            Swal.fire('خطأ', 'تعذر الحصول على معلومات المشروع للمهمة', 'error');
        }
    }).fail(() => {
        Swal.fire('خطأ', 'تعذر الحصول على معلومات المهمة', 'خطأ');
    });
}

// Function to recursively update child tasks with the same sprint
function updateChildTasksSprint(parentTaskId, sprintId) {
    // Show loading message
    Swal.fire({
        title: 'جاري تحديث العناصر الفرعية...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Call the backend to update all child tasks using route helper - POST method
    const formData = new FormData();
    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
    if (sprintId) {
        formData.append('sprint_id', sprintId);
    }

    $.ajax({
        url: route('product-backlog.update-children-sprint', { taskId: parentTaskId }),
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: (response) => {
            if (response.success) {
                Swal.fire('نجاح', 'تم تحديث Sprint لجميع العناصر الفرعية', 'success');
                // Reload the current tab
                const activeTab = $('.tab-pane.active').attr('id');
                loadTabContent(activeTab);
            } else {
                Swal.fire('خطأ', response.message || 'حدث خطأ أثناء تحديث العناصر الفرعية', 'error');
                // Still reload the current tab
                const activeTab = $('.tab-pane.active').attr('id');
                loadTabContent(activeTab);
            }
        },
        error: (xhr) => {
            Swal.fire('خطأ', 'حدث خطأ أثناء تحديث العناصر الفرعية: ' + (xhr.responseJSON?.message || 'خطأ غير معروف'), 'error');
            // Still reload the current tab
            const activeTab = $('.tab-pane.active').attr('id');
            loadTabContent(activeTab);
        }
    });
}
/**
 * تحديث دالة loadBacklogOverview لإظهار السبرنت الحالي فقط
 */
function loadBacklogOverview() {
    showSkeletonLoader('#backlogContent');

    clearTimeout(window.backlogOverviewTimeout);
    window.backlogOverviewTimeout = setTimeout(function() {
        const filters = {
            user_id: $('#backlogFilterUser').val(),
            priority: $('#backlogFilterPriority').val(),
            status: $('#backlogFilterStatus').val()
        };

        $.ajax({
            url: '/api/backlog/sprints-and-tasks',
            method: 'GET',
            data: filters,
            cache: false,
            success: (response) => {
                console.log('Backlog API response:', response); // Debug log
                if (response.success) {
                    renderBacklogOverview(response.sprints, response.backlogTasks, response.currentSprint);
                } else {
                    showError('#backlogContent', 'Backlog Overview');
                }
            },
            error: (xhr, status, error) => {
                console.error('Backlog API error:', { xhr, status, error }); // Debug log
                showError('#backlogContent', 'Backlog Overview');
            }
        });
    }, 300);
}

/**
 * تحديث دالة renderBacklogOverview لعرض السبرنت الحالي فقط
 */
function renderBacklogOverview(sprints, backlogTasks, currentSprint) {
    // Debug information
    console.log('renderBacklogOverview called with:', { sprints, backlogTasks, currentSprint });

    let html = '<div class="fade-in-up">';

    // عرض معلومات السبرنت الحالي
    if (currentSprint) {
        console.log('Current sprint dates:', currentSprint.start_date, currentSprint.end_date);

        // تنسيق التواريخ بالميلادي
        const startDate = currentSprint.start_date ? formatDateGregorian(currentSprint.start_date) : 'غير محدد';
        const endDate = currentSprint.end_date ? formatDateGregorian(currentSprint.end_date) : 'غير محدد';

        // Check if sprint is closed
        const isClosed = currentSprint.status === 'closed';
        
        html += '<div class="alert ' + (isClosed ? 'alert-warning' : 'alert-info') + ' alert-dismissible fade show" role="alert">';
        html += '<h5><i class="feather icon-calendar"></i> ' + (isClosed ? 'السبرنت المغلق: ' : 'السبرنت الحالي: ') + currentSprint.name + '</h5>';
        html += '<div class="row mt-2">';
        html += '<div class="col-md-6">';
        html += '<p class="mb-1"><strong>تاريخ البداية:</strong> ' + startDate + '</p>';
        html += '</div>';
        html += '<div class="col-md-6">';
        html += '<p class="mb-1"><strong>تاريخ النهاية:</strong> ' + endDate + '</p>';
        html += '</div>';
        html += '</div>';
        if (currentSprint.goal) {
            html += '<p class="mb-1 mt-2"><strong>الهدف:</strong> ' + currentSprint.goal + '</p>';
        }
        if (isClosed) {
            html += '<p class="mb-1 mt-2"><strong>الحالة:</strong> <span class="badge badge-warning">مغلق</span></p>';
            html += '<button class="btn btn-sm btn-success mt-2" onclick="reopenSprint(' + currentSprint.id + ')" title="إعادة فتح السبرنت"><i class="feather icon-rotate-ccw"></i> إعادة الفتح</button>';
        }
        html += '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
        html += '<span aria-hidden="true">&times;</span>';
        html += '</button>';
        html += '</div>';
        
        // If sprint is closed, show a message and don't display tasks
        if (isClosed) {
            html += '<div class="card mb-4">';
            html += '<div class="card-body text-center py-5">';
            html += '<i class="feather icon-lock text-warning" style="font-size: 3rem;"></i>';
            html += '<h4 class="mt-3">السبرنت مغلق</h4>';
            html += '<p class="text-muted">هذا السبرنت مغلق. لإظهار المهام المرتبطة به، يرجى إعادة فتحه.</p>';
            html += '<button class="btn btn-success" onclick="reopenSprint(' + currentSprint.id + ')"><i class="feather icon-rotate-ccw"></i> إعادة فتح السبرنت</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>'; // Close fade-in div
            $('#backlogContent').html(html);
            return;
        }

        // عرض User Stories المجدولة في السبرنت الحالي
        const currentSprintStories = sprints.find(s => s.id === currentSprint.id);
        let scheduledStories = [];
        let unscheduledStories = [];

        if (currentSprintStories && currentSprintStories.tasks) {
            scheduledStories = currentSprintStories.tasks.filter(t => t.type === 'user_story' && t.is_scheduled === true);
            // Only get unscheduled stories that are part of the current sprint
            unscheduledStories = currentSprintStories.tasks.filter(t => t.type === 'user_story' && t.is_scheduled === false);
        }

        // Always include backlog tasks (unscheduled user stories not in any sprint)
        if (backlogTasks && backlogTasks.length > 0) {
            // Add backlog tasks to unscheduled stories
            unscheduledStories = unscheduledStories.concat(backlogTasks);
        }

        // عرض User Stories المجدولة في السبرنت الحالي مع إمكانية الطي والتوسيع
        if (scheduledStories.length > 0) {
            html += '<div class="section-header mt-4">';
            html += '<h4><i class="feather icon-check-circle text-success"></i> User Stories المجدولة</h4>';
            html += '<button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#scheduledStoriesCollapse" aria-expanded="true" aria-controls="scheduledStoriesCollapse">';
            html += '<i class="feather icon-chevron-up"></i> توسيع/طي';
            html += '</button>';
            html += '<span class="badge badge-success mr-2">' + scheduledStories.length + ' قصة</span>';
            html += '</div>';
            html += '<div class="collapse show" id="scheduledStoriesCollapse">';
            html += '<div class="card mb-4 sprint-card">';
            html += '<div class="card-body p-0">';
            html += renderUserStoriesTable(scheduledStories, true);
            html += '</div>';
            html += '</div>';
            html += '</div>'; // Close collapse div
        }

        // عرض User Stories غير المجدولة في السبرنت الحالي مع إمكانية الطي والتوسيع
        if (unscheduledStories.length > 0) {
            html += '<div class="section-header mt-4">';
            html += '<h4><i class="feather icon-alert-circle text-warning"></i> User Stories غير المجدولة</h4>';
            html += '<button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#unscheduledStoriesCollapse" aria-expanded="true" aria-controls="unscheduledStoriesCollapse">';
            html += '<i class="feather icon-chevron-up"></i> توسيع/طي';
            html += '</button>';
            html += '<span class="badge badge-warning">' + unscheduledStories.length + ' قصة</span>';
            html += '</div>';
            html += '<div class="collapse show" id="unscheduledStoriesCollapse">';
            html += '<div class="card mb-4 sprint-card">';
            html += '<div class="card-body p-0">';
            html += renderUserStoriesTable(unscheduledStories, false);
            html += '</div>';
            html += '</div>';
            html += '</div>'; // Close collapse div
        }
    } else {
        // لا يوجد سبرنت حالي
        // Still show unscheduled user stories (backlog tasks)
        let unscheduledStories = [];
        if (backlogTasks && backlogTasks.length > 0) {
            unscheduledStories = backlogTasks;
        }

        html += '<div class="card mb-4">';
        html += '<div class="card-body text-center py-5">';
        html += '<i class="feather icon-alert-triangle text-warning" style="font-size: 3rem;"></i>';
        html += '<h4 class="mt-3">لا يوجد سبرنت نشط حالياً</h4>';
        html += '<p class="text-muted">يرجى تفعيل سبرنت لعرض المهام المجدولة</p>';
        html += '</div>';
        html += '</div>';

        // عرض User Stories غير المجدولة
        if (unscheduledStories.length > 0) {
            html += '<div class="section-header mt-4">';
            html += '<h4><i class="feather icon-alert-circle text-warning"></i> User Stories غير المجدولة</h4>';
            html += '<button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#unscheduledStoriesCollapse" aria-expanded="true" aria-controls="unscheduledStoriesCollapse">';
            html += '<i class="feather icon-chevron-up"></i> توسيع/طي';
            html += '</button>';
            html += '<span class="badge badge-warning">' + unscheduledStories.length + ' قصة</span>';
            html += '</div>';
            html += '<div class="collapse show" id="unscheduledStoriesCollapse">';
            html += '<div class="card mb-4 sprint-card">';
            html += '<div class="card-body p-0">';
            html += renderUserStoriesTable(unscheduledStories, false);
            html += '</div>';
            html += '</div>';
            html += '</div>'; // Close collapse div
        }
    }

    html += '</div>'; // Close fade-in div
    $('#backlogContent').html(html);
}

/**
 * دالة مساعدة لعرض جدول User Stories
 */
function renderUserStoriesTable(stories, isScheduled) {
    let html = '<div class="table-responsive">';
    html += '<table class="table table-striped mb-0">';
    html += '<thead class="thead-light">';
    html += '<tr>';
    html += '<th width="35%">User Story</th>';
    html += '<th width="15%">الأولوية</th>';
    html += '<th width="15%">الحالة</th>';
    html += '<th width="15%">الجدولة</th>';
    html += '<th width="15%">المستخدمون</th>';
    html += '<th width="10%">الإجراءات</th>';
    html += '</tr>';
    html += '</thead>';
    html += '<tbody>';

    stories.forEach(function(story) {
        const rowClass = isScheduled ? 'user-story-scheduled' : 'user-story-unscheduled';

        html += '<tr class="' + rowClass + '">';
        html += '<td class="font-weight-bold">' + story.title + '</td>';
        html += '<td>' + getPriorityBadge(story.priority) + '</td>';
        html += '<td>' + getStatusBadge(story.status) + '</td>';

        // حالة الجدولة
        html += '<td>';
        html += '<span class="schedule-badge">';
        html += '<i class="feather icon-' + (isScheduled ? 'check-circle text-success' : 'alert-circle text-warning') + ' schedule-icon"></i> ';
        html += isScheduled ? 'مجدولة' : 'غير مجدولة';
        html += '</span>';
        html += '</td>';

        html += '<td>' + getUsersBadges(story.assigned_users) + '</td>';
        html += '<td>';
        html += '<button class="btn btn-sm btn-outline-primary" onclick="viewStoryDetails(' + story.id + ')" title="عرض التفاصيل">';
        html += '<i class="feather icon-eye"></i>';
        html += '</button>';
        html += '</td>';
        html += '</tr>';
    });

    html += '</tbody>';
    html += '</table>';
    html += '</div>';

    return html;
}

/**
 * دالة لتنسيق التاريخ بالميلادي
 */
function formatDateGregorian(dateString) {
    if (!dateString) return 'غير محدد';

    // Log the date string for debugging
    console.log('Formatting date:', dateString);

    const date = new Date(dateString);
    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'long'
    };

    // تنسيق التاريخ بالإنجليزية ثم ترجمة الأيام والأشهر
    const formatted = date.toLocaleDateString('en-US', options);

    // Log the formatted date for debugging
    console.log('Formatted date:', formatted);

    // ترجمة أسماء الأيام
    const daysMap = {
        'Sunday': 'الأحد',
        'Monday': 'الإثنين',
        'Tuesday': 'الثلاثاء',
        'Wednesday': 'الأربعاء',
        'Thursday': 'الخميس',
        'Friday': 'الجمعة',
        'Saturday': 'السبت'
    };

    // ترجمة أسماء الأشهر
    const monthsMap = {
        'January': 'يناير',
        'February': 'فبراير',
        'March': 'مارس',
        'April': 'أبريل',
        'May': 'مايو',
        'June': 'يونيو',
        'July': 'يوليو',
        'August': 'أغسطس',
        'September': 'سبتمبر',
        'October': 'أكتوبر',
        'November': 'نوفمبر',
        'December': 'ديسمبر'
    };

    let arabicDate = formatted;

    // استبدال أسماء الأيام
    Object.keys(daysMap).forEach(day => {
        arabicDate = arabicDate.replace(day, daysMap[day]);
    });

    // استبدال أسماء الأشهر
    Object.keys(monthsMap).forEach(month => {
        arabicDate = arabicDate.replace(month, monthsMap[month]);
    });

    // Log the final result for debugging
    console.log('Arabic date:', arabicDate);

    return arabicDate;
}

/**
 * دالة لعرض تفاصيل User Story
 */
function viewStoryDetails(storyId) {
    $.ajax({
        url: '/tasks/' + storyId,
        method: 'GET',
        success: function(response) {
            if (response.success && response.task) {
                showStoryDetailsModal(response.task);
            }
        },
        error: function() {
            showToast('error', 'حدث خطأ في جلب تفاصيل القصة');
        }
    });
}

/**
 * عرض modal لتفاصيل User Story
 */
function showStoryDetailsModal(story) {
    const modalHtml = `
        <div class="modal fade" id="storyDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">${story.title}</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>الحالة:</strong> ${getStatusBadge(story.status)}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>الأولوية:</strong> ${getPriorityBadge(story.priority)}</p>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6">
                                <p><strong>حالة الجدولة:</strong> ${story.is_scheduled ? '<span class="badge badge-success">مجدولة</span>' : '<span class="badge badge-warning">غير مجدولة</span>'}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>نسبة الإنجاز:</strong> ${story.completion_percentage || 0}%</p>
                            </div>
                        </div>
                        ${story.description ? `<div class="mt-3"><strong>الوصف:</strong><p>${story.description}</p></div>` : ''}
                        ${story.assigned_users && story.assigned_users.length > 0 ? `
                            <div class="mt-3">
                                <strong>المستخدمون المكلفون:</strong>
                                <div class="mt-2">${getUsersBadges(story.assigned_users)}</div>
                            </div>
                        ` : ''}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">إغلاق</button>
                        <button type="button" class="btn btn-primary" onclick="editStory(${story.id})">تعديل</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // إزالة modal قديم إن وجد
    $('#storyDetailsModal').remove();

    // إضافة modal جديد
    $('body').append(modalHtml);
    $('#storyDetailsModal').modal('show');
}

// تحديث دالة filterBacklog
function filterBacklog() {
    clearTimeout(window.backlogFilterTimeout);
    window.backlogFilterTimeout = setTimeout(function() {
        loadBacklogOverview();
    }, 300);
}
