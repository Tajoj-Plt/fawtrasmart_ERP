<style>
/* ===================================
   Points System Modern Styles
   =================================== */

/* Profile Card Modern */
.profile-card-modern {
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: none;
}
.profile-card-modern .card-body {
    padding: 2rem;
}

/* ===================================
   Modern Table Styles
   =================================== */
.table-card-modern {
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: none;
    overflow: hidden;
}
.table-card-modern .card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-bottom: 1px solid #eee;
    padding: 1.25rem 1.5rem;
}
.table-card-modern .card-body {
    padding: 1.5rem;
}

.pts-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0;
}
.pts-table thead {
    background: linear-gradient(135deg, #f8f9fa 0%, #eef1f5 100%);
}
.pts-table thead th {
    padding: 1rem 1.25rem;
    font-weight: 600;
    font-size: 0.85rem;
    color: #5e5873;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    border-bottom: 2px solid #e9ecef;
    white-space: nowrap;
}
.pts-table tbody tr {
    transition: all 0.2s ease;
}
.pts-table tbody tr:hover {
    background-color: #f8f9fa;
    transform: scale(1.001);
}
.pts-table tbody td {
    padding: 1.1rem 1.25rem;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    color: #5e5873;
}
.pts-table tbody tr:last-child td {
    border-bottom: none;
}

/* Table Cell Variants */
.pts-table .cell-id {
    font-weight: 600;
    color: #7367f0;
}
.pts-table .cell-date {
    color: #82868b;
    font-size: 0.85rem;
}
.pts-table .cell-date i {
    color: #b4b7bd;
    margin-left: 5px;
}
.pts-table .cell-points-positive {
    color: #28c76f;
    font-weight: 700;
    font-size: 0.95rem;
}
.pts-table .cell-points-positive i {
    margin-left: 3px;
}
.pts-table .cell-points-negative {
    color: #ea5455;
    font-weight: 700;
    font-size: 0.95rem;
}
.pts-table .cell-points-negative i {
    margin-left: 3px;
}
.pts-table .cell-balance {
    font-weight: 600;
    color: #5e5873;
    background: #f8f9fa;
    border-radius: 6px;
    padding: 0.4rem 0.8rem;
    display: inline-block;
}
.pts-table .cell-description {
    max-width: 280px;
    color: #82868b;
    font-size: 0.85rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Empty State */
.pts-empty-state {
    padding: 3rem 2rem;
    text-align: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-radius: 10px;
}
.pts-empty-state .empty-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f0f0f0 0%, #e8e8e8 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
}
.pts-empty-state .empty-icon i {
    font-size: 2.5rem;
    color: #b4b7bd;
}
.pts-empty-state h5 {
    color: #5e5873;
    margin-bottom: 0.5rem;
}
.pts-empty-state p {
    color: #b4b7bd;
    font-size: 0.9rem;
}

/* ===================================
   Modern Filter Section
   =================================== */
.pts-filter-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid #eee;
}
.pts-filter-section .filter-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #5e5873;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
}
.pts-filter-section .filter-title i {
    margin-left: 8px;
    color: #7367f0;
}

/* Modern Form Controls */
.pts-form-group {
    margin-bottom: 0;
}
.pts-form-group label {
    display: block;
    font-size: 0.8rem;
    font-weight: 500;
    color: #82868b;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.pts-form-control {
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    font-weight: 400;
    line-height: 1.5;
    color: #5e5873;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #d8d6de;
    border-radius: 8px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}
.pts-form-control:focus {
    color: #5e5873;
    background-color: #fff;
    border-color: #7367f0;
    outline: 0;
    box-shadow: 0 3px 10px 0 rgba(115, 103, 240, 0.1);
}
.pts-form-control::placeholder {
    color: #b4b7bd;
}

/* Modern Select */
.pts-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%235e5873' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: left 0.75rem center;
    background-size: 16px 12px;
    padding-left: 2.5rem;
}

/* Modern Date Input */
.pts-date-input {
    position: relative;
}
.pts-date-input::-webkit-calendar-picker-indicator {
    opacity: 0.5;
    cursor: pointer;
}

/* ===================================
   Modern Buttons
   =================================== */
.pts-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
    gap: 8px;
}
.pts-btn i {
    font-size: 1rem;
}
.pts-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}
.pts-btn:active {
    transform: translateY(0);
}

