-- ============================================
-- جدول إعدادات SMTP
-- يحفظ معلومات SMTP لكل مستخدم
-- ============================================

CREATE TABLE IF NOT EXISTS `smtp_settings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `use_smtp` tinyint(1) NOT NULL DEFAULT 0,
  `sender_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` text DEFAULT NULL,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) NOT NULL DEFAULT 587,
  `smtp_encryption` varchar(255) DEFAULT 'tls',
  `ssl_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `smtp_settings_user_id_foreign` (`user_id`),
  CONSTRAINT `smtp_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ملاحظات:
-- 1. تأكد من وجود جدول users قبل تشغيل هذا الأمر
-- 2. كلمات المرور مشفرة تلقائياً في التطبيق
-- 3. كل مستخدم له إعدادات خاصة به
-- ============================================
