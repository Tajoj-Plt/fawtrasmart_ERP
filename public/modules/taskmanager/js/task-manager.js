$(document).ready(function() {
    // تفعيل Select2
    $('.select2').select2();

    // تحميل الإبيكات عند اختيار المكون
    $('#filterComponent').on('change', function() {
        var componentId = $(this).val();
        loadEpicsByComponent(componentId);

        // عند اختيار مكون، نقوم بتحديث الفلاتر تلقائياً إذا كان نوع المهمة هو user_story
        if (componentId && $('#filterType').val() === 'user_story') {
            applyFilters();
        }
    });

    // تحميل الإبيكات حسب المكون
    function loadEpicsByComponent(componentId) {
        $('#filterEpic').empty().append('<option value="">جميع الإبيكات</option>');

        if (!componentId) return;

        $.ajax({
            url: '/tasks/epics/' + componentId,
            method: 'GET',
            success: function(response) {
                response.forEach(function(epic) {
                    $('#filterEpic').append(
                        $('<option></option>')
                            .val(epic.id)
                            .text(epic.title)
                    );
                });

                // تطبيق الفلاتر تلقائياً إذا كان هناك إبيك محدد
                if ($('#filterEpic').val()) {
                    applyFilters();
                }
            },
            error: function() {
                console.log('Error loading epics');
            }
        });
    }

    // تحميل قصص المستخدم عند اختيار الإبيك
    $('#filterEpic').on('change', function() {
        // عند اختيار إبيك، نقوم بتحديث الفلاتر تلقائياً
        if ($(this).val()) {
            applyFilters();
        }
    });

    // تحميل قصص المستخدم عند اختيار المكون
    $('#filterComponent').on('change', function() {
        // عند اختيار مكون، نقوم بتحديث الفلاتر تلقائياً إذا كان نوع المهمة هو user_story
        if ($(this).val() && $('#filterType').val() === 'user_story') {
            applyFilters();
        }
    });

    // تحميل Epics عند تغيير المشروع أو النوع
    $('#project_id, #type').on('change', function() {
        var projectId = $('#project_id').val();
        var taskType = $('#type').val();

        if ((taskType === 'epic' || taskType === 'user_story' || taskType === 'bug') && projectId) {
            loadEpics(projectId);
        }
    });

    // تحميل Epics
    function loadEpics(projectId) {
        $.ajax({
            url: '/api/backlog/epics',
            method: 'GET',
            data: {
                project_id: projectId
            },
            success: function(response) {
                if (response.success) {
                    var epicSelect = $('#parent_task_id');
                    epicSelect.empty().append('<option value="">لا يوجد (مهمة رئيسية)</option>');

                    response.tasks.forEach(function(epic) {
                        epicSelect.append($('<option></option>').val(epic.id).text(epic.title));
                    });
                }
            },
            error: function() {
                console.log('Error loading epics');
            }
        });
    }

    // حفظ المهمة
    $('#taskForm').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        var taskId = $('#task_id').val();
        var url = taskId ? '/tasks/' + taskId : '/tasks/store';
        var method = taskId ? 'PUT' : 'POST';

        $('#saveTaskBtn').prop('disabled', true).html('<i class="feather icon-loader"></i> جاري الحفظ...');

        $.ajax({
            url: url,
            method: method,
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire('نجاح!', response.message, 'success');
                    $('#taskModal').modal('hide');

                    // إعادة تطبيق الفلاتر الحالية بدلاً من reload
                    if (window.taskFilter) {
                        window.taskFilter.applyFilters();
                    }
                }
            },
            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var errors = xhr.responseJSON.errors;
                    var errorHtml = '<ul>';
                    $.each(errors, function(key, value) {
                        errorHtml += '<li>' + value[0] + '</li>';
                    });
                    errorHtml += '</ul>';
                    $('#formErrors').html(errorHtml).show();
                } else {
                    Swal.fire('خطأ!', 'حدث خطأ أثناء حفظ المهمة', 'error');
                }
            },
            complete: function() {
                $('#saveTaskBtn').prop('disabled', false).html('<i class="feather icon-save"></i> حفظ');
            }
        });
    });

    // إغلاق Modal عند الضغط على زر الإلغاء أو الإغلاق
    $('.btn-secondary[data-dismiss="modal"]').on('click', function() {
        $('#taskModal').modal('hide');
    });

    $('.modal .close').on('click', function() {
        $('#taskModal').modal('hide');
    });

    // تطبيق الفلاتر تلقائياً عند تغيير أي فلتر سريع (بلا حاجة لزر بحث)
    $(document).on('change', '.board-quick-filter', function() {
        applyFilters();
    });

    // إعادة تعيين الفلاتر
    $('#btnResetFilters').on('click', function() {
        resetFilters();
    });

    // تصفية سريعة بالمعيَّن (النقر على الصورة الرمزية في شريط الفلترة)
    $(document).on('click', '.assignee-quick-filter', function() {
        var userId = $(this).data('user-id');
        var alreadyActive = $(this).hasClass('active');

        $('.assignee-quick-filter').removeClass('active');

        if (!alreadyActive) {
            $(this).addClass('active');
            $('#filterAssignee').val(userId).trigger('change.select2');
        } else {
            $('#filterAssignee').val('').trigger('change.select2');
        }

        applyFilters();
    });

    // تطبيق الفلاتر عند تحميل الصفحة — فقط في الصفحات التي تحمل شريط الفلاتر
    // (لوحة المهام الرئيسية). لوحة السبرنت المفردة تعرض لوحتها الجاهزة من
    // الخادم مباشرة ولا تملك عناصر الفلاتر هذه، فاستدعاء applyFilters() هنا
    // كان يستبدل محتواها ببيانات غير مرتبطة بالسبرنت المعروض.
    if ($('#filterProject').length) {
        applyFilters();
    }

    // تفعيل السحب والإفلات
    initDragAndDrop();

    // إغلاق السبرنت
    $('#closeSprintBtn').on('click', function() {
        var sprintId = $(this).data('sprint-id');
        closeSprint(sprintId);
    });
});

