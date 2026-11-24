# إرشادات تفعيل Cron Job في الاستضافة

دليل شامل لتشغيل Laravel Scheduler على أنواع الاستضافة المختلفة.

---

## ١. التحقق من عمل الـ Scheduler محلياً

قبل النشر، اختبر الـ Command يدوياً:

```bash
# تشغيل تسوية الأهداف يدوياً
php artisan targets:process-settlements

# عرض ما سيُنفَّذ بدون حفظ
php artisan targets:process-settlements --dry-run

# تنفيذ بغض النظر عن اليوم/الوقت
php artisan targets:process-settlements --force

# نوع محدد
php artisan targets:process-settlements --type=sales

# تشغيل الـ scheduler العام (يدوي - مرة واحدة)
php artisan schedule:run

# عرض كل المهام المُجدولة
php artisan schedule:list
```

---

## ٢. على استضافة مشتركة (cPanel / Plesk)

### عبر cPanel - Cron Jobs

1. افتح **cPanel** → **Advanced** → **Cron Jobs**
2. أضف Cron Job جديد:
   - **Common Settings**: Once Per Minute (`* * * * *`)
   - **Command**:
   ```bash
   cd /home/USERNAME/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
3. عدّل `USERNAME` و المسار حسب استضافتك
4. تحقق من إصدار PHP الصحيح (قد يكون `/usr/local/bin/ea-php82` أو `php8.2`)

### Plesk

1. **Schedule Tasks** → **Add Task**
2. **Run**: Cron-style
3. **Cron expression**: `* * * * *`
4. **Command**:
   ```
   php /var/www/vhosts/yourdomain.com/httpdocs/artisan schedule:run
   ```

### اكتشف مسار PHP عبر SSH:
```bash
which php
which php8.2
ls /usr/local/bin/ea-php*
```

---

## ٣. على VPS أو خادم مخصص (Linux)

### تحرير crontab للمستخدم:
```bash
crontab -e
```

أضف السطر التالي:
```
* * * * * cd /var/www/yourapp && php artisan schedule:run >> /dev/null 2>&1
```

### حفظ والخروج:
- في `vi/vim`: اضغط `Esc` ثم اكتب `:wq`
- في `nano`: `Ctrl+O` ثم `Enter` ثم `Ctrl+X`

### التحقق:
```bash
crontab -l
sudo systemctl status cron        # Ubuntu/Debian
sudo systemctl status crond       # CentOS/RHEL
```

---

## ٤. على Windows (للتطوير)

**الأسهل:** `composer dev` يشغّل الآن `php artisan schedule:work` مع الخادم والطابور وVite
(منذ 2026-09-26)، فما دامت حلقة التطوير تعمل فالمجدول يعمل. وإن شغّلت `php artisan serve`
وحده فافتح طرفية ثانية واتركها على:

```bash
php artisan schedule:work
```

علامة أنّه يعمل: صندوق «حالة المجدول على الخادم» في «إعدادات الأهداف» يصير «سليم»
خلال دقيقة، وكذلك في «المهام المجدولة» بالإعدادات.

**بلا طرفية مفتوحة** استخدم **Task Scheduler**:

1. افتح Task Scheduler
2. **Create Basic Task**:
   - **Name**: Laravel Scheduler
   - **Trigger**: Daily, تكرار كل دقيقة
   - **Action**: Start a program
   - **Program**: `C:\php\php.exe`
   - **Arguments**: `artisan schedule:run`
   - **Start in**: `C:\Users\MetaCode\Documents\GitHub\fawtmaintest1`

أو عبر PowerShell:
```powershell
$action = New-ScheduledTaskAction -Execute "php" -Argument "artisan schedule:run" -WorkingDirectory "C:\Users\MetaCode\Documents\GitHub\fawtmaintest1"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -Action $action -Trigger $trigger -TaskName "LaravelScheduler"
```

---

## ٥. على Docker

في `docker-compose.yml` أضف خدمة scheduler:

```yaml
services:
  scheduler:
    image: php:8.2-cli
    volumes:
      - ./:/app
    working_dir: /app
    command: >
      sh -c "while true; do
        php artisan schedule:run --verbose --no-interaction &
        sleep 60
      done"
```

---

## ٦. مراقبة وتشخيص الأخطاء

### الـ Logs:
```bash
# سجل تسويات الأهداف (مُكوَّن في routes/console.php)
tail -f storage/logs/targets-settlements.log

# سجل Laravel العام
tail -f storage/logs/laravel.log

