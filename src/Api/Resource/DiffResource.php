<?php

namespace TheTurk\Diff\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use TheTurk\Diff\Models\Diff;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends Resource\AbstractDatabaseResource<Diff>
 */
class DiffResource extends Resource\AbstractDatabaseResource
{
    public function type(): string
    {
        return 'diff';
    }

    public function model(): string
    {
        return Diff::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Delete::make()
                ->can('delete'),
            Endpoint\Index::make()
                ->paginate(),
        ];
    }

    public function fields(): array
    {
        return [

            /**
             * @todo migrate logic from old serializer and controllers to this API Resource.
             * @see https://docs.flarum.org/2.x/extend/api#api-resources
             */

            // Example:
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->minLength(3)
                ->maxLength(255)
                ->writable(),


            Schema\Relationship\ToOne::make('actor')
                ->includable()
                // ->inverse('?') // the inverse relationship name if any.
                ->type('actors'), // the serialized type of this relation (type of the relation model's API resource).
            Schema\Relationship\ToOne::make('deletedUser')
                ->includable()
                // ->inverse('?') // the inverse relationship name if any.
                ->type('deletedUsers'), // the serialized type of this relation (type of the relation model's API resource).
            Schema\Relationship\ToOne::make('rollbackedUser')
                ->includable()
                // ->inverse('?') // the inverse relationship name if any.
                ->type('rollbackedUsers'), // the serialized type of this relation (type of the relation model's API resource).
        ];
    }

    public function sorts(): array
    {
        return [
            // SortColumn::make('createdAt'),
        ];
    }
}
