<!-- تبويب المواعيد -->
<div class="tab-pane fade" id="appointments-{{ $clientData['id'] }}" role="tabpanel">
    @if (count($clientData['appointments']) > 0)
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>تاريخ الموعد</th>
                        <th>التاريخ المسجل</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clientData['appointments'] as $key => $appointment)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td><strong>#{{ $appointment['id'] }}</strong></td>
                            <td><small class="text-muted">{{ $appointment['created_at'] ? $appointment['created_at']->format('Y-m-d H:i') : '--' }}</small></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">
            <i class="fas fa-calendar"></i>
            <h6>لا توجد مواعيد لهذا العميل</h6>
            <p class="mb-0 text-muted">لم يتم تسجيل أي مواعيد للعميل حتى الآن</p>
        </div>
    @endif
</div>