# آخر 50 سطر من سجل النظام
tail -50 /var/log/syslog | grep CRON         # Ubuntu/Debian
tail -50 /var/log/cron                       # CentOS/RHEL
```

### اختبار يدوي:
```bash
php artisan schedule:run --verbose
```

### في حال عدم عمل المهام:
1. تأكد من **مسار PHP الصحيح**: `which php`
2. تأكد من **مسار المشروع**: `pwd`
3. تأكد من **صلاحية المستخدم**: المستخدم الذي يشغل cron يجب أن يكون له صلاحية كتابة على `storage/`
4. تحقق من **timezone** في `config/app.php` و `php.ini`

---

## ٧. المهام المُجدولة في هذا النظام

كما هو مُكوَّن في [routes/console.php](../../../routes/console.php):

| Command | التكرار | الوصف |
|---------|---------|--------|
| `targets:process-settlements` | كل ساعة | تنفيذ تسويات الأهداف المُجدولة |

الأمر يفحص داخلياً إعدادات `target_settings` لكل نوع (sales/collections):
- `auto_settlement_enabled = true`
- اليوم الحالي يطابق `settlement_day`
- الوقت الحالي قريب من `settlement_time` (نافذة 5 دقائق)

ثم ينفّذ التسوية للفترة المنتهية حسب `settlement_frequency` (weekly/monthly/quarterly/yearly).

---

## ٨. التوقفية الزمنية (Timezone)

تأكد من ضبط Timezone في `config/app.php`:
```php
'timezone' => 'Asia/Riyadh',
```

ثم نفّذ:
```bash
php artisan config:clear
php artisan cache:clear
```

---

## ٩. تنبيهات أمان

- **لا تستخدم `* * * * *` لأكثر من مهمة** بنفس الـ command - استخدم `Schedule::command(...)` متعدد.
- **استخدم `withoutOverlapping()`** لتجنب تداخل المهام الطويلة.
- **استخدم `runInBackground()`** للمهام التي تحتاج وقتاً.
- **اعمل backup للـ DB قبل تفعيل scheduled settlements في الإنتاج** للمرة الأولى.

---

## ١٠. خيارات إضافية مفيدة

```php
// في routes/console.php

Schedule::command('targets:process-settlements')
    ->hourly()                                        // كل ساعة
    ->timezone('Asia/Riyadh')                         // المنطقة الزمنية
    ->withoutOverlapping(60)                          // قفل لمدة 60 دقيقة
    ->runInBackground()                               // تشغيل بالخلفية
    ->onSuccess(function () {                         // عند النجاح
        \Log::info('Target settlements processed successfully');
    })
    ->onFailure(function () {                         // عند الفشل
        \Log::error('Target settlements failed');
    })
    ->emailOutputOnFailure('admin@example.com');      // إرسال خطأ للإيميل
```

---

**تم آخر تحديث:** 2026-05-09


---

## المجدول الداخلي — بلا Cron على أي دومين (2026-09-26)

النظام يُركَّب على دومينات كثيرة، ولكل دومين كان يلزم سطر Cron خاص. صار النظام يُشغّل
دورة المهام بنفسه من زيارات المستخدمين (`App\Services\Cron\SchedulerTick` عبر الوسيط
`TickSchedulerFromRequests` بعد إرسال الاستجابة)، مرة كل دقيقة على الأكثر، ويتنحّى تلقائياً
ما دام Cron حقيقي ينبض خلال آخر ثلاث دقائق. الدورة هي `cron:run-due` نفسها، لذلك انتقلت
`sms:flush` و`sessions:reconcile` من `Console\Kernel` إلى جدول `cron_jobs`.

- الإيقاف: `CRON_WEB_TICK=false` في `.env`. الفاصل: `CRON_WEB_TICK_INTERVAL` (ثوانٍ).
- ساعات الخلوّ بلا زيارات = بلا دورات. لتغطيتها: رابط النبض `/scheduler/tick/{token}` (يظهر في
  شاشة «المهام المجدولة») يُضاف إلى خدمة مراقبة تستدعيه كل دقيقة. الرمز مشتقّ من `APP_KEY`.
- شاشة المهام المجدولة تبيّن مصدر آخر نبضة: Cron، أو زيارات، أو رابط النبض.
- على Apache mod_php (بلا `fastcgi_finish_request`) ينتظر الزائر الذي وقعت الدورة في طلبه
  حتى تنتهي — مرة في الدقيقة. Cron الحقيقي يبقى الأفضل حين يتوفّر، والداخلي احتياط.