.pts-btn-primary {
    background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
    color: #fff;
}
.pts-btn-primary:hover {
    background: linear-gradient(135deg, #6258e8 0%, #8f85f0 100%);
    color: #fff;
}

.pts-btn-secondary {
    background: #fff;
    color: #82868b;
    border: 1px solid #d8d6de;
}
.pts-btn-secondary:hover {
    background: #f8f9fa;
    color: #5e5873;
    border-color: #b4b7bd;
}

.pts-btn-success {
    background: linear-gradient(135deg, #28c76f 0%, #48da89 100%);
    color: #fff;
}

.pts-btn-danger {
    background: linear-gradient(135deg, #ea5455 0%, #f08182 100%);
    color: #fff;
}

.pts-btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
}

.pts-btn-lg {
    padding: 1rem 2rem;
    font-size: 1rem;
}

/* Button Group */
.pts-btn-group {
    display: flex;
    gap: 0.75rem;
    align-items: center;
    flex-wrap: wrap;
}

/* ===================================
   Modern Badges
   =================================== */
.pts-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.4rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 500;
    border-radius: 50px;
    gap: 5px;
}
.pts-badge i {
    font-size: 0.75rem;
}

.pts-badge-success {
    background: rgba(40, 199, 111, 0.12);
    color: #28c76f;
}
.pts-badge-warning {
    background: rgba(255, 159, 67, 0.12);
    color: #ff9f43;
}
.pts-badge-danger {
    background: rgba(234, 84, 85, 0.12);
    color: #ea5455;
}
.pts-badge-info {
    background: rgba(0, 207, 232, 0.12);
    color: #00cfe8;
}
.pts-badge-secondary {
    background: rgba(130, 134, 139, 0.12);
    color: #82868b;
}
.pts-badge-primary {
    background: rgba(115, 103, 240, 0.12);
    color: #7367f0;
}

/* ===================================
   Timeline Modern
   =================================== */
.pts-timeline {
    position: relative;
    padding-right: 45px;
}
.pts-timeline::before {
    content: '';
    position: absolute;
    right: 17px;
    top: 0;
    bottom: 0;
    width: 3px;
    background: linear-gradient(180deg, #7367f0 0%, #ce9ffc 100%);
    border-radius: 3px;
}
.pts-timeline-item {
    position: relative;
    margin-bottom: 1.75rem;
    padding-right: 25px;
}
.pts-timeline-item:last-child {
    margin-bottom: 0;
}
.pts-timeline-marker {
    position: absolute;
    right: -35px;
    top: 0;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #7367f0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
    box-shadow: 0 2px 8px rgba(115, 103, 240, 0.2);
}
.pts-timeline-marker.success {
    border-color: #28c76f;
    color: #28c76f;
}
.pts-timeline-marker.danger {
    border-color: #ea5455;
    color: #ea5455;
}
.pts-timeline-marker i {
    font-size: 1rem;
}
.pts-timeline-content {
    background: #fff;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border: 1px solid #eee;
}
.pts-timeline-content:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,0.08);
}

/* ===================================
   Tier Card Modern
   =================================== */
.pts-tier-card {
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0,0,0,0.05);
    border: 2px solid transparent;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.pts-tier-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 40px rgba(0,0,0,0.12);
}
.pts-tier-card.active {
    border-color: #28c76f;
    background: linear-gradient(135deg, rgba(40, 199, 111, 0.03) 0%, #fff 100%);
}
.pts-tier-card .current-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: linear-gradient(135deg, #28c76f 0%, #48da89 100%);
    color: #fff;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 3px 10px rgba(40, 199, 111, 0.3);
}
.pts-tier-card .tier-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    color: #fff;
    font-size: 2rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}
.pts-tier-card .tier-name {
    font-weight: 700;
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
    color: #5e5873;
}
.pts-tier-card .tier-points {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}
.pts-tier-card .tier-points .min {
    color: #7367f0;
    font-weight: 600;
}
.pts-tier-card .tier-points .max {
    color: #28c76f;
    font-weight: 600;
}
.pts-tier-card .tier-points .separator {
    color: #b4b7bd;
    margin: 0 5px;
}
.pts-tier-card .tier-unit {
    color: #b4b7bd;
    font-size: 0.85rem;
}
.pts-tier-card .tier-benefits {
    margin-top: 1.25rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
}
.pts-tier-card .benefit-tag {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    gap: 5px;
}
.pts-tier-card .benefit-tag.success {
    background: rgba(40, 199, 111, 0.1);
    color: #28c76f;
}
.pts-tier-card .benefit-tag.primary {
    background: rgba(115, 103, 240, 0.1);
    color: #7367f0;
}
.pts-tier-card .benefit-tag.light {
    background: #f8f9fa;
    color: #5e5873;
    border: 1px solid #eee;
}

