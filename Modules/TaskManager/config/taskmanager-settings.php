<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Task Manager Global Settings
    |--------------------------------------------------------------------------
    |
    | This configuration file contains default values for global task manager settings.
    | These settings can be overridden through the admin interface.
    |
    */

    'statuses' => [
        [
            'id' => 'new',
            'name' => 'جديد',
            'color' => '#3498db',
            'order' => 0,
            'description' => 'المهام الجديدة التي تم إنشاؤها حديثاً'
        ],
        [
            'id' => 'in_progress',
            'name' => 'قيد التنفيذ',
            'color' => '#f1c40f',
            'order' => 1,
            'description' => 'المهام التي يتم العمل عليها حالياً'
        ],
        [
            'id' => 'completed',
            'name' => 'مكتمل',
            'color' => '#2ecc71',
            'order' => 2,
            'description' => 'المهام التي تم إنجازها'
        ],
        [
            'id' => 'on_hold',
            'name' => 'معلق',
            'color' => '#95a5a6',
            'order' => 3,
            'description' => 'المهام التي تم إيقافها مؤقتاً'
        ],
        [
            'id' => 'cancelled',
            'name' => 'ملغى',
            'color' => '#e74c3c',
            'order' => 4,
            'description' => 'المهام التي تم إلغاؤها'
        ]
    ],

    'types' => [
        [
            'id' => 'task',
            'name' => 'مهمة',
            'icon' => 'feather icon-check-square',
            'color' => '#3498db',
            'description' => 'مهمة عادية'
        ],
        [
            'id' => 'bug',
            'name' => 'خلل',
            'icon' => 'feather icon-alert-triangle',
            'color' => '#e74c3c',
            'description' => 'خلل أو مشكلة تحتاج إلى إصلاح'
        ],
        [
            'id' => 'feature',
            'name' => 'ميزة',
            'icon' => 'feather icon-star',
            'color' => '#2ecc71',
            'description' => 'ميزة جديدة أو تحسين'
        ],
        [
            'id' => 'story',
            'name' => 'قصة المستخدم',
            'icon' => 'feather icon-users',
            'color' => '#9b59b6',
            'description' => 'قصة مستخدم من منظور المستخدم النهائي'
        ],
        [
            'id' => 'epic',
            'name' => 'إبيك',
            'icon' => 'feather icon-layers',
            'color' => '#f39c12',
            'description' => 'مجموعة كبيرة من المهام أو القصص'
        ]
    ],

    'priorities' => [
        [
            'id' => 'low',
            'name' => 'منخفض',
            'color' => '#3498db',
            'level' => 1,
            'description' => 'مهمة منخفضة الأهمية'
        ],
        [
            'id' => 'medium',
            'name' => 'متوسط',
            'color' => '#f1c40f',
            'level' => 2,
            'description' => 'مهمة ذات أهمية متوسطة'
        ],
        [
            'id' => 'high',
            'name' => 'مرتفع',
            'color' => '#e67e22',
            'level' => 3,
            'description' => 'مهمة ذات أهمية عالية'
        ],
        [
            'id' => 'critical',
            'name' => 'حرج',
            'color' => '#e74c3c',
            'level' => 4,
            'description' => 'مهمة حرجة تحتاج إلى إجراء فوري'
        ]
    ],

    'notifications' => [
        'task_created' => true,
        'task_assigned' => true,
        'task_updated' => true,
        'task_completed' => true,
        'comment_added' => true,
        'status_changed' => true,
        'deadline_approaching' => true,
        'channels' => ['email']
    ],

    'permissions' => [
        'roles' => [
            'admin' => [
                'view_tasks' => true,
                'create_tasks' => true,
                'edit_tasks' => true,
                'delete_tasks' => true,
                'assign_tasks' => true,
                'change_status' => true,
                'manage_settings' => true
            ],
            'manager' => [
                'view_tasks' => true,
                'create_tasks' => true,
                'edit_tasks' => true,
                'delete_tasks' => true,
                'assign_tasks' => true,
                'change_status' => true,
                'manage_settings' => false
            ],
            'member' => [
                'view_tasks' => true,
                'create_tasks' => true,
                'edit_tasks' => true,
                'delete_tasks' => false,
                'assign_tasks' => false,
                'change_status' => true,
                'manage_settings' => false
            ],
            'viewer' => [
                'view_tasks' => true,
                'create_tasks' => false,
                'edit_tasks' => false,
                'delete_tasks' => false,
                'assign_tasks' => false,
                'change_status' => false,
                'manage_settings' => false
            ]
        ]
    ],

    'integrations' => [
        'email' => ['enabled' => true],
        'slack' => ['enabled' => false],
        'github' => ['enabled' => false],
        'google_calendar' => ['enabled' => false]
    ],

    'reporting' => [
        'schedule' => 'weekly',
        'recipients' => [],
        'formats' => ['pdf', 'xlsx']
    ]
];