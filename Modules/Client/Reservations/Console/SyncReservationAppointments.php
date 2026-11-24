<?php

namespace Modules\Client\Reservations\Console;

use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Client\Reservations\Models\Reservation;
use Modules\Client\Reservations\Services\ReservationAppointmentSync;

/**
 * مواعيدُ الحجوزات السابقة — ما تأكّد قبل أن يصير التأكيدُ يكتب موعداً.
 *
 * يمرّ على كلّ حجزٍ غير محذوف ويكتب موعدَه على حالته، بالخدمة نفسِها التي
 * يناديها الانتقال — فالنتيجةُ واحدةٌ أيّاً كان الطريق. آمنُ الإعادة: الموعدُ
 * فريدٌ على الحجز، فالتشغيلُ الثاني يُحدّث ولا يكرّر.
 */
class SyncReservationAppointments extends Command
{
    /*
        `--locale`: نصُّ الموعد («حجز RES-… — الخدمة») يُكتب بلغة التشغيل، وسطرُ
        الأوامر يعمل بـ`app.locale` (en) لا بلغة الواجهة — فبلا هذا تُكتب
        مواعيدُ منشأةٍ عربية بالإنجليزية.
    */
    protected $signature = 'reservations:sync-appointments
        {--dry-run : يعرض ما سيتغيّر ثمّ يتراجع}
        {--locale=ar : لغة نصّ الموعد}';

    protected $description = 'يُنشئ موعداً لكلّ حجزٍ مؤكَّد لا موعدَ له، ويُحدّث مواعيدَ الحجوزات القائمة';

    public function handle(ReservationAppointmentSync $sync): int
    {
        if (! ReservationAppointmentSync::ready()) {
            $this->error('عمود appointments.reservation_id غير موجود — شغّل الترحيلات أولاً (php artisan migrate --force).');

            return self::FAILURE;
        }

        app()->setLocale((string) $this->option('locale'));

        $dryRun = (bool) $this->option('dry-run');
        $before = Appointment::whereNotNull('reservation_id')->count();

        DB::beginTransaction();

        try {
            Reservation::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->whereNotNull('client_id')
                ->orderBy('id')
                ->chunkById(200, function ($reservations) use ($sync) {
                    foreach ($reservations as $reservation) {
                        $sync->sync($reservation);
                    }
                });

            $after = Appointment::whereNotNull('reservation_id')->count();

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[تجربة — لم يُحفظ شيء] ' : '')
            .'مواعيد الحجوزات: كانت '.$before.' وصارت '.$after.' (جديدة: '.($after - $before).').');

        return self::SUCCESS;
    }
}
