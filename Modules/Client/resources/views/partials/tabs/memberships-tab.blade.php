<!-- تبويب العضويات -->
<div class="tab-pane fade" id="memberships-{{ $clientData['id'] }}" role="tabpanel">
    @if (count($clientData['memberships']) > 0)
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>معلومات العضوية</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($clientData['memberships'] as $key => $membership)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>
                                <strong>#{{ $membership->id ?? 'N/A' }}</strong>
                                <small class="d-block text-muted">{{ $membership->created_at ? $membership->created_at->format('Y-m-d H:i') : '--' }}</small>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty-state">
            <i class="fas fa-star"></i>
            <h6>لا توجد عضويات لهذا العميل</h6>
            <p class="mb-0 text-muted">لم يتم تسجيل أي عضويات للعميل حتى الآن</p>
        </div>
    @endif
</div>
