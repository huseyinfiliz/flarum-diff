<?php

/**
 * Diff Extension for Flarum.
 *
 * LICENSE: For the full copyright and license information,
 * please view the LICENSE file that was distributed
 * with this source code.
 */

namespace HuseyinFiliz\Diff;

use Flarum\Api\Resource\PostResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Foundation\Paths;
use Flarum\Post\Post;
use Flarum\Search\Database\DatabaseSearchDriver;
use Illuminate\Console\Scheduling\Event;
use HuseyinFiliz\Diff\Console\ArchiveCommand;
use HuseyinFiliz\Diff\Models\Diff;
use HuseyinFiliz\Diff\Search\DiffSearcher;
use HuseyinFiliz\Diff\Search\PostIdFilter;

return [
    (new Extend\Frontend('admin'))
        ->css(__DIR__.'/less/admin.less')
        ->js(__DIR__.'/js/dist/admin.js'),

    (new Extend\Frontend('forum'))
        ->css(__DIR__.'/less/forum.less')
        ->js(__DIR__.'/js/dist/forum.js'),

    (new Extend\Locales(__DIR__.'/locale')),

    (new Extend\Model(Post::class))
        ->hasMany('diff', Diff::class, 'post_id'),

    (new Extend\Event())
        ->subscribe(Listeners\PostActions::class),

    (new Extend\Console())
        ->command(ArchiveCommand::class)
        ->schedule(ArchiveCommand::class, function (Event $event) {
            /** @var Paths $paths */
            $paths = resolve(Paths::class);
            $event->weeklyOn(2, '2:00')
                ->appendOutputTo($paths->storage.(DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'diff-archive-task.log'));
        }),

    (new Extend\Settings())
        ->serializeToForum('textFormattingForDiffPreviews', 'huseyinfiliz-diff.textFormatting', 'boolVal', true),

    (new Extend\User())
        ->registerPreference('diffRenderer', 'strval', 'sideBySide'),

    // Register Searcher for Diff model
    (new Extend\SearchDriver(DatabaseSearchDriver::class))
        ->addSearcher(Diff::class, DiffSearcher::class)
        ->addFilter(DiffSearcher::class, PostIdFilter::class),

    // Register the Diff API Resource
    new Extend\ApiResource(Api\Resource\DiffResource::class),

    // Extend Post resource with diff-related attributes and relationships
    (new Extend\ApiResource(PostResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canViewEditHistory')
                ->get(function ($post, $context) {
                    return $context->getActor()->can('viewEditHistory');
                }),
            Schema\Boolean::make('canDeleteEditHistory')
                ->get(function ($post, $context) {
                    $actor = $context->getActor();
                    $isSelf = $actor->id === $post->user_id;
                    return $actor->can('deleteEditHistory')
                        || ($isSelf && $actor->can('selfDeleteEditHistory'));
                }),
            Schema\Boolean::make('canRollbackEditHistory')
                ->get(function ($post, $context) {
                    $actor = $context->getActor();
                    $isSelf = $actor->id === $post->user_id;
                    return $actor->can('rollbackEditHistory')
                        || ($isSelf && $actor->can('selfRollbackEditHistory'));
                }),
            Schema\Integer::make('revisionCount')
                ->get(function ($post) {
                    $diffSubject = Diff::where('post_id', $post->id);
                    return $diffSubject->exists() ? $diffSubject->max('revision') : 0;
                }),
            Schema\Relationship\ToMany::make('diff')
                ->type('diff')
                ->includable(),
        ]),
];