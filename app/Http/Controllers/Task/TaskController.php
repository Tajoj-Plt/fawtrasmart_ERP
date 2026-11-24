<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    { 
        // جلب جميع الموظفين
        return view('task.index');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:component,epic,user_story,sub_task,bug',
            'project_id' => 'nullable|exists:projects,id',
            'parent_task_id' => 'nullable|exists:tasks,id',
            'description' => 'nullable|string',
            'status' => 'required|in:not_started,in_progress,completed,overdue',
            'priority' => 'required|in:low,medium,high,urgent',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'budget' => 'nullable|numeric',
            'estimated_hours' => 'nullable|numeric',
            'completion_percentage' => 'nullable|integer|min:0|max:100',
        ]);

        $task = Task::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ المهمة بنجاح',
            'task' => $task
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        
        $request->validate([
            'status' => 'required|in:not_started,in_progress,completed,overdue',
            'completion_percentage' => 'nullable|integer|min:0|max:100'
        ]);

        $task->status = $request->status;
        if ($request->has('completion_percentage')) {
            $task->completion_percentage = $request->completion_percentage;
        }
        $task->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الحالة بنجاح',
            'task' => $task
        ]);
    }

    public function updateProgress(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        
        $request->validate([
            'completion_percentage' => 'required|integer|min:0|max:100'
        ]);

        $task->completion_percentage = $request->completion_percentage;
        
        // تحديث الحالة تلقائياً بناءً على نسبة الإنجاز
        if ($task->completion_percentage == 100) {
            $task->status = 'completed';
        } elseif ($task->completion_percentage > 0) {
            $task->status = 'in_progress';
        } else {
            $task->status = 'not_started';
        }
        
        $task->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث نسبة الإنجاز بنجاح',
            'task' => $task
        ]);
    }

    public function show($id)
    {
        $task = Task::with(['project', 'parent', 'children', 'creator', 'assignedUsers'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'task' => $task
        ]);
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المهمة بنجاح'
        ]);
    }

    public function getTasks(Request $request)
    {
        $query = Task::with(['project', 'assignedUsers']);

        // تطبيق الفلاتر
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->priority) {
            $query->where('priority', $request->priority);
        }

        $tasks = $query->get();

        return response()->json([
            'success' => true,
            'tasks' => $tasks
        ]);
    }

    public function getTasksByProject($projectId)
    {
        $tasks = Task::where('project_id', $projectId)
            ->whereNull('parent_task_id')
            ->get();

        return response()->json([
            'success' => true,
            'tasks' => $tasks
        ]);
    }
}