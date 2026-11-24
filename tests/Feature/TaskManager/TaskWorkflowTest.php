<?php

namespace Tests\Feature\TaskManager;

use App\Helpers\TaskSettingsHelper;
use App\Models\Sprint;
use App\Models\Task;
use App\Models\TaskSettings;
use App\Support\TaskManager\TaskVocabulary;
use Illuminate\Support\Carbon;

/**
 * الحالة وتوابعها، والمفردات، والسبرنت.
 */
class TaskWorkflowTest extends TaskManagerTestCase
{
    public function test_status_climbs_the_whole_hierarchy_and_stamps_the_completion_date(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $epic = $this->task($project, ['type' => 'epic']);
        $story = $this->task($project, ['type' => 'user_story', 'parent_task_id' => $epic->id]);
        $first = $this->task($project, ['type' => 'sub_task', 'parent_task_id' => $story->id]);
        $second = $this->task($project, ['type' => 'sub_task', 'parent_task_id' => $story->id]);

        $this->actingAs($owner)->patchJson("/tasks/{$first->id}/status", ['status' => 'completed'])->assertOk();
        $this->assertSame('not_started', $story->fresh()->status, 'أخٌ لم يكتمل — الأب لا يتبع');

        $this->actingAs($owner)->patchJson("/tasks/{$second->id}/status", ['status' => 'completed'])->assertOk();

        $this->assertSame('completed', $story->fresh()->status);
        // مستويان لا واحد: كان الصعود يقف عند الأب المباشر
        $this->assertSame('completed', $epic->fresh()->status);
        $this->assertSame(now()->toDateString(), Carbon::parse($second->fresh()->completed_date)->toDateString());

        // والتراجع عن الاكتمال يمحو تاريخه
        $this->actingAs($owner)->patchJson("/tasks/{$second->id}/status", ['status' => 'in_progress'])->assertOk();
        $this->assertNull($second->fresh()->completed_date);
    }

    public function test_board_vocabulary_matches_what_the_columns_accept(): void
    {
        $this->assertSame(['not_started', 'in_progress', 'completed', 'overdue'], array_column(TaskSettingsHelper::getStatuses(), 'id'));
        $this->assertContains('urgent', array_column(TaskSettingsHelper::getPriorities(), 'id'));

        // بندٌ من الشاشة القديمة بمعرّفٍ لا يقبله العمود يُهمل، وتخصيصُ اسمِ معرّفٍ قائمٍ يُحترم
        TaskSettings::setSetting(null, 'statuses', [
            ['id' => 'blocked', 'name' => 'معطّل', 'color' => '#000000'],
            ['id' => 'completed', 'name' => 'منجزة', 'color' => '#4CAF50'],
        ], 'json', '', true);
        \Illuminate\Support\Facades\Cache::flush();

        $statuses = array_column(TaskSettingsHelper::getStatuses(), 'name', 'id');

        $this->assertArrayNotHasKey('blocked', $statuses);
        $this->assertSame('منجزة', $statuses['completed']);
        $this->assertSame(TaskVocabulary::statusIds(), array_keys($statuses));
    }

    public function test_global_settings_customise_an_existing_status_and_refuse_a_new_one(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/task-settings/statuses', ['id' => 'in_progress', 'name' => 'جارٍ', 'color' => '#123456'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post('/task-settings/statuses', ['id' => 'in_review', 'name' => 'مراجعة', 'color' => '#123456'])
            ->assertSessionHasErrors('id');

        $this->assertSame('جارٍ', array_column(TaskSettingsHelper::getGlobalStatuses(), 'name', 'id')['in_progress']);
    }

    public function test_task_is_created_with_the_dates_the_user_entered(): void
    {
        $owner = $this->member();
        $project = $this->project($owner, ['management_type' => 'manual']);

        $this->actingAs($owner)->postJson('/tasks', [
            'type' => 'task',
            'title' => 'مهمّة بتاريخ',
            'priority' => 'urgent',
            'project_id' => $project->id,
            'start_date' => '2026-03-01',
            'due_date' => '2026-03-20',
        ])->assertOk();

        $task = Task::where('title', 'مهمّة بتاريخ')->firstOrFail();
        $this->assertSame('2026-03-01', Carbon::parse($task->start_date)->toDateString());
        $this->assertSame('2026-03-20', Carbon::parse($task->due_date)->toDateString());
        $this->assertSame('عاجلة', $task->priority_label);
    }

    public function test_task_cannot_join_a_sprint_of_another_project(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $foreignSprint = $this->sprint($this->project($owner));

        $this->actingAs($owner)->postJson('/tasks', [
            'type' => 'component',
            'title' => 'مكوّن',
            'priority' => 'medium',
            'project_id' => $project->id,
            'sprint_id' => $foreignSprint->id,
        ])->assertStatus(422)->assertJsonValidationErrors('sprint_id');
    }

    public function test_moving_a_story_to_another_epic_recounts_both_epics(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $component = $this->task($project, ['type' => 'component']);
        $from = $this->task($project, ['type' => 'epic', 'parent_task_id' => $component->id, 'estimated_hours' => 8]);
        $to = $this->task($project, ['type' => 'epic', 'parent_task_id' => $component->id]);
        $story = $this->task($project, ['type' => 'user_story', 'parent_task_id' => $from->id, 'estimated_hours' => 8]);

        $this->actingAs($owner)->putJson("/tasks/{$story->id}", [
            'title' => $story->title,
            'priority' => 'medium',
            'status' => 'not_started',
            'parent_task_id' => $to->id,
            'estimated_hours' => 5,
        ])->assertOk();

        $this->assertEquals(0, $from->fresh()->estimated_hours);
        $this->assertEquals(5, $to->fresh()->estimated_hours);
    }