/* ===================================
   Modern Nav Tabs
   =================================== */
.pts-nav-tabs {
    border-bottom: 2px solid #eee;
    gap: 0;
}
.pts-nav-tabs .nav-item {
    margin-bottom: -2px;
}
.pts-nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    padding: 1rem 1.5rem;
    font-weight: 500;
    color: #82868b;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    background: transparent;
    border-radius: 0;
}
.pts-nav-tabs .nav-link:hover {
    color: #7367f0;
    border-bottom-color: rgba(115, 103, 240, 0.3);
}
.pts-nav-tabs .nav-link.active {
    color: #7367f0;
    border-bottom-color: #7367f0;
    background: transparent;
}
.pts-nav-tabs .nav-link .badge {
    font-size: 0.7rem;
    padding: 4px 8px;
}

/* ===================================
   Modern Pagination
   =================================== */
.pts-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 0;
    border-top: 1px solid #eee;
    margin-top: 1.5rem;
    flex-wrap: wrap;
    gap: 1rem;
}
.pts-pagination-info {
    color: #82868b;
    font-size: 0.9rem;
}
.pts-pagination-links {
    display: flex;
    gap: 5px;
}
.pts-pagination-links .page-link {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid #eee;
    color: #5e5873;
    font-weight: 500;
    transition: all 0.2s ease;
    text-decoration: none;
}
.pts-pagination-links .page-link:hover {
    background: #7367f0;
    border-color: #7367f0;
    color: #fff;
}
.pts-pagination-links .page-link.active {
    background: linear-gradient(135deg, #7367f0 0%, #9e95f5 100%);
    border-color: transparent;
    color: #fff;
    box-shadow: 0 3px 10px rgba(115, 103, 240, 0.3);
}
.pts-pagination-links .page-link.disabled {
    color: #b4b7bd;
    pointer-events: none;
    background: #f8f9fa;
}

/* ===================================
   Empty State Enhanced
   =================================== */
.pts-empty-state-full {
    padding: 4rem 2rem;
    text-align: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border-radius: 15px;
    border: 2px dashed #e0e0e0;
}
.pts-empty-state-full i {
    font-size: 4rem;
    color: #d0d0d0;
    margin-bottom: 1rem;
    display: block;
}
.pts-empty-state-full h5 {
    color: #5e5873;
    margin-bottom: 0.5rem;
    font-weight: 600;
}
.pts-empty-state-full p {
    color: #b4b7bd;
    font-size: 0.9rem;
    margin: 0;
}

/* pts-tier with prefix for show pages */
.pts-tier-current-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    background: linear-gradient(135deg, #28c76f 0%, #48da89 100%);
    color: #fff;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 3px 10px rgba(40, 199, 111, 0.3);
}
.pts-tier-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    color: #fff;
    font-size: 2rem;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}
.pts-tier-name {
    font-weight: 700;
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
    color: #5e5873;
}
.pts-tier-points {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}
.pts-tier-benefits {
    margin-top: 1.25rem;
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 8px;
}
.pts-benefit-tag {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
    gap: 5px;
}
.pts-benefit-tag.success {
    background: rgba(40, 199, 111, 0.1);
    color: #28c76f;
}
.pts-benefit-tag.primary {
    background: rgba(115, 103, 240, 0.1);
    color: #7367f0;
}

/* ===================================
   Responsive Adjustments
   =================================== */
@media (max-width: 768px) {
    .pts-filter-section {
        padding: 1rem;
    }
    .pts-filter-section .row > div {
        margin-bottom: 1rem;
    }
    .pts-btn-group {
        width: 100%;
        justify-content: stretch;
    }
    .pts-btn-group .pts-btn {
        flex: 1;
    }
    .pts-table thead th,
    .pts-table tbody td {
        padding: 0.75rem;
        font-size: 0.8rem;
    }
    .pts-nav-tabs .nav-link {
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
    }
    .pts-tier-card {
        padding: 1.5rem;
    }
    .profile-card-modern .card-body {
        padding: 1.5rem;
    }
}
</style>
