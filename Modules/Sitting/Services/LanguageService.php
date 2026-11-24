<?php

namespace Modules\Sitting\Services;

use App\Models\AccountSetting;
use App\Models\Log as ModelsLog;
use App\Models\SystemLanguage;
use App\Services\EventLogService;
use App\Support\TranslationFileParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Sitting\Repositories\LanguageRepository;
use RuntimeException;
use Throwable;

class LanguageService
{
    protected $languageRepository;

    protected $eventLog;

    protected $translationEditor;

    public function __construct(
        LanguageRepository $languageRepository,
        EventLogService $eventLog,
        TranslationEditorService $translationEditor
    ) {
        $this->languageRepository = $languageRepository;
        $this->eventLog = $eventLog;
        $this->translationEditor = $translationEditor;
    }

    /**
     * Store a new language with transaction and logging
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $translationFiles  ملف أو أكثر
     * @param  string|null  $translationsPayload  تعديلات جدول المفاتيح (JSON)
     */
    public function store(array $data, $translationFiles = null, ?string $translationsPayload = null)
    {
        return DB::transaction(function () use ($data, $translationFiles, $translationsPayload) {
            $data = $this->normalize($data);

            $stored = $this->storeTranslationFiles($translationFiles, $data['code']);
            if ($stored !== []) {
                $data['translation_files'] = $stored;
                $data['translation_file'] = $stored[0];
            }

            // لغة واحدة افتراضية فقط: تُلغى الصفة عن الباقي قبل الإنشاء
            $wantsDefault = ! empty($data['is_default']);
            if ($wantsDefault) {
                $data['is_active'] = true; // اللغة الافتراضية لا تكون معطّلة
                $this->languageRepository->unsetDefaultExcept();
            }

            $language = $this->languageRepository->create($data);

            $this->translationEditor->applyPayload($language, $translationsPayload);

            if ($wantsDefault) {
                $this->syncDefaultToAccount($language);
            }

            $this->log('تم إضافة لغة جديدة للنظام: '.$language->name, 'language_created', $language);

            return $language;
        });
    }

    /**
     * Update an existing language with transaction and logging
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $translationFiles  ملف أو أكثر
     * @param  string|null  $translationsPayload  تعديلات جدول المفاتيح (JSON)
     */
    public function update($id, array $data, $translationFiles = null, ?string $translationsPayload = null)
    {
        return DB::transaction(function () use ($id, $data, $translationFiles, $translationsPayload) {
            $current = $this->languageRepository->find($id);
            $data = $this->normalize($data);

            $stored = $this->storeTranslationFiles($translationFiles, $data['code']);
            if ($stored !== []) {
                // الملفات المرفوعة سابقاً تبقى مسجّلة: الرفع الجديد إضافة لا استبدال للقائمة
                $data['translation_files'] = array_values(array_unique(array_merge($current->translationFiles(), $stored)));
                $data['translation_file'] = $stored[0];
            }

            $wantsDefault = ! empty($data['is_default']);

            // لا يجوز سحب صفة الافتراضية عن اللغة الحالية دون تعيين بديل
            if ($current->is_default && ! $wantsDefault) {
                throw new \Exception('لا يمكن إلغاء الافتراضية عن هذه اللغة. عيّن لغة أخرى كافتراضية أولاً.');
            }

            if ($wantsDefault) {
                $data['is_active'] = true;
                $this->languageRepository->unsetDefaultExcept($current->id);
            }

            // لا يمكن تعطيل اللغة الافتراضية
            if (array_key_exists('is_active', $data) && ! $data['is_active'] && ($wantsDefault || $current->is_default)) {
                throw new \Exception('لا يمكن إلغاء تفعيل اللغة الافتراضية للنظام.');
            }

            $language = $this->languageRepository->update($id, $data);

            $this->translationEditor->applyPayload($language, $translationsPayload);

            if ($wantsDefault) {
                $this->syncDefaultToAccount($language);
            }

            $this->log('تم تعديل بيانات اللغة: '.$language->name, 'language_updated', $language);

            return $language;
        });
    }

    /**
     * Delete a language safely
     */
    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            $language = $this->languageRepository->find($id);

            if ($language->is_default) {
                throw new \Exception('لا يمكن حذف اللغة الافتراضية للنظام.');
            }

            $name = $language->name;
            $languageId = $language->id;

            $this->languageRepository->delete($id);

            $this->log('تم حذف اللغة من النظام: '.$name, 'language_deleted', null, $languageId);

