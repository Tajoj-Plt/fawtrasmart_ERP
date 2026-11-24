<!-- تضمين مكتبة SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Create Task Modal -->
<div class="modal fade" id="createTaskModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <!-- CSRF Token -->
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <div class="modal-header">
                <h5 class="modal-title" id="createTaskModalTitle">إضافة مهمة جديدة</h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="createTaskForm" action="/tasks" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="taskType">نوع المهمة</label>
                        <select class="form-control" id="taskType" name="type" required>
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
                        <div class="plain-task-fields task-fields" style="display: none;">
                            <div class="form-group">
                                <label for="plainTaskProjectId">المشروع</label>
                                <select class="form-control" id="plainTaskProjectId" name="project_id">
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
                        <div class="component-fields task-fields" style="display: none;">
                            <div class="form-group">
                                <label for="project_id">المشروع</label>
                                <select class="form-control" id="projectId" name="project_id">
                                    <option value="">اختر المشروع</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}">{{ $project->title }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif

                    <!-- Epic Fields -->
                    <div class="epic-fields task-fields" style="display: none;">
                        <div class="form-group">
                            <label for="epicParentId">المكون</label>
                            <select class="form-control parent-select" id="epicParentId">
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
                    <div class="user-story-fields task-fields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="storyParentId">الإبيك</label>
                                    <select class="form-control parent-select" id="storyParentId">
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
                                    <label for="estimatedHours">الوقت المقدر (بالساعات)</label>
                                    <input type="number" class="form-control" id="estimatedHours" name="estimated_hours" min="0" step="0.5">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sub Task Fields -->
                    <div class="sub-task-fields task-fields" style="display: none;">
                        <div class="form-group">
                            @if(($managementType ?? 'scrum') === 'manual')
                                <label for="subTaskParentId">المهمة</label>
                                <select class="form-control parent-select" id="subTaskParentId">
                                    <option value="">اختر المهمة</option>
                                    @foreach($manualParentTasks ?? [] as $parentTask)
                                        <option value="{{ $parentTask->id }}">{{ $parentTask->title }}</option>
                                    @endforeach
                                </select>
                            @else
                                <label for="subTaskParentId">قصة المستخدم</label>
                                <select class="form-control parent-select" id="subTaskParentId">
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
                    <div class="bug-fields task-fields" style="display: none;">
                        <div class="form-group">
                            <label for="bugParentId">المكون</label>
                            <select class="form-control parent-select" id="bugParentId">
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

                    <!-- Hidden field for parent task ID -->
                    <input type="hidden" id="parentTaskId" name="parent_task_id" value="">

                    <!-- Common Fields -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="title">العنوان</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="priority">الأولوية</label>
                                <select class="form-control" id="priority" name="priority" required>
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
                                <label for="description">الوصف</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        @if(($managementType ?? 'scrum') !== 'manual')
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="sprintId">السبرنت</label>
                                    <select class="form-control" id="sprintId" name="sprint_id">
                                        <option value="">اختر السبرنت</option>
                                        @foreach($sprints as $sprint)
                                            <option value="{{ $sprint->id }}">{{ $sprint->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- اختياريّان: الفارغ يبدأ اليوم ويمتدّ أسبوعاً — وكانا يُفرضان
                         كذلك دائماً بلا حقلٍ يغيّرهما إلا بعد الحفظ --}}
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createStartDate">تاريخ البداية</label>
                                <input type="date" class="form-control" id="createStartDate" name="start_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="createDueDate">تاريخ الاستحقاق</label>
                                <input type="date" class="form-control" id="createDueDate" name="due_date">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Include the task dropdowns JavaScript -->
<script src="{{ asset('modules/taskmanager/js/task-dropdowns.js') }}"></script>