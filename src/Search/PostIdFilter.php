<?php

namespace HuseyinFiliz\Diff\Search;

use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;

class PostIdFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'post_id';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $state->getQuery()->where('post_id', $negate ? '!=' : '=', (int) $value);
    }
}