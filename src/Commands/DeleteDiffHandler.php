<?php

namespace TheTurk\Diff\Commands;

use Carbon\Carbon;
use Flarum\Post\PostRepository;
use Flarum\User\Exception\PermissionDeniedException;
use TheTurk\Diff\Models\Diff;
use TheTurk\Diff\Repositories\DiffArchiveRepository;

class DeleteDiffHandler
{
    public function __construct(protected PostRepository $posts, protected DiffArchiveRepository $diffArchive)
    {
    }

    /*
     * Deleting revision will keep their rows.
     * Only the revision contents will be removed for good.
     */
    public function handle(DeleteDiff $command)
    {
        $actor = $command->actor;
        $diff = Diff::findOrFail($command->diffId);
        $post = $this->posts->findOrFail($diff->post_id, $actor);
        $isSelf = $actor->id === $post->user_id;

        if (!$actor->can('deleteEditHistory')
            && !($isSelf && $actor->can('selfDeleteEditHistory'))) {
            throw new PermissionDeniedException();
        }

        // if this is an archived revision
        if ($diff->archive_id !== null) {
            $this->diffArchive->deleteArchivedContent(
                $diff->archive_id,
                $diff->id
            );
            // it's not archived anymore
            $diff->archive_id = null;
        }

        if ($diff->archive_id === null) {
            $diff->content = null;
        }
        $diff->deleted_user_id = $actor->id;
        $diff->deleted_at = Carbon::now();
        $diff->save();
    }
}
