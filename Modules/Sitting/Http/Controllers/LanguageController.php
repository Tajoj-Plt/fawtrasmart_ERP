<?php

namespace Modules\Sitting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AccountSetting;
use App\Models\SystemLanguage;
use App\Services\ErrorLogService;
use App\Support\OperationLog;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Sitting\Http\Requests\StoreLanguageRequest;
use Modules\Sitting\Http\Requests\UpdateLanguageRequest;
use Modules\Sitting\Repositories\LanguageRepository;
use Modules\Sitting\Services\LanguageService;
use Modules\Sitting\Services\TranslationEditorService;

class LanguageController extends Controller
{
    protected $languageService;

    protected $languageRepository;

    protected $errorLog;

    protected $translationEditor;

    public function __construct(
        LanguageService $languageService,
        LanguageRepository $languageRepository,
        ErrorLogService $errorLog,
        TranslationEditorService $translationEditor
    ) {
        $this->languageService = $languageService;
        $this->languageRepository = $languageRepository;
        $this->errorLog = $errorLog;
        $this->translationEditor = $translationEditor;
    }

    /**
     * قائمة اللغات مع البحث والفلترة والإحصائيات.
     */
    public function index(Request $request)
    {
        checkLanguagePermission('view');

        try {
            $account_setting = AccountSetting::where('user_id', auth()->id())->first();

            $system_languages = $this->languageRepository->paginateFiltered(
                $request->only(['search', 'status', 'direction']),
                (int) $request->get('per_page', 12)
            );

            $stats = $this->languageRepository->stats();

            // الاسمان معاً: القديم يحافظ على أي قالب يستخدمه، والجديد يطابق تسمية النظام المرجعي
            return view('sitting::languages.index', [
                'system_languages' => $system_languages,
                'languages' => $system_languages,
                'account_setting' => $account_setting,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Language', 'index');

            return $this->fail($e, 'languages_index');
        }
    }

    /**
     * نموذج إضافة لغة جديدة.
     */
    public function create()
    {
        checkLanguagePermission('create');

        try {
            return view('sitting::languages.create');
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Language', 'create');

            return $this->fail($e, 'languages_create');
        }
    }

    /**
     * حفظ لغة جديدة.
     */
    public function store(StoreLanguageRequest $request)
    {
        try {
            $language = $this->languageService->store(
                Arr::except($request->validated(), $this->nonColumnInputs()),
                $this->uploadedTranslationFiles($request),
                $request->input('translations_payload')
            );

            return $this->redirectToIndex()
                ->with('success', __('languages.messages.created', ['name' => $language->name]));
        } catch (\Exception $e) {
            $this->errorLog->log($e);

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * عرض تفاصيل لغة.
     */
    public function show($id)
    {
        checkLanguagePermission('view');

        try {
            $language = $this->languageRepository->find($id);

            $translationFiles = collect($language->translationFiles())
                ->map(fn ($path) => ['path' => $path, 'exists' => File::exists(base_path($path))])
                ->all();

            // الاسم القديم يبقى لأي قالب يقرأه
            $translationExists = $language->translation_file
                && File::exists(base_path($language->translation_file));

            $translationGroups = $this->translationEditor->groups($language);

            return view('sitting::languages.show', compact(
                'language',
                'translationExists',
                'translationFiles',
                'translationGroups'
            ));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Language', 'show');

            return $this->fail($e, 'languages_show');
        }
    }

    /**
     * نموذج تعديل لغة — ويعيد JSON للطلبات غير المتصفّحة (AJAX/API).
     */
    public function edit(Request $request, $id)
    {
        checkLanguagePermission('edit');

        try {
            $language = $this->languageRepository->find($id);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json($language);
            }

            // مفاتيح الترجمة تُحرَّر من شاشة التفاصيل، فلا داعي لتفكيك ملفات اللغة هنا
            return view('sitting::languages.edit', compact('language'));
        } catch (\Exception $e) {
            // كان الاستثناء يُبتلع: يُبلَّغ المستخدم بخطأ ولا يبقى منه أثر
            OperationLog::failure($e, 'Sitting.Language', 'edit');

            return $this->fail($e, 'languages_edit');
        }
    }

    /**
     * تحديث بيانات لغة.
     */
    public function update(UpdateLanguageRequest $request, $id)
    {
        try {
            $language = $this->languageService->update(
                $id,
                Arr::except($request->validated(), $this->nonColumnInputs()),
                $this->uploadedTranslationFiles($request),
                $request->input('translations_payload')
            );

            return $this->redirectToIndex()
                ->with('success', __('languages.messages.updated', ['name' => $language->name]));
        } catch (\Exception $e) {
            $this->errorLog->log($e);

            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * حذف لغة (غير الافتراضية).
     */
    public function destroy(Request $request, $id)
    {
        checkLanguagePermission('delete');

        try {
            $this->languageService->delete($id);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => __('languages.messages.deleted')]);
            }

            return $this->redirectToIndex()->with('success', __('languages.messages.deleted'));
        } catch (\Exception $e) {
            $this->errorLog->log($e);

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * تفعيل / تعطيل لغة.
     *
     * يقبل المعرّف من المسار (النمط الجديد) أو من جسم الطلب (النمط القديم
     * المستخدم في قائمة الخيارات) حتى لا ينكسر أي نموذج قائم.
     */
    public function toggleActive(Request $request, $id = null)
    {
        checkLanguagePermission('activate');

        try {
            $languageId = $id ?? $request->input('id');
            $language = $this->languageService->toggleActive($languageId);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => __('languages.messages.status_changed'),
                    'is_active' => $language->is_active,
                ]);
            }

            return redirect()->back()->with('success', __('languages.messages.status_changed'));
        } catch (\Exception $e) {
            $this->errorLog->log($e);

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /** اسم بديل يطابق تسمية النظام المرجعي. */
    public function toggleStatus(Request $request, $id = null)
    {
        return $this->toggleActive($request, $id);
    }

    /**
     * تعيين لغة كافتراضية للنظام.
     */
    public function setDefault(Request $request, $id)
    {
        checkLanguagePermission('set_default');

        try {
            $language = $this->languageService->setDefault($id);

            return redirect()->back()
                ->with('success', __('languages.messages.set_default', ['name' => $language->name]));
        } catch (\Exception $e) {
            $this->errorLog->log($e);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * حفظ اللغة الافتراضية من شاشة إعدادات الحساب (المسار القديم).
     *
     * يمرّ عبر نفس منطق setDefault حتى لا يتفرّع سلوك اللغة الافتراضية.
     */
    public function storeAccountLanguage(Request $request)
    {
        checkLanguagePermission('set_default');

        try {
            $request->validate([
                'language' => 'required|string|exists:system_languages,code',
            ]);

            $language = SystemLanguage::where('code', $request->language)->firstOrFail();

            $this->languageService->setDefault($language->id);

            return redirect()->back()->with('success', __('languages.messages.default_saved'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->errorLog->log($e);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * مفاتيح مجموعة ترجمة واحدة — يُحمّلها الجدول عند فتح تبويبها.
     *
     * التحميل عند الطلب لا مع الصفحة: ملفات لغة واحدة قد تبلغ مئات
     * الكيلوبايتات، وإرسالها كلها في HTML يثقل الشاشة بلا داعٍ.
     */
    public function showTranslationGroup(Request $request, $id)
    {
        checkLanguagePermission('view');

        try {
            $data = $request->validate([
                'group' => ['required', 'string', 'max:50'],
                'type' => ['required', 'in:php,json'],
            ]);

            $language = $this->languageRepository->find($id);

            $group = $this->translationEditor->readGroup(
                $this->translationEditor->safeCode($language->code),
                $data['type'] === 'json' ? TranslationEditorService::JSON_GROUP : $data['group'],
                $data['type']
            );

            return response()->json([
                'name' => $group['name'],
                'label' => $group['label'],
                'type' => $group['type'],
                'relative' => $group['relative'],
                'error' => $group['error'],
                'entries' => (object) $group['entries'],
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->errorLog->log($e);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * حفظ تعديلات جدول مفاتيح الترجمة من شاشة التفاصيل.
     */
    public function updateTranslations(Request $request, $id)
    {
        checkLanguagePermission('edit');

        try {
            // فارغة تعني «لا تعديل» فتُقابَل برسالة واضحة بدل خطأ تحقق
            $request->validate([
                'translations_payload' => ['nullable', 'string', 'max:4000000'],
            ]);

            $language = $this->languageRepository->find($id);
            $saved = $this->translationEditor->applyPayload($language, $request->input('translations_payload'));

            $message = $saved === []
                ? __('languages.translations.nothing_changed')
                : __('languages.translations.saved', ['count' => count($saved)]);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => $message, 'files' => $saved]);
            }

            return redirect()->route('languages.show', $language->id)->with('success', $message);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->errorLog->log($e);

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * حذف ملف مجموعة ترجمة كاملة.
     */
    public function destroyTranslationGroup(Request $request, $id)
    {
        checkLanguagePermission('edit');

        try {
            $data = $request->validate([
                'group' => ['required', 'string', 'max:50'],
                'type' => ['required', 'in:php,json'],
            ]);

            $language = $this->languageRepository->find($id);
            $deleted = $this->translationEditor->deleteGroup($language, $data['group'], $data['type']);

            return redirect()->route('languages.show', $language->id)->with(
                $deleted ? 'success' : 'error',
                $deleted ? __('languages.translations.group_deleted') : __('languages.file_missing')
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->errorLog->log($e);

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /* ==================== Helpers ==================== */

    /**
     * المدخلات التي لا تقابل أعمدة في الجدول — تُعالَج بمسار خاص.
     *
     * @return array<int, string>
     */
    protected function nonColumnInputs(): array
    {
        return ['translation_file', 'translation_files', 'translations_payload'];
    }

    /**
     * ملفات الترجمة المرفوعة: الحقل الجديد المتعدّد مع الحقل المفرد القديم.
     *
     * @return array<int, UploadedFile>
     */
    protected function uploadedTranslationFiles(Request $request): array
    {
        $files = $request->file('translation_files', []);
        $files = is_array($files) ? $files : [$files];

        if ($request->hasFile('translation_file')) {
            $files[] = $request->file('translation_file');
        }

        return array_values(array_filter($files));
    }

    /**
     * شاشة القائمة تُخدم من مسار الإعدادات القديم، فيُوجَّه إليه دائماً.
     */
    protected function redirectToIndex()
    {
        return redirect()->route('SittingAccount.language');
    }

    protected function fail(\Exception $e, string $context)
    {
        $this->errorLog->log($e);
        Log::error("LanguageController [{$context}]: ".$e->getMessage());

        return redirect()->route('SittingAccount.language')
            ->with('error', __('languages.messages.unexpected_error'));
    }
}
