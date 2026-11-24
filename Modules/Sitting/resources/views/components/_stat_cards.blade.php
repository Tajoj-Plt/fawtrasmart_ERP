{{-- تهدئة بطاقات الإحصاء القادمة من `online-store.css`.
     يُضمَّن في كل شاشة تستعمل `.stat-card-modern`. --}}
<style>
    /* ================= تهدئة بطاقات الإحصاء =================
       `stat-card-modern` في `online-store.css` يرسم دائرة قطرها 120px
       بلون البطاقة خلف الزاوية، ويضع خلف الأيقونة هالةً بشفافية 0.4،
       ويكتب العنوان بأحرف كبيرة متباعدة بلون فاتح جداً (#a5a3ae).
       فالنتيجة أن الزخرفة تسبق الرقم إلى العين، والرقم هو المقصود.

       يُعدَّل هنا لا في `online-store.css` لأن ذاك الملفّ يخدم المتجر
       الإلكتروني أيضاً، وتغييره يمسّ شاشات خارج نطاق هذا العمل. */
    .stat-card-modern { padding: 18px 20px; border: 1px solid #e9ecf1; box-shadow: 0 1px 2px rgba(20,26,35,.04), 0 6px 16px rgba(20,26,35,.04); }
    .stat-card-modern:hover { transform: none; box-shadow: 0 1px 2px rgba(20,26,35,.06), 0 10px 24px rgba(20,26,35,.07); }
    /* الدائرة الزخرفية تُلغى: هي مصدر «البقعة» التي تملأ ربع البطاقة */
    .stat-card-modern::before { display: none; }

    .stat-card-modern .stat-icon {
        width: 40px; height: 40px; border-radius: 11px; margin-bottom: 12px;
        box-shadow: none !important; background: #eef1f5 !important;
    }
    .stat-card-modern .stat-icon i { font-size: 18px; }
    /* اللون ينتقل من الخلفية الصارخة إلى الأيقونة نفسها */
    .stat-card-modern.primary .stat-icon { background: #eef0fd !important; }
    .stat-card-modern.primary .stat-icon i { color: #5a4fd1; }
    .stat-card-modern.success .stat-icon { background: #e7f6ee !important; }
    .stat-card-modern.success .stat-icon i { color: #1b7a4b; }
    .stat-card-modern.warning .stat-icon { background: #fdf3df !important; }
    .stat-card-modern.warning .stat-icon i { color: #96700f; }
    .stat-card-modern.info    .stat-icon { background: #e4f4f8 !important; }
    .stat-card-modern.info    .stat-icon i { color: #16778a; }
    .stat-card-modern.danger  .stat-icon { background: #fceceb !important; }
    .stat-card-modern.danger  .stat-icon i { color: #a3342a; }

    .stat-card-modern .stat-label {
        font-size: .82rem; color: #6b7280; font-weight: 600;
        text-transform: none; letter-spacing: 0; margin-bottom: 4px;
    }
    .stat-card-modern .stat-value {
        font-size: 1.9rem; color: #1b2430; margin-bottom: 6px;
        font-variant-numeric: tabular-nums;
    }
    .stat-card-modern .stat-trend { font-size: .78rem; color: #8a93a0; line-height: 1.5; }
    /* الأخضر كان يصبغ سطراً وصفياً محايداً فيقرأ كأنه مؤشّر إيجابي */
    .stat-card-modern .stat-trend.up { color: #8a93a0; }
    .stat-card-modern .stat-trend.down { color: #a3342a; }
    .stat-card-modern .stat-trend i { flex: 0 0 auto; }
</style>