    public function test_only_one_active_sprint_per_project(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $this->sprint($project, ['status' => 'active']);
        $planned = $this->sprint($project);

        $this->actingAs($owner)->patchJson("/sprints/{$planned->id}/status", ['status' => 'active'])->assertStatus(422);
        $this->assertSame('planned', $planned->fresh()->status);

        $this->actingAs($owner)->postJson('/sprints', [
            'project_id' => $project->id, 'name' => 'ثانٍ', 'start_date' => '2026-02-01', 'end_date' => '2026-02-14', 'status' => 'active',
        ])->assertStatus(422);

        // ومشروعٌ آخر لا يتأثّر
        $this->actingAs($owner)->postJson('/sprints', [
            'project_id' => $this->project($owner)->id, 'name' => 'في مشروعٍ آخر', 'start_date' => '2026-02-01', 'end_date' => '2026-02-14', 'status' => 'active',
        ])->assertOk();
    }

    public function test_tasks_join_and_leave_a_sprint_and_the_tree_follows(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $sprint = $this->sprint($project);
        $story = $this->task($project);
        $child = $this->task($project, ['type' => 'sub_task', 'parent_task_id' => $story->id]);
        $foreign = $this->task($this->project($owner));

        $this->actingAs($owner)->postJson("/sprints/{$sprint->id}/tasks", ['task_id' => $foreign->id])->assertStatus(422);

        $this->actingAs($owner)
            ->postJson("/tasks/{$story->id}/assign-sprint-with-inheritance", ['sprint_id' => $sprint->id])
            ->assertOk();

        $this->assertSame($sprint->id, $story->fresh()->sprint_id);
        $this->assertSame($sprint->id, $child->fresh()->sprint_id);

        $this->actingAs($owner)->deleteJson("/sprints/{$sprint->id}/tasks/{$story->id}")->assertOk();
        $this->assertNull($story->fresh()->sprint_id);

        // حذف السبرنت يفكّ ما بقي فيه ولا يحذف المهامّ
        $this->actingAs($owner)->deleteJson("/sprints/{$sprint->id}")->assertOk();
        $this->assertNull($child->fresh()->sprint_id);
    }

    public function test_current_sprint_is_the_active_one_not_the_newest(): void
    {
        $project = $this->project($this->member());
        $active = $this->sprint($project, ['status' => 'active']);
        $this->sprint($project, ['start_date' => '2026-06-01', 'end_date' => '2026-06-14']);

        $this->assertSame($active->id, Sprint::currentFor($project->id)->id);
    }

    public function test_sprint_user_stories_endpoint_exists(): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $sprint = $this->sprint($project);
        $story = $this->task($project, ['sprint_id' => $sprint->id]);
        $this->task($project, ['sprint_id' => $sprint->id, 'type' => 'bug']);

        $this->actingAs($owner)->getJson("/sprints/{$sprint->id}/user-stories")
            ->assertOk()
            ->assertJsonCount(1, 'tasks')
            ->assertJsonPath('tasks.0.id', $story->id);
    }

    public function test_closing_through_the_status_route_needs_the_close_permission(): void
    {
        $user = $this->userWith(array_values(array_diff(self::MEMBER_PERMISSIONS, ['tasks.sprints.close'])));
        $sprint = $this->sprint($this->project($user));

        $this->actingAs($user)->patchJson("/sprints/{$sprint->id}/status", ['status' => 'closed'])->assertForbidden();
        $this->assertSame('planned', $sprint->fresh()->status);
    }

    public function test_overdue_command_marks_only_unfinished_tasks_past_their_due_date(): void
    {
        $project = $this->project($this->member());
        $late = $this->task($project, ['due_date' => now()->subDays(2)->toDateString()]);
        $done = $this->task($project, ['due_date' => now()->subDays(2)->toDateString(), 'status' => 'completed']);
        $future = $this->task($project, ['due_date' => now()->addDays(2)->toDateString()]);

        $this->artisan('tasks:mark-overdue', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('not_started', $late->fresh()->status);

        $this->artisan('tasks:mark-overdue')->assertSuccessful();

        $this->assertSame('overdue', $late->fresh()->status);
        $this->assertSame('completed', $done->fresh()->status);
        $this->assertSame('not_started', $future->fresh()->status);
    }

    public function test_assignment_notifies_the_assignee(): void
    {
        $owner = $this->member();
        $project = $this->project($owner, ['management_type' => 'manual']);
        $assignee = $this->member();
        $this->join($project, $assignee);

        $this->actingAs($owner)->postJson('/tasks', [
            'type' => 'task', 'title' => 'مهمّة مُسندة', 'priority' => 'low',
            'project_id' => $project->id, 'assigned_users' => [$assignee->id],
        ])->assertOk();

        $this->assertDatabaseHas('notifications', ['receiver_id' => $assignee->id, 'type' => 'task']);
        $this->assertDatabaseMissing('notifications', ['receiver_id' => $owner->id, 'type' => 'task']);
    }
}
