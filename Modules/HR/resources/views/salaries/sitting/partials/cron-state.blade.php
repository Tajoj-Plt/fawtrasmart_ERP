{{--
    حالةُ المهامّ المجدولة التي يقوم عليها إعدادٌ في شاشات الرواتب.

    مفتاحُ «التوليد التلقائي» أو «مدّة الإنذار» لا يفعل شيئاً إن كانت المهمّةُ
    التي تقرؤه موقوفةً في «المهام المجدولة» — والمستخدمُ يرى المفتاحَ مفعّلاً
    فيظنّ أنّ الأمر يجري. فالحالةُ الحيّة تُعرض بجانب الإعداد نفسِه.

    @param array $cronKeys  [مفتاح cron_jobs.key => وصفٌ قصير لما تقرؤه من هنا]
--}}
@php
    $cronRows = collect();
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('cron_jobs')) {
            $cronRows = \App\Models\CronJob::query()
                ->whereIn('key', array_keys($cronKeys ?? []))
                ->get()
                ->keyBy('key');
        }
    } catch (\Throwable $e) {
        $cronRows = collect();
    }
    $canOpenCron = hasPermission('settings.cron.view');
@endphp

<div class="stk-section">
    <div class="stk-section__head">
        <i class="feather icon-clock"></i>
        <h6 class="stk-section__title">المهامّ المجدولة التي تنفّذ هذه الإعدادات</h6>
        <i class="feather icon-help-circle stk-tip"
           title="الإعدادُ هنا يُقرأ عند تشغيل المهمّة. مهمّةٌ موقوفة في «المهام المجدولة» تعني أنّ الإعداد لا يسري مهما كانت قيمتُه."></i>
    </div>

    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>المهمّة</th>
                    <th>ما تقرؤه من هنا</th>
                    <th>الموعد</th>
                    <th>الحالة</th>
                    <th>آخر تشغيل</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cronKeys ?? [] as $cronKey => $cronUse)
                    @php $job = $cronRows->get($cronKey); @endphp
                    <tr>
                        <td>{{ $job->name ?? $cronKey }}</td>
                        <td class="text-muted">{{ $cronUse }}</td>
                        <td>{{ $job ? ($job->schedule_time ? substr((string) $job->schedule_time, 0, 5) : $job->cron_expression) : '—' }}</td>
                        <td>
                            @if (! $job)
                                <span class="stk-state stk-state--off">غير مسجّلة</span>
                            @elseif ($job->status === \App\Models\CronJob::STATUS_ACTIVE)
                                <span class="stk-state stk-state--on">نشطة</span>
                            @else
                                <span class="stk-state stk-state--off">{{ \App\Models\CronJob::STATUSES[$job->status]['label'] ?? $job->status }} — الإعداد لا يسري</span>
                            @endif
                        </td>
                        <td>{{ $job && $job->last_run_at ? \Illuminate\Support\Carbon::parse($job->last_run_at)->format('Y-m-d H:i') : 'لم تُشغَّل بعد' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($canOpenCron)
        <p class="stk-help mb-0">
            تُدار المواعيدُ والإيقاف من <a href="{{ route('CronJobs.index') }}">المهام المجدولة</a>.
        </p>
    @endif
</div>
