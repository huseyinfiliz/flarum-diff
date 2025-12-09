<?php

namespace HuseyinFiliz\Diff\Search;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use HuseyinFiliz\Diff\Models\Diff;
use Illuminate\Database\Eloquent\Builder;

class DiffSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        return Diff::query();
    }
}