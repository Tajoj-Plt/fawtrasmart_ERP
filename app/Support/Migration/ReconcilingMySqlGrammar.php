<?php

namespace App\Support\Migration;

use Illuminate\Database\Schema\Grammars\MySqlGrammar;

/**
 * نحويّةُ MySQL نفسُها، إلا أنّها تسأل [SchemaReconciler] قبل كل أمرِ مخطَّط.
 * تُركَّب أثناء تشغيلة التحديث وحدها وتُعاد بعدها.
 */
class ReconcilingMySqlGrammar extends MySqlGrammar
{
    use ReconcilesSchema;
}
