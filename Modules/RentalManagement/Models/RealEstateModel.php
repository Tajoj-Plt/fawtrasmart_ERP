<?php

namespace Modules\RentalManagement\Models;

use App\Models\Concerns\StampsOwnerContext;
use App\Models\User;
use App\Support\DocumentAttachments;
use App\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Modules\RentalManagement\Support\RealEstateVocabulary as V;

/**
 * أساس نماذج العقارات: الفرع وختم المنشئ (كبقيّة مستندات النظام) وقراءة
 * أعمدة المرفقات والصور.
 */
abstract class RealEstateModel extends Model
{
    use BelongsToBranch, StampsOwnerContext;

    protected $guarded = ['id'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return array<int, string> مسارات نسبية إلى `assets/uploads` */
    public function filePaths(string $column = 'attachments'): array
    {
        return DocumentAttachments::paths($this->getAttribute($column));
    }

    public function coverUrl(): ?string
    {
        $first = $this->filePaths('images')[0] ?? null;

        return $first ? DocumentAttachments::url($first) : null;
    }

    protected function vocab(array $list, string $column): array
    {
        $value = $this->getAttribute($column);

        return ['label' => V::label($list, $value), 'tone' => V::tone($list, $value)];
    }
}
