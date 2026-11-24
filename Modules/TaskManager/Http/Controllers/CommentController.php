<?php

namespace Modules\TaskManager\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Support\OperationLog;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\TaskManager\Services\ProjectAccess;
use Modules\TaskManager\Services\TaskNotifier;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CommentController extends Controller
{
    /** وحدة السجلّ — بها يربط `logs`/`event_logs` أسطرَ هذه الشاشة. */
    private const LOG_MODULE = 'TaskManager.Comment';

    public function __construct()
    {
        /*
            التعليق مِلك كاتبه: الحارس هنا قائمةٌ بديلة
            (`edit_own|edit_any`)، والتمييزُ داخل الإجراء نفسه — من يملك
            `edit_own` وحدها يمرّ ثم يُردّ إن كان التعليق لغيره.
        */
        $this->middleware('permission.role:tasks.comments.view', ['only' => ['getTaskComments']]);
        $this->middleware('permission.role:tasks.comments.create', ['only' => ['store']]);
        $this->middleware('permission.role:tasks.comments.edit_own|tasks.comments.edit_any', ['only' => ['update']]);
        $this->middleware('permission.role:tasks.comments.delete_own|tasks.comments.delete_any', ['only' => ['destroy']]);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:2000|min:3',
                'commentable_type' => 'required|string',
                'commentable_id' => 'required|integer',
                'parent_id' => 'nullable|exists:comments,id',
            ], [
                'content.required' => 'محتوى التعليق مطلوب',
                'content.min' => 'التعليق يجب أن يكون على الأقل 3 أحرف',
                'content.max' => 'التعليق لا يمكن أن يتجاوز 2000 حرف',
                'commentable_type.required' => 'نوع العنصر المعلق عليه مطلوب',
                'commentable_id.required' => 'معرف العنصر المعلق عليه مطلوب',
                'parent_id.exists' => 'التعليق الأصلي غير موجود',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'البيانات المدخلة غير صحيحة',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // التحقق من صحة نوع العنصر المعلق عليه
            // `App\Models\Post` كان في القائمة ولا نموذجَ بهذا الاسم — فكل
            // تعليقٍ عليه خطأ 500 لا رفضٌ مفهوم
            $allowedTypes = [Project::class, Task::class];

            if (! in_array($request->commentable_type, $allowedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'نوع العنصر المعلق عليه غير مدعوم',
                ], 400);
            }

            // التحقق من وجود العنصر المعلق عليه
            $commentableClass = $request->commentable_type;
            $commentable = $commentableClass::find($request->commentable_id);

            if (! $commentable) {
                return response()->json([
                    'success' => false,
                    'message' => 'العنصر المعلق عليه غير موجود',
                ], 404);
            }

            // التعليق كتابةٌ في المشروع: لأعضائه لا لكل من يملك الصلاحية،
            // وليس للمراقب
            if (! $this->canAccess($commentable, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا تملك التعليق في هذا المشروع',
                ], 403);
            }

            // التحقق من صحة التعليق الأصلي إذا كان موجوداً
            if ($request->parent_id) {
                $parentComment = Comment::where('id', $request->parent_id)
                    ->where('commentable_type', $request->commentable_type)
                    ->where('commentable_id', $request->commentable_id)
                    ->first();

                if (! $parentComment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'التعليق الأصلي غير صحيح',
                    ], 400);
                }
            }

            // تنظيف محتوى التعليق
            $cleanContent = strip_tags(trim($request->content));

            // إنشاء التعليق
            $comment = Comment::create([
                'user_id' => Auth::id(),
                'commentable_type' => $request->commentable_type,
                'commentable_id' => $request->commentable_id,
                'parent_id' => $request->parent_id,
                'content' => $cleanContent,
            ]);

            // تحميل بيانات المستخدم والردود
            $comment->load([
                'user:id,name,email',
                'replies.user:id,name,email',
            ]);

            // إضافة تاريخ مقروء
            $comment->created_at_human = $comment->created_at->diffForHumans();

            if ($commentable instanceof Task) {
                app(TaskNotifier::class)->commented($commentable, $cleanContent);
            }

            OperationLog::record(
                'commented',
                self::LOG_MODULE,
                $comment,
                sprintf(
                    'عُلّق على **%s #%d**: %s',
                    class_basename($request->commentable_type),
                    (int) $request->commentable_id,
                    Str::limit($cleanContent, 120),
                ),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة التعليق بنجاح',
                'data' => $comment,
            ], 201);

        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            OperationLog::failure($e, self::LOG_MODULE, 'store');

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إضافة التعليق، يرجى المحاولة مرة أخرى',
            ], 500);
        }
    }

    /**
     * حذف تعليق
     */
    public function destroy($id)
    {
        try {
            $comment = Comment::findOrFail($id);

            /*
                كان الشرط `hasRole('admin')` — وهو تابع Spatie لتعدّد
                الأدوار، والمشروع لا يستعمله: الصلاحية فيه تأتي من
                `users.role_id` وحده عبر `hasRolePermission()`
                (انظر [\App\Http\Middleware\CheckPermissionRole]).
                فكان الاستدعاء يرمي أو يعود false دائماً — أي أن «مدير
                يحذف تعليق غيره» لم تكن تعمل أصلاً.
            */
            if (! $this->canAccess($comment->commentable, true)
                || ($comment->user_id !== Auth::id() && ! Auth::user()?->hasRolePermission('tasks.comments.delete_any'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مسموح لك بحذف هذا التعليق',
                ], 403);
            }

            // قبل الحذف — بعده لا نصَّ يُكتب في السطر
            $commentId = (int) $comment->id;
            $excerpt = Str::limit((string) $comment->content, 120);
            $ownComment = $comment->user_id === Auth::id();

            // الردود بلا أصلها لا تظهر في أي قائمة — فتُحذف معه
            $comment->replies()->delete();
            $comment->delete();

            OperationLog::record(
                'deleted',
                self::LOG_MODULE,
                $commentId,
                sprintf('حُذف تعليق%s: %s', $ownComment ? '' : ' لمستخدمٍ آخر', $excerpt),
            );

            return response()->json([
                'success' => true,
                'message' => 'تم حذف التعليق بنجاح',
            ]);

        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error deleting comment: '.$e->getMessage());

            OperationLog::failure($e, self::LOG_MODULE, 'destroy', (int) $id);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في حذف التعليق',
            ], 500);
        }
    }

    /**
     * تحديث تعليق
     */
    public function update(Request $request, $id)
    {
        try {
            $comment = Comment::findOrFail($id);

            // التحقق من الصلاحية — تعليقُه هو، أو `edit_any` لمن يراجع
            // الفريق. وكان الشرط الأول وحده، فلا سبيل لتصحيح تعليقٍ مسيء
            // إلا بحذفه
            if (! $this->canAccess($comment->commentable, true)
                || ($comment->user_id !== Auth::id() && ! Auth::user()?->hasRolePermission('tasks.comments.edit_any'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مسموح لك بتعديل هذا التعليق',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:2000|min:3',
            ], [
                'content.required' => 'محتوى التعليق مطلوب',
                'content.min' => 'التعليق يجب أن يكون على الأقل 3 أحرف',
                'content.max' => 'التعليق لا يمكن أن يتجاوز 2000 حرف',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'البيانات المدخلة غير صحيحة',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $previousContent = (string) $comment->content;

            $comment->update([
                'content' => strip_tags(trim($request->content)),
                'is_edited' => true,
                'edited_at' => now(),
            ]);

            OperationLog::record(
                'updated',
                self::LOG_MODULE,
                $comment,
                sprintf('عُدّل تعليق: %s', Str::limit((string) $comment->content, 120)),
                ['old' => ['content' => $previousContent], 'new' => ['content' => (string) $comment->content]],
            );

            $comment->load('user:id,name,email');
            $comment->created_at_human = $comment->created_at->diffForHumans();

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث التعليق بنجاح',
                'data' => $comment,
            ]);

        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error updating comment: '.$e->getMessage());

            OperationLog::failure($e, self::LOG_MODULE, 'update', (int) $id);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحديث التعليق',
            ], 500);
        }
    }

    public function getTaskComments($taskId)
    {
        try {
            // التحقق من وجود المهمة والصلاحيات
            $task = Task::findOrFail($taskId);

            // التحقق من صلاحية الوصول للمهمة
            if (! $this->canAccess($task, false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مصرح لك بمشاهدة تعليقات هذه المهمة',
                ], 403);
            }

            // جلب التعليقات الأساسية مع الردود
            $comments = Comment::where('commentable_type', 'App\Models\Task')
                ->where('commentable_id', $taskId)
                ->whereNull('parent_id')
                ->with([
                    'user:id,name,email,avatar',
                    'replies' => function ($query) {
                        $query->with('user:id,name,email,avatar')
                            ->orderBy('created_at', 'asc');
                    },
                ])
                ->withCount('replies')
                ->orderBy('created_at', 'desc')
                ->get();

            // تنسيق التواريخ للعرض
            $comments->each(function ($comment) {
                $comment->created_at_human = $comment->created_at->diffForHumans();

                if ($comment->replies) {
                    $comment->replies->each(function ($reply) {
                        $reply->created_at_human = $reply->created_at->diffForHumans();
                    });
                }
            });

            // حساب العدد الإجمالي للتعليقات (تشمل الردود)
            $totalCount = $comments->count() + $comments->sum('replies_count');

            return response()->json([
                'success' => true,
                'data' => $comments,
                'total' => $totalCount,
                'main_comments' => $comments->count(),
                'replies_count' => $comments->sum('replies_count'),
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'المهمة غير موجودة',
            ], 404);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('خطأ في جلب تعليقات المهمة: '.$e->getMessage(), [
                'task_id' => $taskId,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في تحميل التعليقات',
            ], 500);
        }
    }

    /**
     * هل للمستخدم أن يقرأ (أو يكتب) في مشروع العنصر المعلَّق عليه؟
     *
     * كانت `canAccessTask()` تنتهي بـ`hasRole('admin')` — تابع Spatie لتعدّد
     * الأدوار الذي لا يستعمله المشروع — فلا يمرّ منها مديرٌ ليس عضواً.
     * والعضوية الآن من [ProjectAccess] كبقيّة الوحدة.
     */
    private function canAccess($commentable, bool $write): bool
    {
        if ($commentable instanceof Task) {
            return ProjectAccess::canTouchTask($commentable, $write);
        }

        if ($commentable instanceof Project) {
            return $write ? ProjectAccess::canWrite($commentable) : ProjectAccess::canView($commentable);
        }

        // العنصر حُذف وبقي تعليقُه — لصاحبه أن يحذفه ولا شيء غير ذلك
        return false;
    }
}
