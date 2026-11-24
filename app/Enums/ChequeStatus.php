<?php

namespace App\Enums;

/**
 * حالات الشيكات المستلمة والصادرة
 * Cheque Status Enum for Received and Payable Cheques
 */
enum ChequeStatus: string
{
    // ═══════════════════════════════════════════════════════════════
    // حالات الشيكات المستلمة (Received Cheques)
    // ═══════════════════════════════════════════════════════════════
    
    case PENDING = 'pending';                    // برسم التحصيل - في الانتظار
    case UNDER_COLLECTION = 'under_collection';  // تحت التحصيل - تم إيداعه في البنك
    case COLLECTED = 'collected';                // تم التحصيل
    case REJECTED = 'rejected';                  // مرفوض/مرتجع
    case ENDORSED = 'endorsed';                  // تم تظهيره لطرف ثالث
    case DEFERRED = 'deferred';                  // مؤجل
    
    // ═══════════════════════════════════════════════════════════════
    // حالات الشيكات الصادرة (Payable Cheques)
    // ═══════════════════════════════════════════════════════════════
    
    case ISSUED = 'issued';                      // صادر - برسم الدفع
    case CASHED = 'cashed';                      // تم صرفه
    case CANCELLED = 'cancelled';                // ملغي
    
    /**
     * Get the Arabic name for the status
     */
    public function getName(): string
    {
        return match($this) {
            self::PENDING => 'برسم التحصيل',
            self::UNDER_COLLECTION => 'تحت التحصيل',
            self::COLLECTED => 'تم التحصيل',
            self::REJECTED => 'مرفوض',
            self::ENDORSED => 'مُظهر',
            self::DEFERRED => 'مؤجل',
            self::ISSUED => 'صادر',
            self::CASHED => 'تم الصرف',
            self::CANCELLED => 'ملغي',
        };
    }

    /**
     * Get the badge color class for the status
     */
    public function getBadgeClass(): string
    {
        return match($this) {
            self::PENDING => 'badge-warning',
            self::UNDER_COLLECTION => 'badge-info',
            self::COLLECTED => 'badge-success',
            self::REJECTED => 'badge-danger',
            self::ENDORSED => 'badge-primary',
            self::DEFERRED => 'badge-secondary',
            self::ISSUED => 'badge-warning',
            self::CASHED => 'badge-success',
            self::CANCELLED => 'badge-dark',
        };
    }

    /**
     * Get the icon for the status
     */
    public function getIcon(): string
    {
        return match($this) {
            self::PENDING => 'fa-clock',
            self::UNDER_COLLECTION => 'fa-university',
            self::COLLECTED => 'fa-check-circle',
            self::REJECTED => 'fa-times-circle',
            self::ENDORSED => 'fa-exchange-alt',
            self::DEFERRED => 'fa-calendar-alt',
            self::ISSUED => 'fa-paper-plane',
            self::CASHED => 'fa-money-bill-wave',
            self::CANCELLED => 'fa-ban',
        };
    }

    /**
     * Check if the cheque can be collected
     */
    public function canCollect(): bool
    {
        return in_array($this, [self::PENDING, self::UNDER_COLLECTION]);
    }

    /**
     * Check if the cheque can be rejected
     */
    public function canReject(): bool
    {
        return in_array($this, [self::PENDING, self::UNDER_COLLECTION]);
    }

    /**
     * Check if the cheque can be endorsed
     */
    public function canEndorse(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if the cheque can be deposited for collection
     */
    public function canDeposit(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Check if the payable cheque can be cashed
     */
    public function canCash(): bool
    {
        return $this === self::ISSUED;
    }

    /**
     * Check if the cheque can be cancelled
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::PENDING, self::ISSUED]);
    }

    /**
     * Get all received cheque statuses
     */
    public static function receivedStatuses(): array
    {
        return [
            self::PENDING,
            self::UNDER_COLLECTION,
            self::COLLECTED,
            self::REJECTED,
            self::ENDORSED,
            self::DEFERRED,
        ];
    }

    /**
     * Get all payable cheque statuses
     */
    public static function payableStatuses(): array
    {
        return [
            self::ISSUED,
            self::CASHED,
            self::REJECTED,
            self::CANCELLED,
        ];
    }
}
