<?php

namespace HuseyinFiliz\Diff\Commands;

use Carbon\Carbon;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Revised;
use Flarum\Post\PostRepository;
use Flarum\User\Exception\PermissionDeniedException;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use HuseyinFiliz\Diff\Models\Diff;
use HuseyinFiliz\Diff\Repositories\DiffArchiveRepository;

class RollbackToDiffHandler
{
    public function __construct(
        protected PostRepository $posts,
        protected DiffArchiveRepository $diffArchive,
        protected EventDispatcher $events
    ) {
    }

    /**
     * Rollbacking to a revision will be considered as formal edit.
     * Thus, new edit will be performed for post using revision's content
     * that we want to rollback to.
     */
    public function handle(RollbackToDiff $command)
    {
        $actor = $command->actor;
        $diff = Diff::findOrFail($command->diffId);
        $post = $this->posts->findOrFail($diff->post_id, $actor);
        $isSelf = $actor->id === $post->user_id;

        if (!$actor->can('rollbackEditHistory')
            && !($isSelf && $actor->can('selfRollbackEditHistory'))) {
            throw new PermissionDeniedException();
        }

        $maxRevisionCount = Diff::where('post_id', $diff->post_id)->max('revision');

        // if we want to rollback to archived revision
        if ($diff->archive_id !== null) {
            $postContent = $this->diffArchive->getArchivedContent(
                $diff->archive_id,
                $diff->id
            );
        } else {
            $postContent = $diff->content;

            // if this is the last revision then its contents
            // gotta be null, because we were retaining its contents
            // from the post itself. We'll add a new revision after
            // this rollback operation so we need to convert this
            // null value into current content first. Revision after
            // rollbacking will be null again because it's the post itself.
            if ($diff->revision == $maxRevisionCount
                  && null === $diff->content) {
                $diff->content = $post->content;
            }
        }

        if ($post->content !== $postContent) {
            // Capture the CURRENT content before changing it
            // This is crucial - we need the actual current content as oldContent
            $oldContent = $post->content;

            // Update the post directly
            if ($post instanceof CommentPost) {
                $post->setContentAttribute($postContent, $actor);
            } else {
                $post->content = $postContent;
            }
            $post->edited_at = Carbon::now();
            $post->edited_user_id = $actor->id;
            $post->save();

            // Dispatch Revised event with the CORRECT oldContent
            // This ensures PostActions::whenRevisedPost() receives the right value
            $this->events->dispatch(new Revised($post, $actor, $oldContent));

            $diff->rollbacked_user_id = $actor->id;
            $diff->rollbacked_at = Carbon::now();

            // Query for the new revision that was just created by the event listener
            $newRevision = Diff::where('post_id', $diff->post_id)
                ->where('revision', $maxRevisionCount + 1)
                ->first();

            if ($newRevision) {
                $diff->rollbacked_to = $newRevision->id;
            }

            $diff->save();
        }

        return $diff;
    }
}