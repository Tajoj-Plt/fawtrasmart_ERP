<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTaskModalTitle">تعديل المهمة</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editTaskForm">
                @method('PUT')
                @csrf
                <input type="hidden" id="editTaskId" name="id">
                <!-- Hidden field for parent task ID -->
                <input type="hidden" id="editParentTaskId" name="parent_task_id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editTaskType">نوع المهمة</label>
                        <select class="form-control" id="editTaskType" name="type" required disabled>
                            <option value="">اختر نوع المهمة</option>
                            @if(($managementType ?? 'scrum') === 'manual')
                                <option value="task">مهمة</option>
                                <option value="sub_task">مهمة فرعية</option>
                            @else
                                <option value="component">مكون</option>
                                <option value="epic">إبيك</option>
                                <option value="user_story">قصة مستخدم</option>
                                <option value="sub_task">مهمة فرعية</option>
                                <option value="bug">خطأ برمجي</option>
                            @endif
                        </select>
                    </div>

                    @if(($managementType ?? 'scrum') === 'manual')
                        <!-- Task Fields (إدارة يدوية) -->
                        <div class="edit-plain-task-fields edit-task-fields" style="display: none;">
                            <div class="form-group">
                                <label for="editProjectId">المشروع</label>
                                <select class="form-control" id="editProjectId" name="project_id">
                                    <option value="">اختر المشروع</option>
                                    @foreach($projects as $project)
                                        @if($project->management_type === 'manual')
                                            <option value="{{ $project->id }}">{{ $project->title }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @else
                        <!-- Component Fields (سكرم) -->
                        <div class="edit-component-fields edit-task-fields" style="display: none;">
                            <div class="form-group">
                                <label for="editProjectId">المشروع</label>
                                <select class="form-control" id="editProjectId" name="project_id">
                                    <option value="">اختر المشروع</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}">{{ $project->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <!-- Epic Fields -->
                    <div class="edit-epic-fields edit-task-fields" style="display: none;">
                        <div class="form-group">
                            <label for="editEpicParentId">المكون</label>
                            <select class="form-control parent-select" id="editEpicParentId">
                                <option value="">اختر المكون</option>
                                @foreach($components ?? [] as $component)
                                    <option value="{{ $component->id }}">
                                        {{ $component->title }}
                                        @if($component->project)
                                            ({{ $component->project->title }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- User Story Fields -->
                    <div class="edit-user-story-fields edit-task-fields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editStoryParentId">الإبيك</label>
                                    <select class="form-control parent-select" id="editStoryParentId">
                                        <option value="">اختر الإبيك</option>
                                        @foreach($epics ?? [] as $epic)
                                            <option value="{{ $epic->id }}">
                                                {{ $epic->title }}
                                                @if($epic->parent)
                                                    ({{ $epic->parent->title }} - {{ $epic->parent->project->title ?? '' }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editEstimatedHours">الوقت المقدر (بالساعات)</label>
                                    <input type="number" class="form-control" id="editEstimatedHours" name="estimated_hours" min="0" step="0.5">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sub Task Fields -->
                    <div class="edit-sub-task-fields edit-task-fields" style="display: none;">
                        <div class="form-group">
                            @if(($managementType ?? 'scrum') === 'manual')
                                <label for="editSubTaskParentId">المهمة</label>
                                <select class="form-control parent-select" id="editSubTaskParentId">
                                    <option value="">اختر المهمة</option>
                                    @foreach($manualParentTasks ?? [] as $parentTask)
                                        <option value="{{ $parentTask->id }}">{{ $parentTask->title }}</option>
                                    @endforeach
                                </select>
                            @else
                            <label for="editSubTaskParentId">قصة المستخدم</label>
                            <select class="form-control parent-select" id="editSubTaskParentId">
                                <option value="">اختر قصة المستخدم</option>
                                @foreach($userStories ?? [] as $story)
                                    <option value="{{ $story->id }}">
                                        {{ $story->title }}
                                        @if($story->parent)
                                            ({{ $story->parent->title }}
                                            @if($story->parent->parent)
                                                - {{ $story->parent->parent->title }}
                                            @endif)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @endif
                        </div>
                    </div>

                    <!-- Bug Fields -->
                    <div class="edit-bug-fields edit-task-fields" style="display: none;">
                        <div class="form-group">
                            <label for="editBugParentId">المكون</label>
                            <select class="form-control parent-select" id="editBugParentId">
                                <option value="">اختر المكون</option>
                                @foreach($components ?? [] as $component)
                                    <option value="{{ $component->id }}">
                                        {{ $component->title }}
                                        @if($component->project)
                                            ({{ $component->project->title }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Common Fields -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editTitle">العنوان</label>
                                <input type="text" class="form-control" id="editTitle" name="title" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editPriority">الأولوية</label>
                                <select class="form-control" id="editPriority" name="priority" required>
                                    <option value="low">منخفضة</option>
                                    <option value="medium">متوسطة</option>
                                    <option value="high">عالية</option>
                                    <option value="urgent">عاجلة</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editStatus">الحالة</label>
                                <select class="form-control" id="editStatus" name="status" required>
                                    <option value="not_started">لم تبدأ</option>
                                    <option value="in_progress">قيد التنفيذ</option>
                                    <option value="completed">مكتمل</option>
                                    <option value="overdue">متأخر</option>
                                </select>
                            </div>
                        </div>
                        @if(($managementType ?? 'scrum') !== 'manual')
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="editSprintId">السبرنت</label>
                                    <select class="form-control" id="editSprintId" name="sprint_id">
                                        <option value="">اختر السبرنت</option>
                                        @foreach($sprints as $sprint)
                                            <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editStartDate">تاريخ البداية</label>
                                <input type="date" class="form-control" id="editStartDate" name="start_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="editDueDate">تاريخ الاستحقاق</label>
                                <input type="date" class="form-control" id="editDueDate" name="due_date">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editDescription">الوصف</label>
                        <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="editAssignedUsers">المستخدمون المخصصون</label>
                        <select multiple class="form-control" id="editAssignedUsers" name="assigned_users[]">
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تحديث</button>
                </div>
            </form>
        </div>
    </div>
</div>