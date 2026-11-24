<?php

use Composer\Autoload\ClassLoader;

/*
    محمِّل اختبارات مديول التصنيع.

    الجذر يربط `Modules\` بمجلّد `Modules/` فقط، ومجلّد الاختبارات هنا
    بحرفٍ صغير (`tests`) اتّباعاً لما يولّده nwidart. فبدل تغيير إعدادٍ
    خارج المديول، نسجّل المساحة هنا على مُحمِّل Composer نفسه.

    أثرُه محصورٌ في تشغيل الاختبارات — لا يُحمَّل في الطلبات العادية.
*/

/** @var ClassLoader $loader */
$loader = require __DIR__.'/../../../vendor/autoload.php';

$loader->addPsr4('Modules\\Manufacturing\\Tests\\', __DIR__);

return $loader;
