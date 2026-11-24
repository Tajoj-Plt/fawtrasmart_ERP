<?php

namespace App\Services\Fawtra;

use Modules\Installer\Services\EnvironmentManager;

/**
 * بيانات اعتماد الربط — قراءةً وكتابةً على `.env`.
 *
 * **لماذا `.env` لا قاعدة البيانات؟** لأن الحقل الحسّاس هنا سرٌّ يُوقَّع به،
 * لا بيانٌ يُعرض. وضعُه في جدول يعني نسخَه مع كل نسخة احتياطية وكل تصدير
 * بيانات وكل مزامنة إلى بيئة اختبار — ودليل الواجهة ينصّ أن السرّ لا يغادر
 * الخادم. و`.env` مستثنى من git ومن النسخ الاحتياطي أصلاً.
 *
 * وما يُعرض في الشاشات هو `client_id` والدومين — أمّا السرّ فلا يُعاد عرضه
 * بعد إدخاله، تماماً كما تفعل لوحة فوترة نفسها.
 */
class FawtraCredentials
{
    public function __construct(protected EnvironmentManager $environment) {}

    /** هل اكتمل الربط؟ الأربعة كلها لازمة — أي نقصٍ يجعل التوقيع مستحيلاً. */
    public function configured(): bool
    {
        foreach (['base_url', 'client_id', 'secret', 'domain'] as $key) {
            if (blank(config('fawtra.'.$key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * ما يصحّ عرضه في الشاشات — بلا السرّ.
     *
     * @return array{base_url:?string, client_id:?string, domain:?string, secret_set:bool}
     */
    public function public(): array
    {
        return [
            'base_url' => config('fawtra.base_url'),
            'client_id' => config('fawtra.client_id'),
            'domain' => config('fawtra.domain'),
            'secret_set' => filled(config('fawtra.secret')),
        ];
    }

    /**
     * كتابة بيانات الاعتماد على `.env` وتحديث إعداد الطلب الحالي.
     *
     * @param  array{base_url:string, client_id:string, secret:string, domain:string}  $data
     */
    /**
     * ضبط بيانات الربط في إعداد هذا الطلب فقط — بلا كتابةٍ في `.env`.
     *
     * يستعملها المُركّب: يربط ويُزامن في نبضاته، ثم تُكتب المفاتيح مع بقية
     * الإعدادات في كتابةٍ واحدة أخيرة. الكتابة أثناء التنفيذ تُعيد تشغيل
     * خادم التطوير فتضيع نبضةٌ في منتصف العمل.
     */
    public function applyRuntime(array $data): void
    {
        config([
            'fawtra.base_url' => self::normaliseBaseUrl($data['base_url'] ?? config('fawtra.base_url')),
            'fawtra.client_id' => trim($data['client_id'] ?? ''),
            'fawtra.secret' => trim($data['secret'] ?? ''),
            'fawtra.domain' => trim($data['domain'] ?? ''),
        ]);
    }

    public function store(array $data): void
    {
        // العنوان لا يأتي من المستخدم: يُقرأ من الإعداد ويُطبَّع. وما يُمرَّر
        // في `$data` يُقبل فقط في التطوير المحلّي حين يُوجَّه إلى لوحة أخرى
        $baseUrl = self::normaliseBaseUrl($data['base_url'] ?? config('fawtra.base_url'));

        $this->environment->write([
            'FAWTRA_BASE_URL' => $baseUrl,
            'FAWTRA_CLIENT_ID' => trim($data['client_id']),
            'FAWTRA_SECRET' => trim($data['secret']),
            'FAWTRA_DOMAIN' => trim($data['domain']),
        ]);

        // `EnvironmentManager::write` يزامن مفاتيح التطبيق المعروفة له وحدها،
        // ومفاتيح فوترة ليست منها — فتُضبط هنا كي يعمل الربط في هذا الطلب
        // نفسه بدل انتظار الطلب التالي
        config([
            'fawtra.base_url' => $baseUrl,
            'fawtra.client_id' => trim($data['client_id']),
            'fawtra.secret' => trim($data['secret']),
            'fawtra.domain' => trim($data['domain']),
        ]);
    }

    /**
     * قطع الربط — تُفرَّغ المفاتيح ولا تُحذف أسطرها.
     *
     * الإبقاء على الأسطر فارغةً يجعل من يفتح `.env` يعرف أن الربط ممكن
     * وأنه غير مضبوط، بدل أن يظنّ الميزة غير موجودة.
     */
    public function forget(): void
    {
        $this->environment->write([
            'FAWTRA_BASE_URL' => '',
            'FAWTRA_CLIENT_ID' => '',
            'FAWTRA_SECRET' => '',
            'FAWTRA_DOMAIN' => '',
        ]);

        config([
            'fawtra.base_url' => null,
            'fawtra.client_id' => null,
            'fawtra.secret' => null,
            'fawtra.domain' => null,
        ]);
    }

    /**
     * قواعد التحقّق — مشتركة بين المعالج وشاشة الإعدادات فلا تفترقان.
     *
     * صيغة المفاتيح مأخوذة من الوثيقة: `fwck_…` للمعرّف و`fwsk_…` للسرّ.
     * والتحقّق من البادئة يمسك الخطأ الأشيع — لصقُ أحدهما مكان الآخر —
     * قبل أن يذهب إلى الخادم ويعود بـ`401` لا تقول شيئاً.
     */
    public static function rules(): array
    {
        return [
            // لا `base_url` هنا: عنوان اللوحة يُضبط في `.env` ويُوزَّع مع
            // النظام. طلبُه من المستخدم فتح باب الخطأ الذي وقع فعلاً —
            // إدخاله متضمّناً بادئة الواجهة فينتج 404 لا يدلّ على سببه
            'client_id' => ['required', 'string', 'max:120', 'regex:/^fwck_[A-Za-z0-9]+$/'],
            'secret' => ['required', 'string', 'min:20', 'max:190', 'regex:/^fwsk_[A-Za-z0-9]+$/'],
            'domain' => ['required', 'string', 'max:190', 'regex:/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*$/'],
        ];
    }

    public static function ruleMessages(): array
    {
        return [
            'client_id.regex' => 'معرّف العميل يبدأ بـ fwck_ — تأكّد أنك لم تلصق السرّ مكانه.',
            'secret.regex' => 'السرّ يبدأ بـ fwsk_ — تأكّد أنك لم تلصق معرّف العميل مكانه.',
            'domain.regex' => 'الدومين اسمُ مضيف بلا بروتوكول ولا مسار، مثل erp.example.com',
        ];
    }

    public static function attributes(): array
    {
        return [
            'client_id' => 'معرّف العميل',
            'secret' => 'السرّ',
            'domain' => 'دومين نظامك',
        ];
    }

    /**
     * تطبيع عنوان اللوحة — إزالة بادئة الواجهة إن أُلحقت به.
     *
     * **هذا إصلاح لعطلٍ وقع فعلاً.** أُدخل العنوان
     * `https://dashboard.fawtrasmart.com/api/integration/v1/` فبنى العميل
     * المسار `…/api/integration/v1/api/integration/v1/ping` — وردّت اللوحة
     * 404 برسالة «خطأ غير معروف» لا تدلّ على السبب.
     *
     * والتطبيع لا يُغني عن نقل العنوان إلى `.env`، لكنه يحمي من كتابته
     * يدوياً بالصيغة الخاطئة — وهي صيغة معقولة لمن يقرأ الوثيقة، إذ إن
     * أمثلتها تعرض المسار كاملاً.
     */
    public static function normaliseBaseUrl(?string $url): string
    {
        $url = rtrim(trim((string) $url), '/');

        if ($url === '') {
            return '';
        }

        $prefix = rtrim((string) config('fawtra.prefix', '/api/integration/v1/'), '/');

        // تُزال البادئة مهما تكرّرت: لصقٌ مرتين ليس أبعد من لصقٍ مرّة
        while ($prefix !== '' && str_ends_with(strtolower($url), strtolower($prefix))) {
            $url = rtrim(substr($url, 0, -strlen($prefix)), '/');
        }

        return $url;
    }
}
