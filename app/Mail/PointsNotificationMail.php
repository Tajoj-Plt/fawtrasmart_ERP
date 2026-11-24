<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * بريد إشعارات نظام النقاط
 * يستخدم لإرسال إشعارات النقاط عبر البريد الإلكتروني
 */
class PointsNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $title;
    public string $body;
    public string $eventType;
    public array $metadata;

    public function __construct(string $title, string $body, string $eventType = '', array $metadata = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->eventType = $eventType;
        $this->metadata = $metadata;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    /**
     * بناء محتوى HTML للبريد
     */
    protected function buildHtml(): string
    {
        $appName = config('app.name', 'نظام النقاط');
        $pointsInfo = '';

        // عرض تفاصيل النقاط إن وجدت
        if (!empty($this->metadata['points'])) {
            $pointsInfo = '<div style="background:#f0f9ff;border-radius:8px;padding:16px;margin:16px 0;text-align:center;">
                <span style="font-size:32px;font-weight:bold;color:#0369a1;">' . $this->metadata['points'] . '</span>
                <br><span style="color:#64748b;">نقطة</span>
            </div>';
        }

        return '<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Tahoma,Arial,sans-serif;">
<div style="max-width:600px;margin:20px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
    <!-- Header -->
    <div style="background:linear-gradient(135deg,#0369a1,#0ea5e9);padding:24px;text-align:center;">
        <h1 style="color:#ffffff;margin:0;font-size:20px;">🏆 ' . e($appName) . '</h1>
        <p style="color:#e0f2fe;margin:8px 0 0;font-size:14px;">نظام النقاط والمكافآت</p>
    </div>
    <!-- Body -->
    <div style="padding:24px;direction:rtl;text-align:right;">
        <h2 style="color:#1e293b;margin:0 0 16px;font-size:18px;">' . e($this->title) . '</h2>
        ' . $pointsInfo . '
        <div style="color:#475569;font-size:15px;line-height:1.8;">
            ' . nl2br(e($this->body)) . '
        </div>
    </div>
    <!-- Footer -->
    <div style="background:#f8fafc;padding:16px 24px;text-align:center;border-top:1px solid #e2e8f0;">
        <p style="color:#94a3b8;font-size:12px;margin:0;">هذا البريد مُرسل تلقائياً من ' . e($appName) . ' — لا ترد عليه</p>
    </div>
</div>
</body>
</html>';
    }
}
