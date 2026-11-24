@extends('manufacturing::master')

@section('title')
    حاسبة احتياجات التصنيع والتكلفة
@endsection

@section('css')
    <style>
        .calculator-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .material-row {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            border: 1px solid var(--app-border);
        }
        .result-card {
            background: #e7f3ff;
            border: 2px solid var(--app-primary);
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        .result-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-right: 4px solid var(--app-success);
        }
        .btn-add-material {
            margin-top: 10px;
        }
        .highlight {
            background: #fff3cd;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: bold;
        }
        .modal-xl {
            max-width: 95%;
        }
        .accordion-button:not(.collapsed) {
            background-color: #e7f3ff;
            color: var(--app-primary);
        }
        #manufacturingGuideModal .table {
            font-size: 0.9em;
        }
        #manufacturingGuideModal code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            color: #d63384;
        }
    </style>
@endsection

@section('content')
    <div class="calculator-container">
        <h1 class="text-center mb-4">🧪 حاسبة احتياجات التصنيع والتكلفة</h1>
        <p class="text-center text-muted mb-4">احسب كمية المواد الخام المطلوبة والتكلفة لتصنيع كمية معينة من المنتج</p>

        <form action="{{ route('stock.manufacturing.test-calculator.calculate') }}" method="POST" id="calculatorForm">
            @csrf
            
            <div class="row mb-4">
                <div class="col-md-5">
                    <label class="form-label fw-bold">اسم المنتج</label>
                    <input type="text" name="product_name" class="form-control" 
                           value="{{ old('product_name', $product_name ?? 'عطر 50 مل') }}" 
                           placeholder="مثال: عطر 50 مل" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">كمية الإنتاج المطلوبة</label>
                    <input type="number" name="production_quantity" class="form-control" 
                           value="{{ old('production_quantity', $production_quantity ?? '20000') }}" 
                           placeholder="20000" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">حجم الوحدة</label>
                    <input type="number" name="unit_size" class="form-control" 
                           value="{{ old('unit_size', $unit_size ?? '50') }}" 
                           placeholder="50" step="0.01" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">وحدة القياس</label>
                    <select name="unit_size_type" class="form-control" required>
                        <option value="مل" {{ old('unit_size_type', $unit_size_type ?? 'مل') == 'مل' ? 'selected' : '' }}>مل (ميلليتر)</option>
                        <option value="لتر" {{ old('unit_size_type', $unit_size_type ?? '') == 'لتر' ? 'selected' : '' }}>لتر</option>
                        <option value="جرام" {{ old('unit_size_type', $unit_size_type ?? '') == 'جرام' ? 'selected' : '' }}>جرام</option>
                        <option value="كجم" {{ old('unit_size_type', $unit_size_type ?? '') == 'كجم' ? 'selected' : '' }}>كيلو جرام</option>
                        <option value="قطعة" {{ old('unit_size_type', $unit_size_type ?? '') == 'قطعة' ? 'selected' : '' }}>قطعة</option>
                    </select>
                </div>
            </div>

            <h3 class="mb-3">المواد الخام المستخدمة</h3>
            
            <div id="materialsContainer">
                @if(old('materials') || isset($materials))
                    @foreach(old('materials', $materials ?? []) as $index => $material)
                        <div class="material-row">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">اسم المادة</label>
                                    <input type="text" name="materials[{{ $index }}][name]" 
                                           class="form-control" value="{{ $material['name'] ?? '' }}" 
                                           placeholder="مثال: كحول" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">الكمية للوحدة الواحدة</label>
                                    <input type="number" name="materials[{{ $index }}][quantity_per_unit]" 
                                           class="form-control" value="{{ $material['quantity_per_unit'] ?? '' }}" 
                                           placeholder="40" step="0.01" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">الوحدة</label>
                                    <select name="materials[{{ $index }}][unit]" class="form-control" required>
                                        <option value="مل" {{ ($material['unit'] ?? '') == 'مل' ? 'selected' : '' }}>مل (ميلليتر)</option>
                                        <option value="جرام" {{ ($material['unit'] ?? '') == 'جرام' ? 'selected' : '' }}>جرام</option>
                                        <option value="كجم" {{ ($material['unit'] ?? '') == 'كجم' ? 'selected' : '' }}>كيلو جرام</option>
                                        <option value="لتر" {{ ($material['unit'] ?? '') == 'لتر' ? 'selected' : '' }}>لتر</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">سعر الوحدة 💰</label>
                                    <input type="number" name="materials[{{ $index }}][price_per_unit]" 
                                           class="form-control" value="{{ $material['price_per_unit'] ?? '' }}" 
                                           placeholder="0" step="0.01">
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger btn-sm w-100 remove-material">حذف</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="material-row">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">اسم المادة</label>
                                <input type="text" name="materials[0][name]" class="form-control" 
                                       value="كحول" placeholder="مثال: كحول" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">الكمية للوحدة الواحدة</label>
                                <input type="number" name="materials[0][quantity_per_unit]" 
                                       class="form-control" value="40" placeholder="40" step="0.01" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">الوحدة</label>
                                <select name="materials[0][unit]" class="form-control" required>
                                    <option value="مل" selected>مل (ميلليتر)</option>
                                    <option value="جرام">جرام</option>
                                    <option value="كجم">كيلو جرام</option>
                                    <option value="لتر">لتر</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">سعر الوحدة 💰</label>
                                <input type="number" name="materials[0][price_per_unit]" 
                                       class="form-control" value="0.5" placeholder="0" step="0.01">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-sm w-100 remove-material">حذف</button>
                            </div>
                        </div>
                    </div>
                    <div class="material-row">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">اسم المادة</label>
                                <input type="text" name="materials[1][name]" class="form-control" 
                                       value="زيت عطري" placeholder="مثال: زيت عطري" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">الكمية للوحدة الواحدة</label>
                                <input type="number" name="materials[1][quantity_per_unit]" 
                                       class="form-control" value="10" placeholder="10" step="0.01" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">الوحدة</label>
                                <select name="materials[1][unit]" class="form-control" required>
                                    <option value="مل" selected>مل (ميلليتر)</option>
                                    <option value="جرام">جرام</option>
                                    <option value="كجم">كيلو جرام</option>
                                    <option value="لتر">لتر</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">سعر الوحدة 💰</label>
                                <input type="number" name="materials[1][price_per_unit]" 
                                       class="form-control" value="2" placeholder="0" step="0.01">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="button" class="btn btn-danger btn-sm w-100 remove-material">حذف</button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <button type="button" class="btn btn-secondary btn-add-material" id="addMaterial">+ إضافة مادة خام</button>

            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">احسب الاحتياجات</button>
            </div>
        </form>

        @if(isset($calculated) && $calculated)
            <div class="result-card">
                <h2 class="text-center mb-4">📊 نتائج الحساب</h2>
                
                <div class="alert alert-info">
                    <h4>معلومات المنتج:</h4>
                    <p class="mb-1"><strong>المنتج:</strong> {{ $product_name }}</p>
                    <p class="mb-1"><strong>كمية الإنتاج:</strong> <span class="highlight">{{ number_format($production_quantity, 0) }}</span> وحدة</p>
                    <p class="mb-1"><strong>حجم الوحدة:</strong> {{ $unit_size }} {{ $unit_size_type }}</p>
                    <p class="mb-0"><strong>إجمالي حجم الإنتاج:</strong> 
                        <span class="highlight">{{ number_format($total_product_volume, 2) }}</span> {{ $unit_size_type }}
                        @if($total_product_volume_in_liters)
                            = <span class="highlight">{{ number_format($total_product_volume_in_liters, 2) }}</span> لتر
                        @endif
                        @if($total_product_volume_in_kg)
                            = <span class="highlight">{{ number_format($total_product_volume_in_kg, 2) }}</span> كجم
                        @endif
                    </p>
                </div>

                <h4 class="mt-4 mb-3">احتياجات المواد الخام:</h4>
                
                @foreach($calculated_materials as $material)
                    <div class="result-item">
                        <h5 class="text-primary">{{ $material['name'] }}</h5>
                        <div class="row">
                            <div class="col-md-4">
                                <p class="mb-1"><strong>الكمية للوحدة الواحدة:</strong> {{ $material['quantity_per_unit'] }} {{ $material['unit'] }}</p>
                            </div>
                            <div class="col-md-4">
                                <p class="mb-1">
                                    <strong>الإجمالي المطلوب:</strong> 
                                    <span class="highlight">{{ number_format($material['total_quantity'], 2) }} {{ $material['unit'] }}</span>
                                    @if($material['total_in_liters'])
                                        = <span class="highlight">{{ number_format($material['total_in_liters'], 2) }} لتر</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-4">
                                @if(isset($material['price_per_unit']) && $material['price_per_unit'] > 0)
                                    <p class="mb-1">
                                        <strong>💰 التكلفة:</strong> 
                                        <span class="highlight" style="color: var(--app-success);">{{ \App\Support\CurrencySettings::format($material['material_cost']) }}</span>
                                    </p>
                                    <p class="mb-0 text-muted" style="font-size: 0.85em;">
                                        (سعر الوحدة: {{ \App\Support\CurrencySettings::format($material['price_per_unit']) }})
                                    </p>
                                @else
                                    <p class="mb-0 text-muted">لم يتم إدخال السعر</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="alert alert-success mt-4">
                    <h5>📝 ملخص:</h5>
                    <p class="mb-0">
                        لإنتاج <strong>{{ number_format($production_quantity, 0) }}</strong> وحدة من {{ $product_name }}، 
                        ستحتاج إلى:
                    </p>
                    <ul class="mt-2 mb-0">
                        @foreach($calculated_materials as $material)
                            <li>
                                <strong>{{ number_format($material['total_quantity'], 2) }} {{ $material['unit'] }}</strong> من {{ $material['name'] }}
                                @if($material['total_in_liters'])
                                    (<strong>{{ number_format($material['total_in_liters'], 2) }} لتر</strong>)
                                @endif
                                @if($material['price_per_unit'] > 0)
                                    - <span style="color: var(--app-success);">{{ \App\Support\CurrencySettings::format($material['material_cost']) }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if($total_cost > 0)
                    <div class="alert alert-warning mt-3" style="background: #fff9e6; border: 2px solid var(--app-warning);">
                        <h4 class="text-center mb-3">💰 التكلفة الإجمالية</h4>
                        <div class="row text-center">
                            <div class="col-md-6">
                                <h5>إجمالي تكلفة المواد الخام</h5>
                                <h3 class="text-danger mb-0">{{ \App\Support\CurrencySettings::format($total_cost) }}</h3>
                            </div>
                            <div class="col-md-6">
                                <h5>تكلفة الوحدة الواحدة</h5>
                                <h3 class="text-success mb-0">{{ \App\Support\CurrencySettings::number($cost_per_unit, 3) }} {{ \App\Support\CurrencySettings::symbol() }}</h3>
                                <small class="text-muted">للوحدة الواحدة ({{ $unit_size }} {{ $unit_size_type }})</small>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- زر لإنشاء أمر تصنيع من الحسابات -->
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-success btn-lg" onclick="showManufacturingGuide()">
                        <i class="fas fa-industry"></i> 🏭 كيف أطبق هذا في أمر التصنيع؟
                    </button>
                </div>
            </div>
        @endif
    </div>

    <!-- Modal: دليل تطبيق الحسابات في أمر التصنيع -->
    @if(isset($calculated) && $calculated)
    <div class="modal fade" id="manufacturingGuideModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h3 class="modal-title">🏭 دليل تطبيق الحسابات في أمر التصنيع</h3>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle"></i> ملخص الحسابات:</h5>
                        <ul class="mb-0">
                            <li><strong>المنتج:</strong> {{ $product_name }} - {{ $unit_size }} {{ $unit_size_type }}</li>
                            <li><strong>الكمية المطلوبة:</strong> {{ number_format($production_quantity) }} وحدة</li>
                            <li><strong>التكلفة الإجمالية:</strong> {{ \App\Support\CurrencySettings::format($total_cost) }}</li>
                            <li><strong>تكلفة الوحدة:</strong> {{ \App\Support\CurrencySettings::number($cost_per_unit, 3) }} {{ \App\Support\CurrencySettings::symbol() }}</li>
                        </ul>
                    </div>

                    <h4 class="mt-4">📋 الخطوات العملية للتطبيق:</h4>
                    
                    <div class="accordion" id="stepsAccordion">
                        <!-- الخطوة 1 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#step1">
                                    <strong>الخطوة 1️⃣: تأكد من المنتج موجود في النظام</strong>
                                </button>
                            </h2>
                            <div id="step1" class="accordion-collapse collapse show" data-bs-parent="#stepsAccordion">
                                <div class="accordion-body">
                                    <p>اذهب إلى: <code>المخزون → المنتجات → قائمة المنتجات</code></p>
                                    <ul>
                                        <li>ابحث عن المنتج: <strong>{{ $product_name }}</strong></li>
                                        <li>إذا لم يكن موجوداً، أضفه من "إضافة منتج جديد"</li>
                                        <li>تأكد من نوع المنتج: <span class="badge bg-success">منتج نهائي (Final Product)</span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- الخطوة 2 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step2">
                                    <strong>الخطوة 2️⃣: تأكد من المواد الخام موجودة</strong>
                                </button>
                            </h2>
                            <div id="step2" class="accordion-collapse collapse" data-bs-parent="#stepsAccordion">
                                <div class="accordion-body">
                                    <p>اذهب إلى: <code>المخزون → المنتجات</code></p>
                                    <p><strong>تأكد من إضافة كل مادة خام:</strong></p>
                                    <table class="table table-sm table-bordered">
                                        <thead>
                                            <tr>
                                                <th>اسم المادة</th>
                                                <th>النوع</th>
                                                <th>الوحدة</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($calculated_materials as $material)
                                            <tr>
                                                <td>{{ $material['name'] }}</td>
                                                <td><span class="badge bg-warning">مادة خام (Raw Material)</span></td>
                                                <td>{{ $material['unit'] }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- الخطوة 3 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step3">
                                    <strong>الخطوة 3️⃣: إنشاء قائمة المواد (BOM)</strong>
                                </button>
                            </h2>
                            <div id="step3" class="accordion-collapse collapse" data-bs-parent="#stepsAccordion">
                                <div class="accordion-body">
                                    <p>اذهب إلى: <code>التصنيع → قوائم المواد → إضافة قائمة مواد جديدة</code></p>
                                    <ol>
                                        <li>اسم القائمة: <strong>BOM - {{ $product_name }}</strong></li>
                                        <li>المنتج النهائي: <strong>{{ $product_name }}</strong></li>
                                        <li>الكمية المنتجة: <strong>{{ $unit_size }} {{ $unit_size_type }}</strong></li>
                                    </ol>
                                    
                                    <h6 class="mt-3">أضف المواد التالية:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm">
                                            <thead class="table-primary">
                                                <tr>
                                                    <th>#</th>
                                                    <th>اسم المادة</th>
                                                    <th>الكمية للوحدة</th>
                                                    <th>الوحدة</th>
                                                    <th>السعر</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($calculated_materials as $index => $material)
                                                <tr>
                                                    <td>{{ $index + 1 }}</td>
                                                    <td><strong>{{ $material['name'] }}</strong></td>
                                                    <td>{{ $material['quantity_per_unit'] }}</td>
                                                    <td>{{ $material['unit'] }}</td>
                                                    <td>{{ number_format($material['price_per_unit'], 2) }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <button class="btn btn-sm btn-info" onclick="copyBOMData()">
                                        <i class="fas fa-copy"></i> نسخ بيانات BOM
                                    </button>
                                    <pre id="bomData" style="display:none;">{{ json_encode([
                                        'product_name' => $product_name,
                                        'unit_size' => $unit_size,
                                        'unit_type' => $unit_size_type,
                                        'materials' => array_map(function($m) {
                                            return [
                                                'name' => $m['name'],
                                                'quantity' => $m['quantity_per_unit'],
                                                'unit' => $m['unit'],
                                                'price' => $m['price_per_unit']
                                            ];
                                        }, $calculated_materials)
                                    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            </div>
                        </div>

                        <!-- الخطوة 4 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step4">
                                    <strong>الخطوة 4️⃣: إنشاء أمر التصنيع</strong>
                                </button>
                            </h2>
                            <div id="step4" class="accordion-collapse collapse" data-bs-parent="#stepsAccordion">
                                <div class="accordion-body">
                                    <p>اذهب إلى: <code>التصنيع → أوامر التصنيع → إنشاء أمر جديد</code></p>
                                    
                                    <h6>املأ البيانات التالية:</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <th width="30%">الحقل</th>
                                            <th>القيمة</th>
                                        </tr>
                                        <tr>
                                            <td>اسم الأمر</td>
                                            <td><code>تصنيع {{ $product_name }} - {{ date('Y-m-d') }}</code></td>
                                        </tr>
                                        <tr>
                                            <td>المنتج النهائي</td>
                                            <td><strong>{{ $product_name }}</strong></td>
                                        </tr>
                                        <tr>
                                            <td>الكمية المطلوبة</td>
                                            <td><strong>{{ number_format($production_quantity) }}</strong> وحدة</td>
                                        </tr>
                                        <tr>
                                            <td>قائمة المواد (BOM)</td>
                                            <td>اختر القائمة التي أنشأتها في الخطوة 3</td>
                                        </tr>
                                        <tr>
                                            <td>التكلفة المتوقعة</td>
                                            <td><strong>{{ \App\Support\CurrencySettings::number($total_cost) }}</strong> {{ \App\Support\CurrencySettings::symbol() }}</td>
                                        </tr>
                                    </table>

                                    <div class="alert alert-success mt-3">
                                        <strong>✅ النظام سيحسب تلقائياً:</strong>
                                        <ul class="mb-0">
                                            @foreach($calculated_materials as $material)
                                            <li>{{ $material['name'] }}: {{ number_format($material['total_quantity'], 2) }} {{ $material['unit'] }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- الخطوة 5 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#step5">
                                    <strong>الخطوة 5️⃣: تنفيذ أمر التصنيع</strong>
                                </button>
                            </h2>
                            <div id="step5" class="accordion-collapse collapse" data-bs-parent="#stepsAccordion">
                                <div class="accordion-body">
                                    <h6>بعد إنشاء الأمر:</h6>
                                    <ol>
                                        <li><strong>استلام المواد:</strong> خصم المواد الخام من المخزن</li>
                                        <li><strong>بدء الإنتاج:</strong> تغيير حالة الأمر إلى "قيد التنفيذ"</li>
                                        <li><strong>إتمام الإنتاج:</strong> إضافة المنتج النهائي للمخزن</li>
                                        <li><strong>إغلاق الأمر:</strong> مراجعة التكلفة الفعلية</li>
                                    </ol>

                                    <div class="alert alert-warning">
                                        <strong>⚠️ ملاحظة هامة:</strong>
                                        <p class="mb-0">عند استلام المواد، سيتم خصم:</p>
                                        <ul class="mb-0">
                                            @foreach($calculated_materials as $material)
                                            <li>{{ number_format($material['total_quantity'], 2) }} {{ $material['unit'] }} من {{ $material['name'] }}</li>
                                            @endforeach
                                        </ul>
                                        <p class="mb-0 mt-2">تأكد من توفر هذه الكميات في المخزن!</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h4>📊 ملخص البيانات للنسخ:</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>معلومات المنتج:</h6>
                            <textarea class="form-control" rows="5" readonly>المنتج: {{ $product_name }}
الكمية: {{ number_format($production_quantity) }} وحدة
الحجم: {{ $unit_size }} {{ $unit_size_type }}
التكلفة الإجمالية: {{ \App\Support\CurrencySettings::format($total_cost) }}
تكلفة الوحدة: {{ \App\Support\CurrencySettings::number($cost_per_unit, 3) }} {{ \App\Support\CurrencySettings::symbol() }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <h6>المواد المطلوبة:</h6>
                            <textarea class="form-control" rows="5" readonly>@foreach($calculated_materials as $material){{ $material['name'] }}: {{ number_format($material['total_quantity'], 2) }} {{ $material['unit'] }}
@endforeach</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                    <a href="{{ url('/ar/stock/manufacturing/orders/create') }}" class="btn btn-success" target="_blank">
                        <i class="fas fa-plus"></i> إنشاء أمر تصنيع الآن
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    <script>
        let materialIndex = {{ isset($materials) ? count($materials) : 2 }};

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM تم تحميله، materialIndex:', materialIndex);
            
            const addMaterialBtn = document.getElementById('addMaterial');
            console.log('زر الإضافة:', addMaterialBtn);
            
            if (addMaterialBtn) {
                addMaterialBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('تم الضغط على زر الإضافة');
                    addNewMaterial();
                });
            } else {
                console.error('لم يتم العثور على زر الإضافة!');
            }

            // حذف مادة خام
            document.addEventListener('click', function(e) {
                if (e.target && e.target.classList.contains('remove-material')) {
                    const materialRows = document.querySelectorAll('.material-row');
                    if (materialRows.length > 1) {
                        e.target.closest('.material-row').remove();
                    } else {
                        alert('يجب أن يكون هناك مادة خام واحدة على الأقل');
                    }
                }
            });
        });

        function addNewMaterial() {
            console.log('إضافة مادة خام جديدة، الفهرس:', materialIndex);
            
            const container = document.getElementById('materialsContainer');
            console.log('Container found:', container);
            
            if (!container) {
                alert('خطأ: لم يتم العثور على حاوية المواد الخام!');
                return;
            }
            
            const newMaterial = document.createElement('div');
            newMaterial.className = 'material-row';
            newMaterial.innerHTML = `
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">اسم المادة</label>
                        <input type="text" name="materials[${materialIndex}][name]" 
                               class="form-control" placeholder="مثال: مادة خام" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الكمية للوحدة الواحدة</label>
                        <input type="number" name="materials[${materialIndex}][quantity_per_unit]" 
                               class="form-control" placeholder="0" step="0.01" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">الوحدة</label>
                        <select name="materials[${materialIndex}][unit]" class="form-control" required>
                            <option value="مل">مل (ميلليتر)</option>
                            <option value="جرام">جرام</option>
                            <option value="كجم">كيلو جرام</option>
                            <option value="لتر">لتر</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">سعر الوحدة 💰</label>
                        <input type="number" name="materials[${materialIndex}][price_per_unit]" 
                               class="form-control" placeholder="0" step="0.01">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" class="btn btn-danger btn-sm w-100 remove-material">حذف</button>
                    </div>
                </div>
            `;
            
            container.appendChild(newMaterial);
            materialIndex++;
            console.log('تمت إضافة المادة بنجاح، الفهرس الجديد:', materialIndex);
        }

        // عرض دليل التطبيق في التصنيع
        function showManufacturingGuide() {
            const modal = new bootstrap.Modal(document.getElementById('manufacturingGuideModal'));
            modal.show();
        }

        // نسخ بيانات BOM
        function copyBOMData() {
            const bomData = document.getElementById('bomData').textContent;
            navigator.clipboard.writeText(bomData).then(function() {
                alert('✅ تم نسخ بيانات BOM بنجاح!');
            }, function() {
                alert('❌ فشل النسخ، حاول مرة أخرى');
            });
        }

        // Fallback: استخدام jQuery إذا كان موجوداً
        if (typeof jQuery !== 'undefined') {
            $(document).ready(function() {
                // حذف مادة خام (تكرار للتأكد من العمل)
                $(document).on('click', '.remove-material', function() {
                    if ($('.material-row').length > 1) {
                        $(this).closest('.material-row').remove();
                    } else {
                        alert('يجب أن يكون هناك مادة خام واحدة على الأقل');
                    }
                });
            });
        }
    </script>

@endsection