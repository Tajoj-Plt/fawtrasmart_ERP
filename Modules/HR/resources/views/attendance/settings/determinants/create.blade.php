@extends('hr::master')

@section('title', 'إضافة محدد حضور')

@section('content')
<div class="hr-hints">
    @include('hr::attendance._partials.styles')

@php
    $canSave = hasPermission('attendance_rules.create');
    $cancelLink = hasPermission('attendance_rules.index')
        ? '<a href="'.route('attendance_determinants.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
        : '';
@endphp

<div class="content-body">
    {{-- زرّ الحفظ (#saveBtn) يؤكّد بنافذة ثم يُرسل النموذج — انظر السكربت أسفل الصفحة --}}
    @include('stock::components.page-head', [
        'title' => 'إضافة محدد حضور',
        'icon' => 'user-check',
        'crumbs' => ['الرئيسية' => url('/'), 'محددات الحضور' => route('attendance_determinants.index'), 'إضافة' => null],
        'actions' => $cancelLink
            .($canSave ? '<button type="button" id="saveBtn" class="btn btn-primary"><i class="feather icon-check"></i> حفظ</button>' : ''),
    ])

    @include('layouts.alerts.error')

    @permission('attendance_rules.create')
        <form id="AttendanceDeterminantForm" method="POST" action="{{ route('attendance_determinants.store') }}">
            @csrf

            @include('hr::attendance.settings.determinants._form_fields', ['det' => null])

            <div class="card">
                <div class="stk-form-foot">
                    @permission('attendance_rules.index')
                        <a href="{{ route('attendance_determinants.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    @endpermission
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('saveBtn').click()">
                        <i class="feather icon-check"></i> حفظ
                    </button>
                </div>
            </div>
        </form>
    @endpermission
</div>
</div>
@include('components.hint-tips')
@endsection

@section('scripts')
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let map;
        let marker;

        function initMap() {
            // Default location (Riyadh)
            const defaultLocation = { lat: 24.7136, lng: 46.6753 };

            // Get saved coordinates if available
            const savedLat = document.getElementById('latitude').value;
            const savedLng = document.getElementById('longitude').value;
            const center = (savedLat && savedLng) ?
                { lat: parseFloat(savedLat), lng: parseFloat(savedLng) } :
                defaultLocation;

            // Create map
            map = new google.maps.Map(document.getElementById("map"), {
                zoom: 15,
                center: center,
                mapTypeId: 'roadmap'
            });

            // Create marker
            marker = new google.maps.Marker({
                position: center,
                map: map,
                draggable: true,
                title: 'موقع العمل'
            });

            // Update hidden fields
            function updateCoordinates(lat, lng) {
                document.getElementById("latitude").value = lat;
                document.getElementById("longitude").value = lng;
            }

            // Map click event
            map.addListener("click", function (event) {
                const lat = event.latLng.lat();
                const lng = event.latLng.lng();

                marker.setPosition(event.latLng);
                updateCoordinates(lat, lng);
            });

            // Marker drag event
            marker.addListener("dragend", function (event) {
                const lat = event.latLng.lat();
                const lng = event.latLng.lng();
                updateCoordinates(lat, lng);
            });

            // Set initial coordinates
            updateCoordinates(center.lat, center.lng);
        }

        $(document).ready(function() {
            // Toggle sections based on checkboxes
            function toggleSections() {
                // IP Section
                if ($('#enable_ip_verification').is(':checked')) {
                    $('#ip_addresses_section').slideDown();
                } else {
                    $('#ip_addresses_section').slideUp();
                }

                // Location Section
                if ($('#enable_location_verification').is(':checked')) {
                    $('#radius_section, #radius_type_section, #map_section').slideDown();
                    // Initialize map if not already initialized
                    if (typeof google !== 'undefined' && google.maps && !map) {
                        initMap();
                    }
                } else {
                    $('#radius_section, #radius_type_section, #map_section').slideUp();
                }
            }

            // Initial toggle
            toggleSections();

            // Checkbox change events
            $('#enable_ip_verification').change(toggleSections);
            $('#enable_location_verification').change(toggleSections);

            // Form submission
            $('#saveBtn').on('click', function(e) {
                e.preventDefault();

                // Validation
                if (!$('#name').val().trim()) {
                    Swal.fire({
                        title: 'خطأ!',
                        text: 'يرجى إدخال اسم محدد الحضور',
                        icon: 'error',
                        confirmButtonText: 'موافق'
                    });
                    return;
                }

                // Show confirmation
                Swal.fire({
                    title: 'تأكيد الحفظ',
                    text: 'هل أنت متأكد من أنك تريد حفظ محدد الحضور؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، احفظ',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'جارٍ الحفظ...',
                            text: 'يرجى الانتظار',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        // Submit form
                        $('#AttendanceDeterminantForm').submit();
                    }
                });
            });
        });
    </script>

    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB6Hsnt5MiyjXtrGT5q-5KUj09XmLPV5So&callback=initMap">
    </script>
@endsection
