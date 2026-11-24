<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Illuminate\Validation\ValidationException;

/**
 * مرفقاتُ المستندات — مجلدٌ لكل نوع مستند، واسمٌ وامتدادٌ يولّدهما الخادم.
 *
 * **ما كان**
 *
 * الفاتورة والمرتجع وسند القبض تحفظ المرفق في `public/assets/uploads/`
 * باسمه الأصلي: `time().'_'.$file->getClientOriginalName()`. الفاتورةُ
 * والمرتجعُ بلا قاعدة تحقّقٍ إطلاقاً، والسندُ بقاعدة `mimes` تفحص المحتوى
 * ثمّ يُحفظ الاسمُ كما أرسله المتصفّح. و`.htaccess` في جذر المشروع يُشغّل
 * ملفات `.php` — فملفٌّ اسمُه `x.php` (أو صورةٌ حقيقية أُلحقت بها شيفرة)
 * يصير رابطاً يُنفَّذ على الخادم.
 *
 * **ما صار**
 *
 *   • الامتداد المحفوظ مستخرجٌ من **محتوى** الملف ومقصورٌ على قائمةٍ
 *     مغلقة، والاسمُ معرّفٌ عشوائي — لا شيء ممّا كتبه المستخدم يصل القرص.
 *   • لكل مستند مجلدُه: `sales/invoices`، `sales/returns`، `sales/payments`.
 *   • المجلد الأمّ `assets/uploads` يحمل `.htaccess` يمنع تنفيذ أي سكربت،
 *     ويُكتب عند أوّل رفع إن غاب — فالمرفوعاتُ القديمة في الجذر محروسةٌ معها.
 *
 * العمود يحمل المسار **نسبةً إلى `assets/uploads`**، فالقراءة القديمة
 * `asset('assets/uploads/'.$value)` تعمل للقيم القديمة والجديدة معاً.
 * ومرفقٌ واحد يُحفظ نصّاً، وأكثر من واحد مصفوفةَ JSON — `paths()` تقرأ الشكلين.
 */
final class DocumentAttachments
{
    public const SALES_INVOICE = 'sales/invoices';

    public const SALES_RETURN = 'sales/returns';

    public const SALES_PAYMENT = 'sales/payments';

    /** الامتدادات المقبولة — والمحفوظُ منها ما يدلّ عليه محتوى الملف لا اسمُه. */
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];

    public const MAX_KILOBYTES = 10240;

    public const MAX_FILES = 10;

    private const BASE = 'assets/uploads';

    /** يُكتب في `public/assets/uploads/.htaccess` إن غاب — نسخته في المستودع مطابقة. */
    private const GUARD = <<<'HTACCESS'
# مرفقاتٌ تُقدَّم ملفّاتٍ فقط — لا يُنفَّذ منها شيء.
# يكتبه App\Support\DocumentAttachments إن غاب.
RemoveHandler .php .phtml .phar .pht .phps .php3 .php4 .php5 .php7 .php8 .php81 .php82 .php83
RemoveType .php .phtml .phar .pht .phps .php3 .php4 .php5 .php7 .php8 .php81 .php82 .php83
<FilesMatch "(?i)\.(php[0-9]*|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp|shtml)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

