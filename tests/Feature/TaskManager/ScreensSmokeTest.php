<?php

namespace Tests\Feature\TaskManager;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * كل شاشةٍ في الوحدة تُفتح لعضو مشروعه بلا خطأ.
 *
 * يقيس القوالب لا المنطق: متغيّرٌ حُذف من المتحكّم وما زال القالب يقرؤه
 * لا يظهر في اختبارات JSON، ويظهر هنا 500.
 */
class ScreensSmokeTest extends TaskManagerTestCase
{
    /** @return array<string, array{0: string}> */
    public static function screens(): array
    {
        return [
            'workspaces' => ['/workspaces'],
            'workspace create' => ['/workspaces/create'],
            'workspace show' => ['/workspaces/{workspace}'],
            'projects' => ['/projects'],
            'project create' => ['/projects/create'],
            'project show' => ['/projects/{project}'],
            'project edit' => ['/projects/{project}/edit'],
            'project board' => ['/projects/{project}/board'],
            'project dashboard' => ['/projects/{project}/dashboard'],
            'project backlog' => ['/projects/{project}/backlog'],
            'project sprints' => ['/projects/{project}/sprints'],
            'project settings' => ['/projects/{project}/settings'],
            'board' => ['/task-manager'],
            'board of project' => ['/task-manager?project_id={project}'],
            'dashboard' => ['/task-dashboard'],
            'backlog' => ['/tasks'],
            'sprints' => ['/sprints'],
            'sprint create' => ['/sprints/create'],
            'sprint show' => ['/sprints/{sprint}'],
            'sprint edit' => ['/sprints/{sprint}/edit'],
            'reports' => ['/task-reports'],
            'report tasks' => ['/task-reports/tasks'],
            'report types' => ['/task-reports/task-types'],
            'report status' => ['/task-reports/task-status'],
            'report priority' => ['/task-reports/task-priority'],
            'report projects' => ['/task-reports/projects'],
            'report completion' => ['/task-reports/project-completion'],
            'report timeline' => ['/task-reports/project-timeline'],
            'report workspaces' => ['/task-reports/workspaces'],
            'report performance' => ['/task-reports/workspace-performance'],
            'global settings' => ['/task-settings'],
        ];
    }

    #[DataProvider('screens')]
    public function test_screen_opens(string $url): void
    {
        $owner = $this->member();
        $project = $this->project($owner);
        $sprint = $this->sprint($project, ['status' => 'active']);
        $epic = $this->task($project, ['type' => 'epic', 'title' => '<script>alert(1)</script>']);
        $story = $this->task($project, ['parent_task_id' => $epic->id, 'sprint_id' => $sprint->id, 'status' => 'completed']);
        $story->assignedUsers()->attach($owner->id);

        // مشروعُ غيره وفيه مهمّة — يجب ألّا تظهر في أي شاشة
        $this->task($this->project($this->member()), ['title' => 'سرٌّ-من-مشروعٍ-آخر']);

        $url = strtr($url, [
            '{workspace}' => $project->workspace_id,
            '{project}' => $project->id,
            '{sprint}' => $sprint->id,
        ]);

        $response = $this->actingAs($owner)->get($url);

        $response->assertOk();
        $response->assertDontSee('سرٌّ-من-مشروعٍ-آخر');
        // العنوان يُعرض مهرَّباً لا وسماً يُنفَّذ
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_board_shows_the_tasks_in_the_columns_the_database_uses(): void
    {
        $owner = $this->member();
        $project = $this->project($owner, ['management_type' => 'manual']);
        $this->task($project, ['type' => 'task', 'title' => 'لم-تبدأ-بعد']);
        $this->task($project, ['type' => 'task', 'title' => 'اكتملت-فعلاً', 'status' => 'completed']);

        $response = $this->actingAs($owner)->get("/projects/{$project->id}/board")->assertOk();

        // كانت الأعمدة todo/in_review/done/blocked — فلا تقع فيها هاتان
        $response->assertSee('data-status="not_started"', false);
        $response->assertSee('data-status="completed"', false);
        $response->assertSee('لم-تبدأ-بعد');
        $response->assertSee('اكتملت-فعلاً');
    }
}
