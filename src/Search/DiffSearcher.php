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
        return Diff::query()->whereHas('post', function (Builder $postQuery) use ($actor) {
            $postQuery->whereVisibleTo($actor);

            if (! $actor->hasPermission('discussion.hidePosts')) {
                $postQuery->where(function (Builder $q) use ($actor) {
                    $q->whereNull('hidden_at')
                      ->orWhere('user_id', $actor->id);
                });
            }
        });
    }
}
