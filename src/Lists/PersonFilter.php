<?php

namespace Omnifexit\OmniCore\Lists;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Filters a list on a person's id, or on whoever is asking with `me`, so a list can start with "mine" before it knows
 * who is logged in.
 *
 * @implements Filter<Model>
 */
class PersonFilter implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $people = array_map(fn ($person) => $person === 'me' ? auth()->id() : $person, (array) $value);

        $query->whereIn($query->qualifyColumn($property), $people);
    }
}
