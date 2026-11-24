<?php

namespace App\Support\Migration;

use Illuminate\Database\Schema\Grammars\MariaDbGrammar;

/**
 * نظيرةُ [ReconcilingMySqlGrammar] لـMariaDB — تُبقي خصوصيّاتها (تعاملَها
 * مع `uuid` والقيم الافتراضية) ولا تكتفي بوراثة نحويّة MySQL.
 */
class ReconcilingMariaDbGrammar extends MariaDbGrammar
{
    use ReconcilesSchema;
}