HTACCESS;

    /** قاعدة ملفٍّ واحد — نصّاً، لتقبل `sometimes|` التي تُلصقها طلباتُ التحديث. */
    public static function rule(): string
    {
        return 'file|mimes:'.implode(',', self::EXTENSIONS).'|max:'.self::MAX_KILOBYTES;
    }

    /**
     * يتحقّق من الحقل قبل أيّ كتابة — ملفّاً واحداً كان أو `attachments[]`.
     *
     * @throws ValidationException
     */
    public static function validate(Request $request, string $field = 'attachments'): void
    {
        $files = $request->file($field);

        if (empty($files)) {
            return;
        }

        $many = is_array($files);

        $rules = $many
            ? [$field => 'array|max:'.self::MAX_FILES, $field.'.*' => self::rule()]
            : [$field => self::rule()];

        $request->validate($rules, self::messages($field, $many));
    }

    /** رسائل القواعد أعلاه — تُدمج في طلبات النماذج (`FormRequest::messages()`). */
    public static function messages(string $field = 'attachments', bool $many = false): array
    {
        $prefix = $many ? $field.'.*' : $field;

        $messages = [
            $prefix.'.file' => __('document_attachments.failed'),
            $prefix.'.uploaded' => __('document_attachments.failed'),
            $prefix.'.mimes' => __('document_attachments.mimes', ['types' => self::typesLabel()]),
            $prefix.'.max' => __('document_attachments.too_large', ['size' => intdiv(self::MAX_KILOBYTES, 1024)]),
        ];

        if ($many) {
            $messages[$field.'.array'] = __('document_attachments.failed');
            $messages[$field.'.max'] = __('document_attachments.too_many', ['count' => self::MAX_FILES]);
        }

        return $messages;
    }

    /**
     * الملفات السليمة المرفوعة في الحقل — مصفوفةً دائماً.
     *
     * @return array<int, UploadedFile>
     */
    public static function files(Request $request, string $field = 'attachments'): array
    {
        return array_values(array_filter(
            Arr::wrap($request->file($field)),
            fn ($file) => $file instanceof UploadedFile && $file->isValid()
        ));
    }

    /**
     * يحفظ الملف في مجلد المستند ويعيد مساره نسبةً إلى `assets/uploads`.
     *
     * @throws ValidationException نوعٌ غير مسموح (حارسٌ ثانٍ بعد `validate()`)
     */
    public static function store(UploadedFile $file, string $folder): string
    {
        if (! preg_match('#^[a-z0-9-]+(/[a-z0-9-]+)*$#', $folder)) {
            throw new InvalidArgumentException("Invalid attachments folder [{$folder}].");
        }

        $extension = self::safeExtension($file);

        self::guardBase();

        $directory = public_path(self::BASE.'/'.$folder);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $name = Str::lower((string) Str::ulid()).'.'.$extension;

        $file->move($directory, $name);

        return $folder.'/'.$name;
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    public static function storeMany(array $files, string $folder): array
    {
        return array_map(fn (UploadedFile $file) => self::store($file, $folder), $files);
    }

    /** قيمة العمود: مسارٌ واحد نصّاً، وأكثر من واحد JSON، ولا شيء `null`. */
    public static function encode(array $paths): ?string
    {
        $paths = array_values(array_filter($paths));

        return match (count($paths)) {
            0 => null,
            1 => $paths[0],
            default => json_encode($paths, JSON_UNESCAPED_SLASHES),
        };
    }

    /**
     * مسارات العمود — القيمة القديمة (اسم ملف) والجديدة (مسار أو JSON).
     *
     * @return array<int, string>
     */
    public static function paths(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded)
            ? array_values(array_filter($decoded, 'is_string'))
            : [$value];
    }

    public static function url(string $path): string
    {
        return asset(self::BASE.'/'.ltrim($path, '/'));
    }

    /** يحذف ملفات القيمة — ما كان منها داخل مجلد المرفوعات وحده. */
    public static function delete(?string $value): void
    {
        $base = realpath(public_path(self::BASE));

        if (! $base) {
            return;
        }

        foreach (self::paths($value) as $path) {
            $full = realpath(public_path(self::BASE.'/'.$path));

            if ($full && str_starts_with($full, $base.DIRECTORY_SEPARATOR) && is_file($full)) {
                @unlink($full);
            }
        }
    }

    public static function typesLabel(): string
    {
        return strtoupper(implode(', ', self::EXTENSIONS));
    }

    /**
     * الامتداد من المحتوى — والمعلَن في الاسم يجب أن يكون مسموحاً هو أيضاً.
     *
     * @throws ValidationException
     */
    private static function safeExtension(UploadedFile $file): string
    {
        $declared = strtolower($file->getClientOriginalExtension());
        $detected = strtolower((string) $file->guessExtension());

        if ($detected === 'jpeg') {
            $detected = 'jpg';
        }

        // النصّ الخام يُخمَّن `txt`، والـCSV نصٌّ خام — يبقى امتدادُه المعلن
        if ($detected === 'txt' && in_array($declared, ['csv', 'txt'], true)) {
            $detected = $declared;
        }

        if (! in_array($detected, self::EXTENSIONS, true) || ! in_array($declared, self::EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'attachments' => __('document_attachments.mimes', ['types' => self::typesLabel()]),
            ]);
        }

        return $detected;
    }

    private static function guardBase(): void
    {
        $guard = public_path(self::BASE.'/.htaccess');

        if (is_file($guard)) {
            return;
        }

        if (! is_dir(dirname($guard))) {
            mkdir(dirname($guard), 0755, true);
        }

        file_put_contents($guard, self::GUARD);
    }
}