// تطبيق الفلاتر
function applyFilters() {
    var filters = {
        project_id: $('#filterProject').val(),
        sprint_id: $('#filterSprint').val(),
        type: $('#filterType').val(),
        component_id: $('#filterComponent').val(),
        epic_id: $('#filterEpic').val(),
        status: $('#filterStatus').val(),
        priority: $('#filterPriority').val(),
        user_id: $('#filterAssignee').val()
    };

    $.ajax({
        url: '/tasks/manager-data',
        method: 'GET',
        data: filters,
        success: function(response) {
            if (response.success) {
                // تحديث لوحة المهام
                updateTaskBoard(response.html);

                // تحديث معلومات السبرنت الحالي
                updateCurrentSprintInfo(response.currentSprint);
            } else {
                Swal.fire('خطأ!', response.message || 'حدث خطأ أثناء تطبيق الفلاتر', 'error');
            }
        },
        error: function(xhr) {
            console.log(xhr);
            Swal.fire('خطأ!', 'حدث خطأ أثناء تطبيق الفلاتر: ' + xhr.responseText, 'error');
        }
    });
}

// إعادة تعيين الفلاتر
function resetFilters() {
    $('#filterProject, #filterSprint, #filterType, #filterComponent, #filterEpic, #filterStatus, #filterPriority, #filterAssignee').val('');
    $('.assignee-quick-filter').removeClass('active');
    // مسح قائمة الإبيكات أيضاً
    $('#filterEpic').empty().append('<option value="">جميع الإبيكات</option>');
    applyFilters();
}

// تحديث لوحة المهام
function updateTaskBoard(html) {
    // تحديث محتوى لوحة المهام
    $('.task-board').html(html);

    // إعادة تفعيل السحب والإفلات
    initDragAndDrop();
}

// تحديث معلومات السبرنت الحالي
function updateCurrentSprintInfo(sprint) {
    if (sprint) {
        $('.current-sprint-name').text(sprint.name);
        $('.current-sprint-status').text(sprint.status);
        $('.current-sprint-start-date').text(sprint.start_date);
        $('.current-sprint-end-date').text(sprint.end_date);
        $('.current-sprint-goal').text(sprint.goal || 'لم يتم تحديد هدف');
    }
}

// عرض إشعار خفيف (toast) بدلاً من نافذة منبثقة تعطّل تدفق العمل عند كل سحبة
function showToast(icon, message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icon,
        title: message,
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });
}

var _boardSortables = [];

