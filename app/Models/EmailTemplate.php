<?php

namespace App\Models;

use App\Enums\EmailCategory;
use App\Enums\EmailStatus;
use App\Enums\RecipientType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $table = 'email_templates';

    protected $primaryKey = 'template_id';

    protected $fillable = [
        'template_name',
        'template_code',
        'template_category',
        'subject',
        'html_content',
        'text_content',
        'variables',
        'language',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
        'template_category' => EmailCategory::class,
    ];

    // Relationships
    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class, 'template_id', 'template_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('template_category', $category);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('template_code', $code);
    }

    // Methods
    public function replaceVariables($data = [])
    {
        $html = $this->html_content;
        $text = $this->text_content;
        $subject = $this->subject;

        foreach ($data as $key => $value) {
            // دعم كلا الصيغتين: {{ key }} و {{key}}
            $patterns = [
                '{{ '.$key.' }}',  // مع مسافات
                '{{'.$key.'}}',     // بدون مسافات
            ];

            foreach ($patterns as $pattern) {
                $html = str_replace($pattern, $value, $html);
                $text = str_replace($pattern, $value, $text);
                $subject = str_replace($pattern, $value, $subject);
            }
        }

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }

    // Accessors
    public function getAvailableVariablesAttribute()
    {
        return $this->variables ?? [];
    }

    /**
     * الحصول على تسمية التصنيف
     */
    public function getCategoryLabelAttribute(): string
    {
        return $this->template_category?->label() ?? '';
    }

    public static function sendTemplatedEmail(
        string $to,
        string $templateCode,
        array $variables,
        ?string $subjectOverride = null,
        RecipientType $recipientType = RecipientType::Customer,
        ?int $recipientId = null
    ): void {
        $template = self::query()
            ->byCode($templateCode)
            ->active()
            ->first();

        if (! $template) {
            // تسجيل القالب المفقود للتصحيح
            Log::warning('قالب البريد غير موجود أو غير مفعل', [
                'template_code' => $templateCode,
                'recipient_email' => $to,
                'variables_keys' => array_keys($variables),
            ]);

            // نُسجّل السطر في email_logs (template_id يقبل null) حتى لا يختفي
            // البريد بصمت: يظهر في سجل البريد كفاشل مع سبب واضح.
            EmailLog::create([
                'template_id' => null,
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'recipient_email' => $to,
                'subject' => $subjectOverride ?: $templateCode,
                'email_content' => '',
                'status' => EmailStatus::Failed,
                'sent_at' => null,
                'error_message' => "قالب البريد '{$templateCode}' غير موجود أو غير مفعّل — شغّل EmailTemplateSeeder أو فعّل القالب.",
            ]);

            return;
        }

        $subject = $subjectOverride ?: ($template->subject ?? '');
        $html = $template->html_content ?? null;
        $text = $template->text_content ?? null;

        foreach ($variables as $key => $value) {
            // دعم كلا الصيغتين: {{ key }} و {{key}}
            $patterns = [
                '{{ '.$key.' }}',  // مع مسافات
                '{{'.$key.'}}',     // بدون مسافات
            ];

            // المتغيرات التي تحتوي على HTML ولا يجب تشفيرها
            $htmlVariables = ['order_items', 'items_html', 'content_html', 'body_html', 'html_content'];
            $isHtmlVariable = in_array($key, $htmlVariables);

            foreach ($patterns as $pattern) {
                $subject = str_replace($pattern, (string) $value, $subject);
                if ($html !== null) {
                    // لا نشفر المتغيرات التي تحتوي على HTML
                    $replacement = $isHtmlVariable ? (string) $value : e((string) $value);
                    $html = str_replace($pattern, $replacement, $html);
                }
                if ($text !== null) {
                    $text = str_replace($pattern, (string) $value, $text);
                }
            }
        }

        $bodyForLog = $html ?? $text ?? '';

        try {
            if ($html) {
                Mail::html($html, function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                });
            } else {
                Mail::raw($text ?? '', function ($message) use ($to, $subject) {
                    $message->to($to)->subject($subject);
                });
            }

            EmailLog::create([
                'template_id' => $template->template_id,
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'recipient_email' => $to,
                'subject' => $subject,
                'email_content' => $bodyForLog,
                'status' => EmailStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            // تسجيل فشل الإرسال للتصحيح
            Log::error('فشل إرسال البريد الإلكتروني', [
                'template_code' => $templateCode,
                'recipient_email' => $to,
                'error' => $e->getMessage(),
            ]);
            EmailLog::create([
                'template_id' => $template->template_id,
                'recipient_type' => $recipientType,
                'recipient_id' => $recipientId,
                'recipient_email' => $to,
                'subject' => $subject,
                'email_content' => $bodyForLog,
                'status' => EmailStatus::Failed,
                'sent_at' => null,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
