/**
 * Salary Formula Autocomplete
 * نظام الإكمال التلقائي للصيغ الحسابية في حقول الرواتب
 * 
 * يوفر هذا السكريبت واجهة autocomplete عند كتابة @ في حقول الصيغ الحسابية
 * مع دعم الاتجاه من اليمين لليسار (RTL)
 */

(function() {
    'use strict';

    // المتغيرات المتاحة للاستخدام في الصيغ الحسابية
    const formulaVariables = [
        {
            name: 'basic_amount',
            displayName: 'basic_amount',
            description: 'الراتب الأساسي',
            example: 'basic_amount * 0.1',
            arabicName: 'الراتب الأساسي'
        },
        {
            name: 'basic',
            displayName: 'basic',
            description: 'الراتب الأساسي (اختصار)',
            example: 'basic + 500',
            arabicName: 'الراتب الأساسي'
        },
        {
            name: 'basic_salary',
            displayName: 'basic_salary',
            description: 'الراتب الأساسي (بديل)',
            example: 'basic_salary * 0.15',
            arabicName: 'الراتب الأساسي'
        }
    ];

    // إنشاء عنصر القائمة المنسدلة
    function createAutocompleteList() {
        const list = document.createElement('div');
        list.className = 'formula-autocomplete-list';
        list.style.cssText = `
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            max-height: 300px;
            overflow-y: auto;
            z-index: 10000;
            display: none;
            min-width: 300px;
            direction: rtl;
            text-align: right;
        `;
        document.body.appendChild(list);
        return list;
    }

    // إنشاء عنصر في القائمة
    function createListItem(variable, isSelected = false) {
        const item = document.createElement('div');
        item.className = 'formula-autocomplete-item' + (isSelected ? ' selected' : '');
        item.style.cssText = `
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            ${isSelected ? 'background-color: #e8f4fd;' : ''}
        `;
        
        item.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; flex-direction: row-reverse;">
                <div style="flex: 1;">
                    <div style="font-weight: bold; color: #2c3e50; font-size: 14px; direction: ltr; text-align: left;">
                        ${variable.displayName}
                    </div>
                    <div style="color: #7f8c8d; font-size: 12px; margin-top: 2px;">
                        ${variable.arabicName}
                    </div>
                </div>
                <div style="margin-left: 10px; color: #95a5a6; font-size: 11px; direction: ltr; text-align: left;">
                    مثال: ${variable.example}
                </div>
            </div>
        `;

        item.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#e8f4fd';
        });

        item.addEventListener('mouseleave', function() {
            if (!this.classList.contains('selected')) {
                this.style.backgroundColor = 'white';
            }
        });

        return item;
    }

    // إنشاء زر المساعدة
    function createHelpButton(input) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'formula-help-btn';
        button.innerHTML = '@';
        button.title = 'عرض المتغيرات المتاحة';
        button.style.cssText = `
            position: absolute;
            left: 5px;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
            color: #6c757d;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
            transition: all 0.2s;
        `;

        button.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#e9ecef';
            this.style.borderColor = '#adb5bd';
        });

        button.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '#f8f9fa';
            this.style.borderColor = '#ddd';
        });

        return button;
    }

    // تهيئة الإكمال التلقائي لحقل إدخال
    function initializeAutocomplete(input) {
        // إنشاء حاوية للزر
        const wrapper = document.createElement('div');
        wrapper.style.cssText = 'position: relative; display: inline-block; width: 100%;';
        
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        // إضافة padding لليمين لترك مساحة للزر
        input.style.paddingLeft = '40px';

        // إنشاء زر المساعدة
        const helpBtn = createHelpButton(input);
        wrapper.appendChild(helpBtn);

        const autocompleteList = createAutocompleteList();
        let selectedIndex = -1;
        let lastAtPosition = -1;

        // إظهار القائمة
        function showAutocomplete(query = '') {
            autocompleteList.innerHTML = '';
            selectedIndex = -1;

            const filtered = query 
                ? formulaVariables.filter(v => 
                    v.name.toLowerCase().includes(query.toLowerCase()) ||
                    v.arabicName.includes(query)
                  )
                : formulaVariables;

            if (filtered.length === 0) {
                autocompleteList.style.display = 'none';
                return;
            }

            filtered.forEach((variable, index) => {
                const item = createListItem(variable, index === 0);
                item.addEventListener('click', () => insertVariable(variable.name));
                autocompleteList.appendChild(item);
            });

            // تحديد موضع القائمة
            const rect = input.getBoundingClientRect();
            autocompleteList.style.top = (rect.bottom + window.scrollY + 5) + 'px';
            autocompleteList.style.left = (rect.left + window.scrollX) + 'px';
            autocompleteList.style.width = rect.width + 'px';
            autocompleteList.style.display = 'block';

            if (filtered.length > 0) {
                selectedIndex = 0;
            }
        }

        // إخفاء القائمة
        function hideAutocomplete() {
            autocompleteList.style.display = 'none';
            selectedIndex = -1;
            lastAtPosition = -1;
        }

        // إدراج المتغير في الحقل
        function insertVariable(variableName) {
            const value = input.value;
            const cursorPos = input.selectionStart;
            
            // البحث عن @ الأخيرة قبل المؤشر
            let atPos = value.lastIndexOf('@', cursorPos - 1);
            if (atPos === -1) atPos = lastAtPosition;

            if (atPos !== -1) {
                const before = value.substring(0, atPos);
                const after = value.substring(cursorPos);
                const newValue = before + variableName + after;
                
                input.value = newValue;
                const newCursorPos = atPos + variableName.length;
                input.setSelectionRange(newCursorPos, newCursorPos);
                input.focus();
            }

            hideAutocomplete();
        }

        // معالج حدث الإدخال
        input.addEventListener('input', function(e) {
            const value = this.value;
            const cursorPos = this.selectionStart;
            
            // البحث عن @ الأخيرة قبل المؤشر
            const atPos = value.lastIndexOf('@', cursorPos - 1);
            
            if (atPos !== -1) {
                const query = value.substring(atPos + 1, cursorPos).trim();
                const afterAt = value.substring(atPos + 1, cursorPos);
                
                // إظهار القائمة فقط إذا لم يكن هناك مسافة بعد @
                if (!afterAt.includes(' ')) {
                    lastAtPosition = atPos;
                    showAutocomplete(query);
                } else {
                    hideAutocomplete();
                }
            } else {
                hideAutocomplete();
            }
        });

        // معالج حدث لوحة المفاتيح
        input.addEventListener('keydown', function(e) {
            if (autocompleteList.style.display === 'none') return;

            const items = autocompleteList.querySelectorAll('.formula-autocomplete-item');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                updateSelection(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                selectedIndex = Math.max(selectedIndex - 1, 0);
                updateSelection(items);
            } else if (e.key === 'Enter' && selectedIndex >= 0) {
                e.preventDefault();
                const selectedVariable = formulaVariables[selectedIndex];
                insertVariable(selectedVariable.name);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                hideAutocomplete();
            }
        });

        // تحديث الاختيار المرئي
        function updateSelection(items) {
            items.forEach((item, index) => {
                if (index === selectedIndex) {
                    item.classList.add('selected');
                    item.style.backgroundColor = '#e8f4fd';
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('selected');
                    item.style.backgroundColor = 'white';
                }
            });
        }

        // إغلاق عند النقر خارج الحقل
        document.addEventListener('click', function(e) {
            if (e.target !== input && !autocompleteList.contains(e.target)) {
                hideAutocomplete();
            }
        });

        // زر المساعدة
        helpBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (autocompleteList.style.display === 'block') {
                hideAutocomplete();
            } else {
                input.focus();
                const cursorPos = input.selectionStart;
                input.value = input.value.substring(0, cursorPos) + '@' + input.value.substring(cursorPos);
                input.setSelectionRange(cursorPos + 1, cursorPos + 1);
                lastAtPosition = cursorPos;
                showAutocomplete('');
            }
        });
    }

    // تهيئة تلقائية عند تحميل الصفحة
    function init() {
        // البحث عن جميع حقول الصيغة الحسابية
        const formulaInputs = document.querySelectorAll('input[name*="calculation_formula"]');
        
        formulaInputs.forEach(input => {
            // تجنب التهيئة المزدوجة
            if (!input.dataset.formulaAutocomplete) {
                input.dataset.formulaAutocomplete = 'true';
                initializeAutocomplete(input);
            }
        });
    }

    // تشغيل عند تحميل الصفحة
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // تشغيل عند إضافة عناصر جديدة ديناميكياً
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        const inputs = node.querySelectorAll('input[name*="calculation_formula"]');
                        inputs.forEach(input => {
                            if (!input.dataset.formulaAutocomplete) {
                                input.dataset.formulaAutocomplete = 'true';
                                initializeAutocomplete(input);
                            }
                        });
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // إضافة دالة عامة للاستخدام اليدوي
    window.initFormulaAutocomplete = initializeAutocomplete;

})();
