@if($timeline->isEmpty())
    <div class="text-center py-5">
        <div style="width:70px;height:70px;border-radius:50%;background:#eff6ff;display:inline-flex;align-items:center;justify-content:center;margin:0 auto 16px auto;">
            <i class="fa fa-inbox" style="font-size:1.8rem;color:#2563a8;"></i>
        </div>
        <h5 style="color:#1e293b;font-weight:700;margin-bottom:6px;">لا يوجد أي نشاط للعميل في هذا الشهر.</h5>
        <p style="color:#64748b;font-size:0.9rem;margin:0;">لم يتم العثور على أي بيانات لهذا العميل.</p>
    </div>
@else
    <style>
        .tbl-unified { width: 100%; border-collapse: separate; border-spacing: 0; direction: rtl; }
        .tbl-unified thead th { 
            background: #f1f5f9; color: #475569; font-weight: 700; 
            padding: 12px 15px; border-bottom: 2px solid #e2e8f0; text-align: center; font-size: 0.95rem; 
        }
        .tbl-unified tbody tr { transition: all 0.2s ease; border-bottom: 1px solid #f1f5f9; }
        .tbl-unified tbody tr:hover { transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.02); }
        .tbl-unified tbody td { padding: 14px 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        .tbl-unified tbody tr:last-child td { border-bottom: none; }
    </style>

    @php
        $unifiedTimeline = [];
        $totalSalesH = 0;
        $totalColH = 0;

        foreach($timeline as $item) {
            $d = explode(' ', $item['date'])[0]; // Y-m-d
            if(!isset($unifiedTimeline[$d])) {
                $unifiedTimeline[$d] = ['date' => $d, 'visit' => null, 'activities' => [], 'note' => null];
            }
            
            if($item['type'] === 'visit') {
                $unifiedTimeline[$d]['visit'] = [
                    // الحالةُ بقاعدة لوحة العملاء — القادمةُ والملغاةُ والمبرَّرةُ ليست «غير مكتملة»
                    'status_class' => $item['color'],
                    'status_label' => $item['state'] ?? ($item['color'] === 'success' ? 'مكتملة' : 'غير مكتملة'),
                    'justification' => $item['note']
                ];
            } elseif($item['type'] === 'invoice') {
                $totalSalesH += (float)$item['amount'];
                $unifiedTimeline[$d]['activities'][] = [
                    'type' => 'فاتورة',
                    'amount' => (float)$item['amount'],
                    'class' => 'success',
                    'icon' => 'fa-file-text-o'
                ];
            } elseif($item['type'] === 'collection') {
                $totalColH += (float)$item['amount'];
                $unifiedTimeline[$d]['activities'][] = [
                    'type' => 'تحصيل',
                    'amount' => (float)$item['amount'],
                    'class' => 'info',
                    'icon' => 'fa-money'
                ];
            } elseif($item['type'] === 'note') {
                $unifiedTimeline[$d]['note'] = [
                    'description' => $item['note'],
                    'deposit' => $item['amount'],
                    'images' => $item['images'] ?? []
                ];
            }
        }
        
        // Sort descending by date
        krsort($unifiedTimeline);
        $unifiedTimeline = array_values($unifiedTimeline);
    @endphp

    <div class="card mb-2 stat-card" style="border-radius:10px; overflow:hidden; border:none; box-shadow:0 4px 15px rgba(0,0,0,0.1);">
        <div class="card-header py-2 d-flex justify-content-between align-items-center"
             style="background:linear-gradient(135deg,#1e293b,#0f172a); color:#fff; border-bottom:none;">
            <h6 class="mb-0 font-weight-bold" style="font-family:'Cairo',sans-serif; font-size:1.1rem;">
                <i class="feather icon-activity text-success"></i>
                سجل النشاط الشهري
                @if(isset($monthTarget))
                    <span class="badge ml-2" style="background-color: {{ $monthTarget['color'] }}; color: #fff; font-size: 0.85rem; padding: 4px 8px;">
                        الهدف: {{ $monthTarget['name'] }}
                    </span>
                @endif
            </h6>
            <div dir="ltr">
                <span class="badge badge-info ml-1" style="font-size:0.9rem;">تحصيل: {{ number_format($totalColH, 2) }}</span>
                <span class="badge badge-success ml-1" style="font-size:0.9rem;">فواتير: {{ number_format($totalSalesH, 2) }}</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table tbl-unified mb-0 font-cairo text-right" dir="rtl">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th style="width:120px;">التاريخ</th>
                            <th style="width:140px;">الزيارة</th>
                            <th>الأنشطة المالية</th>
                            <th>تفاصيل الملاحظة (إن وجدت)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($unifiedTimeline as $ti => $entry)
                        @php
                            $hasVisit      = !empty($entry['visit']);
                            $hasActivities = !empty($entry['activities']);
                            $hasNote       = !empty($entry['note']);
                            $rowStyle = '';
                            if ($hasActivities)      $rowStyle = 'background-color: rgba(40, 199, 111, 0.04);';
                            elseif ($hasVisit)       $rowStyle = 'background-color: rgba(115, 103, 240, 0.03);';
                            elseif ($hasNote)        $rowStyle = 'background-color: rgba(255, 159, 67, 0.04);';
                        @endphp
                        <tr style="{{ $rowStyle }}">
                            {{-- رقم --}}
                            <td class="text-muted font-weight-bold text-center">{{ $ti + 1 }}</td>

                            {{-- التاريخ --}}
                            <td class="text-center">
                                <strong style="color:#2d3748;">{{ $entry['date'] }}</strong>
                            </td>

                            {{-- حالة الزيارة --}}
                            <td class="text-center">
                                @if($hasVisit)
                                    <span class="badge badge-{{ $entry['visit']['status_class'] }}" style="font-size:0.85rem; padding:6px 12px; border-radius:6px;">
                                        {{ $entry['visit']['status_label'] }}
                                    </span>
                                    @if(!empty($entry['visit']['justification']))
                                        <br><small class="text-muted d-block mt-1" style="font-size:0.8rem; line-height:1.4; white-space:normal;" title="{{ $entry['visit']['justification'] }}">
                                            {{ Str::limit($entry['visit']['justification'], 40) }}
                                        </small>
                                    @endif
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            {{-- الأنشطة المالية --}}
                            <td class="text-center">
                                @if($hasActivities)
                                    <div class="d-flex flex-wrap justify-content-center" style="gap:8px;">
                                        @foreach($entry['activities'] as $act)
                                            <span class="badge badge-{{ $act['class'] }}" style="font-size:0.9rem; padding:6px 10px; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                                                <i class="fa {{ $act['icon'] }} mr-1"></i>
                                                {{ $act['type'] }}: <strong>{{ number_format($act['amount'], 2) }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            {{-- تفاصيل الملاحظة (إن وجدت) --}}
                            <td style="text-align:right;">
                                @if($hasNote)
                                    <div style="background:#fff; border-radius:8px; padding:12px; border:1px solid rgba(255,159,67,0.2); box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                                        
                                        @if(!empty($entry['note']['description']))
                                            <p class="mb-2" style="font-size:0.9rem; color:#334155; line-height:1.6; white-space:pre-wrap;">{{ $entry['note']['description'] }}</p>
                                        @endif
                                        
                                        <div class="d-flex flex-wrap align-items-center" style="gap:10px;">
                                            @if(!empty($entry['note']['deposit']))
                                                <span class="badge" style="background:#fef3c7; color:#d97706; font-size:0.85rem; padding:5px 8px; border-radius:4px;">
                                                    <i class="fa fa-boxes"></i> العهدة: <strong>{{ $entry['note']['deposit'] }}</strong>
                                                </span>
                                            @endif
                                        </div>

                                        @if(!empty($entry['note']['images']))
                                            <div class="mt-3">
                                                <div class="d-flex flex-wrap" style="gap:5px;">
                                                    @foreach($entry['note']['images'] as $img)
                                                        @php 
                                                            $img = trim($img);
                                                            if (empty($img)) continue;
                                                            $imgPath = str_contains($img, '/') ? asset($img) : asset('assets/uploads/notes/' . $img);
                                                        @endphp
                                                        <a href="{{ $imgPath }}" target="_blank" data-lightbox="note-images-{{ $ti }}" data-title="مرفق الملاحظة">
                                                            <img src="{{ $imgPath }}" alt="مرفق" style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid #e2e8f0; transition:transform 0.2s;" onerror="this.style.display='none'" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
