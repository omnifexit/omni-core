<?php

namespace Omnifexit\OmniCore\Lists;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Filters on one status, or with `active` on everything that isn't finished yet.
 *
 * @implements Filter<Model>
 */
class ActiveStatusFilter implements Filter
{
    /**
     * @param  list<string>  $finished
     */
    public function __construct(private readonly array $finished) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $column = $query->qualifyColumn($property);

        if ($value === 'active') {
            $query->whereNotIn($column, $this->finished);
        } else {
            $query->whereIn($column, (array) $value);
        }
    }
}