// تفعيل السحب والإفلات عبر SortableJS: بين الأعمدة (تغيير الحالة) وداخل
// العمود نفسه (إعادة ترتيب) — بواجهة سلسة (تمرير تلقائي، فجوة مكان الإفلات،
// ودعم اللمس) بدل واجهة HTML5 drag native الأساسية.
function initDragAndDrop() {
    _boardSortables.forEach(function(instance) { instance.destroy(); });
    _boardSortables = [];

    if (typeof Sortable === 'undefined') {
        return;
    }

    document.querySelectorAll('.task-column').forEach(function(columnEl) {
        var instance = new Sortable(columnEl, {
            group: 'task-board',
            animation: 180,
            ghostClass: 'task-card-ghost',
            chosenClass: 'task-card-chosen',
            dragClass: 'task-card-dragging',
            filter: 'select, button, a, .task-status-select',
            preventOnFilter: false,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            onEnd: function(evt) {
                var moved = evt.from !== evt.to;
                saveColumnOrder(evt.to, moved);
                if (moved) {
                    saveColumnOrder(evt.from, false);
                }
                updateTaskCounts();
            }
        });
        _boardSortables.push(instance);
    });

    // تغيير الحالة عبر القائمة المنسدلة (بديل السحب — للوصول السهل وشاشات اللمس)
    $('.task-status-select').off('change').on('change', function() {
        var select = $(this);
        var taskId = select.data('task-id');
        var newStatus = select.val();
        var taskCard = select.closest('.task-card');
        var targetColumn = $('.task-column[data-status="' + newStatus + '"]');

        if (targetColumn.length) {
            targetColumn.find('.empty-column-hint').remove();
            targetColumn.append(taskCard);
            saveColumnOrder(targetColumn.get(0), true);
            updateTaskCounts();
        }
    });
}

// حفظ ترتيب/حالة كل بطاقات عمود واحد بعد إفلات — يُستخدم لكل من العمود
// الهدف (تغيّرت حالته أو لا) وعمود المصدر عند النقل بين عمودين
function saveColumnOrder(columnEl, announce) {
    var column = $(columnEl);
    var status = column.data('status');
    var taskIds = column.find('.task-card').map(function() {
        return $(this).data('task-id');
    }).get();

    if (taskIds.length === 0) {
        return;
    }

    column.find('.task-card').addClass('updating');

    $.ajax({
        url: '/tasks/reorder',
        method: 'PATCH',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            status: status,
            task_ids: taskIds
        },
        success: function(response) {
            column.find('.task-card').removeClass('updating');
            column.find('.task-status-select').val(status);

            if (response.success) {
                if (announce) {
                    column.find('.task-card').first().addClass('just-moved');
                    setTimeout(function() {
                        column.find('.task-card').removeClass('just-moved');
                    }, 1000);
                    showToast('success', 'تم تحديث حالة المهمة بنجاح');
                }
            } else if (announce) {
                showToast('error', response.message);
                applyFilters();
            }
        },
        error: function(xhr) {
            column.find('.task-card').removeClass('updating');
            var message = (xhr.responseJSON && xhr.responseJSON.message) || 'حدث خطأ أثناء حفظ الترتيب';
            showToast('error', message);
            // إعادة المزامنة مع الخادم بعد فشل الحفظ
            applyFilters();
        }
    });
}

// تحديث العدادات وتلميح "لا توجد مهام" لكل عمود بعد النقل الفوري في الواجهة
function updateTaskCounts() {
    $('.task-column').each(function() {
        var column = $(this);
        var status = column.data('status');
        var count = column.find('.task-card').length;
        var badge = $('.task-count-' + status);
        badge.text(count).addClass('count-updated');
        setTimeout(function() { badge.removeClass('count-updated'); }, 500);

        if (count === 0) {
            if (column.find('.empty-column-hint').length === 0) {
                column.append('<div class="empty-column-hint">لا توجد مهام هنا</div>');
            }
        } else {
            column.find('.empty-column-hint').remove();
        }
    });
}

// إغلاق السبرنت
function closeSprint(sprintId) {
    Swal.fire({
        title: 'هل أنت متأكد؟',
        text: "هل تريد فعلاً إغلاق هذا السبرنت؟",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'نعم، أغلقه!',
        cancelButtonText: 'إلغاء'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '/sprints/' + sprintId + '/close',
                method: 'PATCH',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('تم!', 'تم إغلاق السبرنت بنجاح.', 'success');
                        // تحديث حالة السبرنت في الواجهة
                        $('#currentSprintStatus').val('closed');
                        $('#closeSprintBtn').prop('disabled', true).text('السبرنت مغلق');
                    } else {
                        Swal.fire('خطأ!', response.message, 'error');
                    }
                },
                error: function(xhr) {
                    console.log(xhr);
                    Swal.fire('خطأ!', 'حدث خطأ أثناء إغلاق السبرنت: ' + xhr.responseText, 'error');
                }
            });
        }
    });
}
