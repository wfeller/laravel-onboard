<?php

namespace WF\Onboard\Grammar;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Str;

/**
 * @mixin Grammar
 */
class GrammarMacro
{
    public function reverseWheres() : Closure
    {
        return function (Builder $builder) {
            $wheres = $this->compileWheresToArray($builder->getQuery());

            foreach ($wheres as &$where) {
                $lowerWhere = mb_strtolower($where);

                if (Str::startsWith($lowerWhere, 'and')) {
                    $where = 'or not' . substr($where, 3);
                } elseif (Str::startsWith($lowerWhere, 'or')) {
                    $where = 'and not' . substr($where, 2);
                }
            }

            return preg_replace('/where /i', '', $this->concatenateWhereClauses(null, $wheres), 1);
        };
    }
}
