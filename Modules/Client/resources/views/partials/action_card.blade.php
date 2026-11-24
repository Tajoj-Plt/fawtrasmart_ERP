{{--
    شريط إجراءات العملاء.

    ══════════════════ من عشرة أزرار إلى قائمتين ══════════════════

    كان الشريط عشرةَ أزرارٍ مربّعة (44×44) في صفٍّ واحد: الخريطة، والتعيين
    الجماعي، وتحديث الحالات، ودليل الموظفين، والاستيراد، والبحث، والحدّ
    الائتماني، وتصدير PDF، وتصدير Excel، وإضافة عميل. عشرةُ رموزٍ بلا نصّ
    لا يُعرَف أيُّها المطلوب إلّا بالمرور فوقها واحداً واحداً، وعلى الشاشات
    المتوسّطة تلتفّ إلى سطرين فتأكل ارتفاعاً قبل أن تبدأ القائمة.

    صارت: زرَّان ظاهران لما يُستعمَل كل مرّة (الخريطة، إضافة عميل)، وقائمةٌ
    منسدلة واحدة لما عداهما — كلُّ بندٍ فيها **بأيقونته واسمه**، مقسَّمةً
    بفواصل: الإدارة، ثمّ الاستيراد والتصدير.

    `#toggleMapButton` و`#downloadTemplateBtn` و`#importExcelBtn`
    و`#exportExcelBtn` و`#exportAllClientsBtn` كلُّها معرّفاتٌ تقرؤها
    الشيفرة — بقيت كما هي، وإنّما انتقلت من أزرارٍ إلى بنود قائمة.
--}}
 <div class="cl-scope">
 <div class="card" id="actionCard">
     {{-- بلا `p-3`: أداةُ Bootstrap في هذا القالب تعني 3rem (42px) بـ`!important`،
          فتغلب حشوةَ الثيم وتُخرج شريطَ أدواتٍ ارتفاعُه 130px لصفٍّ من 44px. --}}
     <div class="card-body">
         <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">

             {{-- الخريطة: تبقى ظاهرةً — هي أكثرُ ما يُضغط في هذه الشاشة --}}
             <button id="toggleMapButton" class="btn btn-outline-secondary" title="عرض الخريطة">
                 <i class="fas fa-map-marked-alt"></i>
                 <span class="d-none d-md-inline">الخريطة</span>
             </button>

             {{-- كلُّ ما عداهما في قائمةٍ واحدة --}}
             <div class="dropdown">
                 <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                     id="clientActionsDropdown" data-bs-toggle="dropdown"
                     data-bs-display="static" aria-expanded="false">
                     <i class="fas fa-ellipsis-h"></i>
                     <span class="d-none d-md-inline">إجراءات</span>
                 </button>

                 <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="clientActionsDropdown">
                     <li><h6 class="dropdown-header">الإدارة</h6></li>

                     @permission('clients_assign_employees')
                         <li>
                             <a class="dropdown-item" href="{{ route('clients.bulk-assignment.index') }}">
                                 <i class="fas fa-object-group me-2 text-primary"></i>التعيين الجماعي
                             </a>
                         </li>
                     @endpermission

                     @permission('clients_change_status')
                         <li>
                             <a class="dropdown-item" href="{{ route('clients.bulkStatusPage') }}">
                                 <i class="fas fa-tasks me-2 text-primary"></i>تحديث حالة العملاء
                             </a>
                         </li>
                     @endpermission

                     @permission('clients_assign_employees')
                         <li>
                             <a class="dropdown-item" href="{{ route('clients.employees_directory') }}">
                                 <i class="fas fa-address-book me-2 text-primary"></i>دليل موظفي العملاء
                             </a>
                         </li>
                     @endpermission

                     @permission('clients_manage_credit_limit')
                         <li>
                             <a class="dropdown-item" href="javascript:void(0);" data-bs-toggle="modal"
                                 data-bs-target="#creditLimitModal">
                                 <i class="fas fa-credit-card me-2 text-primary"></i>الحدّ الائتماني
                             </a>
                         </li>
                     @endpermission

                     @permission('clients_import')
                         <li><hr class="dropdown-divider"></li>
                         <li><h6 class="dropdown-header">الاستيراد</h6></li>
                         <li>
                             <button class="dropdown-item" id="downloadTemplateBtn" type="button">
                                 <i class="fas fa-download me-2 text-info"></i>تنزيل قالب الاستيراد
                             </button>
                         </li>
                         <li>
                             <button class="dropdown-item" id="importExcelBtn" type="button">
                                 <i class="fas fa-file-import me-2 text-info"></i>استيراد من ملف
                             </button>
                         </li>
                     @endpermission

                     @permission('clients_export')
                         <li><hr class="dropdown-divider"></li>
                         <li><h6 class="dropdown-header">التصدير</h6></li>
                         <li>
                             <a class="dropdown-item" href="{{ route('clients.export-pdf', ['type' => 'filtered']) }}">
                                 <i class="fas fa-file-pdf me-2 text-danger"></i>PDF — النتائج المُصفّاة
                             </a>
                         </li>
                         <li>
                             <a class="dropdown-item" href="{{ route('clients.export-pdf', ['type' => 'page']) }}">
                                 <i class="fas fa-file-pdf me-2 text-danger"></i>PDF — الصفحة الحالية
                             </a>
                         </li>
                         <li>
                             <a class="dropdown-item" href="{{ route('clients.export-pdf', ['type' => 'all']) }}">
                                 <i class="fas fa-file-pdf me-2 text-danger"></i>PDF — كل العملاء
                             </a>
                         </li>
                         <li>
                             <button class="dropdown-item" id="exportExcelBtn" type="button">
                                 <i class="fas fa-file-excel me-2 text-success"></i>تصدير Excel
                             </button>
                         </li>
                     @endpermission
                 </ul>
             </div>

             @permission('clients_add_client')
                 <a href="{{ route('clients.create') }}" class="btn btn-primary">
                     <i class="fas fa-plus"></i>
                     <span class="d-none d-md-inline">إضافة عميل</span>
                 </a>
             @endpermission
         </div>
     </div>
 </div>

 <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

  </div>

 <script>
     document.addEventListener('DOMContentLoaded', function() {
         console.log('✅ صفحة تحميلت بنجاح');

         // ============================
         // 📥 تحميل نموذج Excel
         // ============================
         const downloadTemplateBtn = document.getElementById('downloadTemplateBtn');
         console.log('🔍 downloadTemplateBtn:', downloadTemplateBtn);

         if (downloadTemplateBtn) {
             downloadTemplateBtn.addEventListener('click', function(e) {
                 e.preventDefault();
                 console.log('✅ تم الضغط على تحميل النموذج');
                 const originalIcon = this.innerHTML;
                 this.innerHTML = '<i class="fas fa-spinner fa-spin text-info"></i>';
                 this.disabled = true;

                 try {
                     // إنشاء رابط التحميل
                     const downloadLink = document.createElement('a');
                     downloadLink.href = "{{ route('clients.import.template') }}";
                     console.log('📥 رابط التحميل:', downloadLink.href);
                     downloadLink.click();
                 } catch (err) {
                     console.error('❌ خطأ في التحميل:', err);
                     alert('❌ حدث خطأ أثناء تحميل النموذج: ' + err.message);
                 } finally {
                     setTimeout(() => {
                         this.innerHTML = originalIcon;
                         this.disabled = false;
                     }, 1000);
                 }
             });
         }

         // ============================
         // 📥 استيراد من Excel
         // ============================
         const importExcelBtn = document.getElementById('importExcelBtn');
         console.log('🔍 importExcelBtn:', importExcelBtn);

         if (importExcelBtn) {
             importExcelBtn.addEventListener('click', function(e) {
                 e.preventDefault();
                 console.log('✅ تم الضغط على استيراد من Excel');

                 // إنشاء input file مؤقت
                 const fileInput = document.createElement('input');
                 fileInput.type = 'file';
                 fileInput.accept = '.xlsx,.xls,.csv';
                 fileInput.style.display = 'none';

                 fileInput.addEventListener('change', async function(e) {
                     const file = e.target.files[0];
                     if (!file) {
                         console.log('⚠️ لم يتم اختيار ملف');
                         return;
                     }

                     console.log('📄 الملف المختار:', file.name);

                     const originalIcon = importExcelBtn.innerHTML;
                     importExcelBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-success"></i>';
                     importExcelBtn.disabled = true;

                     try {
                         // إنشاء FormData مع الملف
                         const formData = new FormData();
                         formData.append('file', file);

                         console.log('📤 بدء الاستيراج...');

                         // إرسال الملف للسيرفر
                         const response = await fetch("{{ route('clients.import.excel') }}", {
                             method: 'POST',
                             headers: {
                                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                             },
                             body: formData
                         });

                         console.log('📥 رد من السيرفر:', response.status);

                         const result = await response.json();
                         console.log('✅ النتيجة:', result);

                         if (result.success) {
                             alert(result.message);
                             // إعادة تحميل الصفحة بعد ثانيتين
                             setTimeout(() => location.reload(), 1000);
                         } else {
                             alert(result.message);
                         }
                     } catch (err) {
                         console.error('❌ خطأ في الاستيراد:', err);
                         alert('❌ حدث خطأ أثناء الاستيراد: ' + err.message);
                     } finally {
                         importExcelBtn.innerHTML = originalIcon;
                         importExcelBtn.disabled = false;
                     }
                 });

                 document.body.appendChild(fileInput);
                 fileInput.click();
                 document.body.removeChild(fileInput);
             });
         }

         // 🔹 دالة لجلب جميع العملاء من السيرفر
         async function fetchAllClients() {
             try {
                 const response = await fetch("{{ route('clients.export.all') }}");
                 if (!response.ok) throw new Error("فشل في تحميل بيانات العملاء");
                 const data = await response.json();
                 console.log('✅ تم جلب البيانات:', data); // للتأكد من البيانات
                 return data;
             } catch (error) {
                 console.error('❌ خطأ في جلب البيانات:', error);
                 throw error;
             }
         }

         // ============================
         // 📗 تصدير كـ Excel
         // ============================
         const exportExcelBtn = document.getElementById('exportExcelBtn');
         if (exportExcelBtn) {
             exportExcelBtn.addEventListener('click', async function(e) {
                 e.preventDefault();
                 console.log('✅ تم الضغط على تصدير Excel');
                 const btn = this;
                 const originalIcon = btn.innerHTML;
                 btn.innerHTML = '<i class="fas fa-spinner fa-spin text-success"></i>';
                 btn.disabled = true;

                 try {
                     const clients = await fetchAllClients();
                     console.log('عدد العملاء:', clients.length); // للتأكد

                     if (!clients || clients.length === 0) {
                         alert('⚠️ لا توجد بيانات عملاء للتصدير');
                         return;
                     }

                     const worksheetData = [
                         [
                             'الكود', 'الاسم التجاري', 'اسم العميل', 'الهاتف',
                             'التصنيف', 'الفرع', 'الحالة', 'المسافة',
                             'تاريخ التسجيل', 'آخر فاتورة', 'آخر دفعة',
                             'الرصيد', 'حد الائتمان', 'فترة الائتمان', 'العنوان'
                         ],
                         ...clients.map(c => [
                             c.code || '-',
                             c.trade_name || '-',
                             c.first_name || '-',
                             c.phone || '-',
                             c.category || '-',
                             c.branch || '-',
                             c.status || '-',
                             c.distance || '-',
                             c.created_at || '-',
                             c.last_invoice || '-',
                             c.last_payment || '-',
                             c.balance || '-',
                             c.credit_limit || '-',
                             c.credit_period || '-',
                             c.address || '-'
                         ])
                     ];

                     const ws = XLSX.utils.aoa_to_sheet(worksheetData);
                     ws['!cols'] = Array(15).fill({
                         wch: 15
                     });
                     const wb = XLSX.utils.book_new();
                     XLSX.utils.book_append_sheet(wb, ws, 'العملاء');
                     const filename = 'العملاء_' + new Date().toISOString().slice(0, 10) + '.xlsx';
                     XLSX.writeFile(wb, filename);

                     alert('✅ تم تصدير ' + clients.length + ' عميل إلى ملف Excel بنجاح');
                 } catch (err) {
                     console.error('❌ خطأ في التصدير:', err);
                     alert('❌ حدث خطأ أثناء تصدير Excel: ' + err.message);
                 } finally {
                     btn.innerHTML = originalIcon;
                     btn.disabled = false;
                 }
             });
         }

         // ============================
         // 📕 تصدير كـ PDF
         // ============================
         const exportPdfBtn = document.getElementById('exportPdfBtn');
         if (exportPdfBtn) {
             exportPdfBtn.addEventListener('click', async function(e) {
                 e.preventDefault();
                 console.log('✅ تم الضغط على تصدير PDF');
                 const btn = this;
                 const originalIcon = btn.innerHTML;
                 btn.innerHTML = '<i class="fas fa-spinner fa-spin text-danger"></i>';
                 btn.disabled = true;

                 try {
                     const clients = await fetchAllClients();
                     console.log('عدد العملاء:', clients.length); // للتأكد

                     if (!clients || clients.length === 0) {
                         alert('⚠️ لا توجد بيانات عملاء للتصدير');
                         return;
                     }

                     const {
                         jsPDF
                     } = window.jspdf;
                     const doc = new jsPDF('landscape', 'mm', 'a4');

                     doc.setFontSize(16);
                     doc.text('قائمة العملاء', doc.internal.pageSize.width / 2, 15, {
                         align: 'center'
                     });

                     const today = new Date().toLocaleDateString('ar-SA');
                     doc.setFontSize(10);
                     doc.text('تاريخ التصدير: ' + today, doc.internal.pageSize.width - 15, 15, {
                         align: 'right'
                     });

                     const tableData = clients.map(c => [
                         c.code || '-',
                         c.trade_name || '-',
                         c.first_name || '-',
                         c.phone || '-',
                         c.category || '-',
                         c.branch || '-',
                         c.status || '-',
                         c.distance || '-',
                         c.created_at || '-',
                         c.last_invoice || '-',
                         c.last_payment || '-',
                         c.balance || '-',
                         c.credit_limit || '-',
                         c.credit_period || '-',
                         c.address || '-'
                     ]);

                     doc.autoTable({
                         head: [
                             [
                                 'الكود', 'الاسم التجاري', 'اسم العميل', 'الهاتف',
                                 'التصنيف', 'الفرع', 'الحالة', 'المسافة',
                                 'تاريخ التسجيل', 'آخر فاتورة', 'آخر دفعة',
                                 'الرصيد', 'حد الائتمان', 'فترة الائتمان', 'العنوان'
                             ]
                         ],
                         body: tableData,
                         startY: 25,
                         styles: {
                             fontSize: 7,
                             halign: 'center',
                             cellPadding: 1.5
                         },
                         headStyles: {
                             fillColor: [41, 128, 185],
                             textColor: 255,
                             fontStyle: 'bold',
                             fontSize: 8
                         },
                         alternateRowStyles: {
                             fillColor: [245, 245, 245]
                         },
                         margin: {
                             top: 25,
                             right: 8,
                             bottom: 15,
                             left: 8
                         },
                         didDrawPage: function() {
                             doc.setFontSize(8);
                             doc.text(
                                 'صفحة ' + doc.internal.getNumberOfPages(),
                                 doc.internal.pageSize.width / 2,
                                 doc.internal.pageSize.height - 5, {
                                     align: 'center'
                                 }
                             );
                         }
                     });

                     const filename = 'العملاء_' + new Date().toISOString().slice(0, 10) + '.pdf';
                     doc.save(filename);

                     alert('✅ تم تصدير ' + clients.length + ' عميل إلى ملف PDF بنجاح');
                 } catch (err) {
                     console.error('❌ خطأ في التصدير:', err);
                     alert('❌ حدث خطأ أثناء تصدير PDF: ' + err.message);
                 } finally {
                     btn.innerHTML = originalIcon;
                     btn.disabled = false;
                 }
             });
         }
     });
 </script>
