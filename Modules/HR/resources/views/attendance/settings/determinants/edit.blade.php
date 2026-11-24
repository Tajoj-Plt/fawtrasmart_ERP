@extends('hr::master')

@section('title', 'تعديل محدد حضور')

@section('content')
<div class="hr-hints">
    @include('hr::attendance._partials.styles')

@php
    $canSave = hasPermission('attendance_rules.edit');
    $cancelLink = hasPermission('attendance_rules.index')
        ? '<a href="'.route('attendance_determinants.index').'" class="btn btn-outline-secondary"><i class="feather icon-x"></i> إلغاء</a>'
        : '';
@endphp

<div class="content-body">
    {{-- زرّ الحفظ (#updateBtn) يؤكّد بنافذة ثم يُرسل النموذج — انظر السكربت أسفل الصفحة --}}
    @include('stock::components.page-head', [
        'title' => 'تعديل محدد حضور: '.$attendance_determinants->name,
        'icon' => 'edit-2',
        'crumbs' => ['الرئيسية' => url('/'), 'محددات الحضور' => route('attendance_determinants.index'), $attendance_determinants->name => route('attendance_determinants.show', $attendance_determinants->id), 'تعديل' => null],
        'actions' => $cancelLink
            .($canSave ? '<button type="button" id="updateBtn" class="btn btn-primary"><i class="feather icon-check"></i> تحديث</button>' : ''),
    ])

    @include('layouts.alerts.error')

    @permission('attendance_rules.edit')
        <form id="AttendanceDeterminantForm" method="POST" action="{{ route('attendance_determinants.update', $attendance_determinants->id) }}">
            @csrf
            {{-- مسار التحديث POST (`attendance_determinants.update`) — كان PUT يُرجع 405 فلا يُحفظ التعديل --}}

            @include('hr::attendance.settings.determinants._form_fields', ['det' => $attendance_determinants])

            <div class="card">
                <div class="stk-form-foot">
                    @permission('attendance_rules.index')
                        <a href="{{ route('attendance_determinants.index') }}" class="btn btn-outline-secondary">
                            <i class="feather icon-x"></i> إلغاء
                        </a>
                    @endpermission
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('updateBtn').click()">
                        <i class="feather icon-check"></i> تحديث
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
            // Get saved coordinates or use default (Riyadh)
            const latitude = {{ $attendance_determinants->latitude ?? 24.7136 }};
            const longitude = {{ $attendance_determinants->longitude ?? 46.6753 }};
            const center = { lat: latitude, lng: longitude };

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
                        setTimeout(initMap, 100);
                    }
                } else {
                    $('#radius_section, #radius_type_section, #map_section').slideUp();
                }
            }

            // Initial toggle
            toggleSections();

            // Checkbox change events
            $('#enable_ip_verification').change(toggleSections);
            $('#enable_location_verification').change(function() {
                toggleSections();
                if ($(this).is(':checked')) {
                    setTimeout(function() {
                        if (typeof google !== 'undefined' && google.maps) {
                            initMap();
                        }
                    }, 300);
                }
            });

            // Form submission
            $('#updateBtn').on('click', function(e) {
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
                    title: 'تأكيد التحديث',
                    text: 'هل أنت متأكد من أنك تريد تحديث محدد الحضور؟',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'نعم، حدث',
                    cancelButtonText: 'إلغاء',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Show loading
                        Swal.fire({
                            title: 'جارٍ التحديث...',
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
