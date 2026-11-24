{{-- تبويب رصد المنافسين — **للعرض فقط**.

     الرصد لم يعد يُدخَل من هنا: زرّ «رصد منافس جديد» ومودالُه ومسارُه حُذفت،
     وصار الرصد جزءاً من ملاحظة الزيارة (قسم «رصد المنافسين» في مودال
     الملاحظة). مدخلٌ واحد للبيان الواحد، وهذا التبويب مكانُ مراجعته. --}}
<div class="tab-pane" id="visit-competitors" role="tabpanel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="fa fa-user-secret me-2" style="color: var(--primary-color);"></i>رصد المنافسين أثناء هذه الزيارة</h6>
    </div>

    @if ($competitors->isEmpty())
        <div class="empty-state">
            <i class="fas fa-user-secret"></i>
            <h5>لا يوجد رصد بعد</h5>
            <p class="mb-0">
                @if (\Modules\Client\Http\Controllers\VisitWorkspaceController::competitorCaptureEnabled())
                    يُسجَّل رصد المنافسين مع ملاحظة الزيارة — من زرّ «إضافة ملاحظة».
                @else
                    قسم رصد المنافسين مطفأ. يُفعَّل من: إعدادات وحدات العملاء ← الزيارات ← «رصد المنافسين داخل ملاحظة الزيارة».
                @endif
            </p>
        </div>
    @else
        <div class="row g-3">
            @foreach ($competitors as $c)
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                {{-- اسم الكتالوج إن بقي، وإلّا لقطةُ الاسم المحفوظة مع الرصد --}}
                                <strong>{{ $c->display_name }}</strong>
                                @if ($c->price !== null)
                                    <span class="text-success">{{ number_format($c->price, 2) }}</span>
                                @endif
                            </div>

                            @if ($c->asset_count !== null)
                                <div class="mt-1">
                                    <span class="badge bg-light text-dark border">
                                        <i class="fas fa-boxes me-1"></i> عهدته: {{ $c->asset_count }}
                                    </span>
                                </div>
                            @endif

                            @if ($c->notes)
                                <p class="text-muted small mt-1 mb-1">{{ $c->notes }}</p>
                            @endif

                            @if ($c->photo_path)
                                <a href="{{ asset('assets/uploads/visit_competitors/'.$c->photo_path) }}" target="_blank" class="d-inline-block mt-1">
                                    <img src="{{ asset('assets/uploads/visit_competitors/'.$c->photo_path) }}" class="ws-thumb">
                                </a>
                            @endif

                            <small class="text-muted d-block mt-2">{{ $c->created_at->format('Y-m-d h:i A') }}</small>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