            return true;
        });
    }

    /**
     * Toggle active status
     */
    public function toggleActive($id)
    {
        $language = $this->languageRepository->find($id);

        if ($language->is_default && $language->is_active) {
            throw new \Exception('لا يمكن إلغاء تفعيل اللغة الافتراضية للنظام.');
        }

        $language = $this->languageRepository->update($id, ['is_active' => ! $language->is_active]);

        $this->log(
            'تم '.($language->is_active ? 'تفعيل' : 'تعطيل').' اللغة: '.$language->name,
            $language->is_active ? 'language_activated' : 'language_deactivated',
            $language
        );

        return $language;
    }

    /**
     * تعيين لغة كافتراضية للنظام مع مزامنة إعدادات الحساب والجلسة.
     */
    public function setDefault($id)
    {
        return DB::transaction(function () use ($id) {
            $language = $this->languageRepository->find($id);

            if (! $language->is_active) {
                throw new \Exception('لا يمكن تعيين لغة غير مفعّلة كلغة افتراضية. فعّلها أولاً.');
            }

            $language = $this->languageRepository->setAsDefault($id);

            $this->syncDefaultToAccount($language);

            $this->log('تم تعيين اللغة الافتراضية للنظام: '.$language->name, 'language_default_changed', $language);

            return $language;
        });
    }

    /* ==================== Helpers ==================== */

    /**
     * توحيد شكل البيانات القادمة من النماذج قبل الحفظ.
     */
    protected function normalize(array $data): array
    {
        if (isset($data['code'])) {
            $data['code'] = strtolower(trim($data['code']));
        }

        // المحلية تتبع الرمز افتراضياً حتى لا تبقى فارغة فيفشل التبديل
        $data['locale'] = ! empty($data['locale'])
            ? strtolower(trim($data['locale']))
            : ($data['code'] ?? null);

        $data['is_active'] = ! empty($data['is_active']);
        $data['is_default'] = ! empty($data['is_default']);
        $data['sort_order'] = isset($data['sort_order']) && $data['sort_order'] !== null
            ? (int) $data['sort_order']
            : 0;

        return $data;
    }

    /**
     * مزامنة اللغة الافتراضية مع إعدادات حساب المستخدم والجلسة،
     * وهو نفس ما تفعله شاشة "حفظ اللغة الافتراضية" حتى لا يتفرّع السلوك.
     */
    protected function syncDefaultToAccount(SystemLanguage $language): void
    {
        if (! auth()->check()) {
            return;
        }

        $setting = AccountSetting::firstOrCreate(['user_id' => auth()->id()]);
        $setting->language = $language->code;
        $setting->save();

        session(['locale' => $language->code]);
        app()->setLocale($language->code);
    }

    /**
     * تخزين ملفات الترجمة المرفوعة — ملف واحد أو عدة ملفات معاً.
     *
     * كل ملف PHP يصير مجموعة مستقلة باسمه، وملفات JSON تُدمج في ملف اللغة
     * الواحد لأن لارافيل لا يقرأ إلا resources/lang/{code}.json.
     *
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return array<int, string> مسارات الملفات المكتوبة
     */
    protected function storeTranslationFiles($files, string $code): array
    {
        $files = array_filter(is_array($files) ? $files : [$files]);

        if ($files === []) {
            return [];
        }

        $stored = [];
        $jsonData = [];

        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $contents = (string) file_get_contents($file->getRealPath());

            // اسم الملف يظهر في الخطأ: مع عدة ملفات لا يكفي أن نقول «الملف غير صالح»
            try {
                $data = TranslationFileParser::parse($contents, $extension);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    __('languages.validation.translation_file_named', ['file' => $file->getClientOriginalName()])
                    .' '.$e->getMessage(), 0, $e
                );
            }

            if ($extension === 'php') {
                // اسم المجموعة من اسم الملف المرفوع بعد تنقيته
                $group = $this->translationEditor->safeGroup(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $stored[] = $this->translationEditor->write($code, $group, 'php', Arr::dot($data), true)['relative'];

                continue;
            }

            // عدة ملفات JSON: الأخير يغلب على المتشابه في المفاتيح
            $jsonData = array_replace($jsonData, $data);
        }

        if ($jsonData !== []) {
            $stored[] = $this->translationEditor->write($code, TranslationEditorService::JSON_GROUP, 'json', $jsonData, true)['relative'];
        }

        return array_values(array_unique(array_filter($stored)));
    }

    /**
     * تسجيل العملية في سجل النظام العام وسجل الأحداث.
     */
    protected function log(string $description, string $action, ?SystemLanguage $language = null, $languageId = null): void
    {
        try {
            ModelsLog::create([
                'type' => 'setting',
                'type_id' => $language?->id ?? $languageId,
                'type_log' => 'log',
                'icon' => 'globe',
                'description' => $description,
                'created_by' => auth()->id(),
                'ip_address' => request()->ip(),
                'route' => request()->path(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Language log failed: '.$e->getMessage());
        }

        try {
            $this->eventLog->log($action, $description);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Language event log failed: '.$e->getMessage());
        }
    }
}
