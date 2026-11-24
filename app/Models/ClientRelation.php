<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientRelation extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'classification_id', 'status', 'quotation_id', 'process', 'time', 'date', 'employee_id', 'description', 'visit_type', 'location_id', 'type', 'deposit_count', 'employee_view_status', 'site_type', 'invoice_id', 'competitor_documents', 'additional_data', 'attachments', 'client_employee_id', 'guarantee', 'warranty_period', 'shift_start', 'shift_end'];

    public function classification()
    {
        return $this->belongsTo(NoteClassification::class, 'classification_id');
    }

    protected $casts = [
        'additional_data' => 'array',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function location()
    {
        return $this->hasOne(Location::class, 'client_relation_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function clientEmployee()
    {
        return $this->belongsTo(ClientEmployeeNote::class, 'client_employee_id');
    }

    // دالة للحصول على نص نوع الموقع
    public function getSiteTypeTextAttribute()
    {
        $types = [
            'independent_booth' => 'بسطة مستقلة',
            'grocery' => 'بقالة',
            'supplies' => 'تموينات',
            'markets' => 'أسواق',
            'station' => 'محطة',
        ];

        return $types[$this->site_type] ?? 'غير محدد';
    }

    /**
     * المنافسون الذين رُصدوا **مع هذه الملاحظة**.
     *
     * الرصد يقع داخل مودال الملاحظة في مساحة عمل الزيارة
     * (`VisitWorkspaceController::storeCompetitorObservations()`)، ويُربط
     * بها عبر `visit_competitor_observations.client_relation_id`. بلا هذه
     * العلاقة كانت الملاحظة تُعرض في شاشة العميل وفي إدارة العلاقات بلا
     * أثرٍ لمن رُصد فيها — والرصدُ جزءٌ منها لا ملحقٌ بعيد.
     */
    public function competitorObservations()
    {
        return $this->hasMany(VisitCompetitorObservation::class, 'client_relation_id');
    }
}
