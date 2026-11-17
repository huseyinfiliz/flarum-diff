<?php

namespace HuseyinFiliz\Diff\Listeners;

use Carbon\Carbon;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Revised as PostRevised;
use Flarum\Post\Event\Saving;
use Flarum\Settings\SettingsRepositoryInterface;
use HuseyinFiliz\Diff\Jobs\ArchiveDiffs;
use HuseyinFiliz\Diff\Models\Diff;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use WeakMap;

class PostActions
{
    /** @var WeakMap<CommentPost, array{actorId: int, reason: ?string}> */
    protected WeakMap $editReasons;

    public function __construct(protected SettingsRepositoryInterface $settings, protected ArchiveDiffs $job)
    {
        $this->editReasons = new WeakMap();
    }

    /**
     * Subscribes to the Flarum events.
     *
     * @param Dispatcher $events
     */
    public function subscribe(Dispatcher $events)
    {
        $events->listen(Saving::class, [$this, 'whenSavingPost']);
        $events->listen(
            PostRevised::class,
            [$this, 'whenRevisedPost']
        );
    }

    public function whenSavingPost(Saving $event): void
    {
        unset($this->editReasons[$event->post]);

        if (! ($event->post instanceof CommentPost) || ! $event->post->exists || ! $event->post->isDirty('content')) {
            return;
        }

        $reason = Arr::get($event->data, 'attributes.editReason');

        if (is_string($reason) && mb_strlen($reason) <= 500) {
            $this->editReasons[$event->post] = [
                'actorId' => $event->actor->id,
                'reason' => trim($reason) === '' ? null : trim($reason),
            ];
        }
    }

    /**
     * We'll always store the old content as new revision
     * because latest revision will always be the current post content.
     *
     * In Flarum 2.x, we prefer using $event->oldContent from the Revised event
     * as it's more reliable, especially for operations like rollback where
     * PostSaving event may not be triggered.
     */
    public function whenRevisedPost($event)
    {
        $pendingReason = $this->editReasons[$event->post] ?? null;
        unset($this->editReasons[$event->post]);
        $editReason = $pendingReason && $pendingReason['actorId'] === $event->actor->id
            ? $pendingReason['reason']
            : null;

        $mainPostOnly = (bool) $this->settings->get(
            'huseyinfiliz-diff.mainPostOnly',
            false
        );

        if ($mainPostOnly) {
            // skip if it's not first post
            if ($event->post->number != '1') {
                return;
            }
        }

        $archiveOlds = $this->settings->get(
            'huseyinfiliz-diff.archiveOlds',
            false
        );

        $useCrons = $this->settings->get(
            'huseyinfiliz-diff.useCrons',
            false
        );

        // The event retains the previous text, including edits performed by rollback.
        $oldContent = $event->oldContent ?? '';

        $maxRevisionCount = Diff::where('post_id', $event->post->id)->max('revision') ?? 0;

        // if this is a first edit
        if ($maxRevisionCount == 0) {
            $diff = Diff::build(
                0, // save original post as revision 0 before updating it
                $event->post->id,
                $event->post->user_id, // original post's creator
                $oldContent
            );

            $diff->created_at = $event->post->created_at;
            $diff->save();
        } else {
            // update last revision's content
            // because we set it to null before
            // (we were getting its contents from posts table
            // because latest revision is equal to latest post content)
            $latestDiff = Diff::where('post_id', $event->post->id)
                ->where('revision', $maxRevisionCount)
                ->firstOrFail();
            $latestDiff->content = $oldContent;
            $latestDiff->save();
        }

        // add new revision and set it content to null
        // because latest revision is equal to latest post content
        $diff = Diff::build(
            $maxRevisionCount + 1,
            $event->post->id,
            $event->actor->id,
            null
        );

        $diff->created_at = Carbon::now();
        $diff->edit_reason = $editReason;
        $diff->save();

        // if they want to archive old revisions
        // without using cron jobs or `diff:archive` command
        // ...we're cool with it.
        if ($archiveOlds && ! $useCrons) {
            $this->job->archiveForPost($event->post->id, $maxRevisionCount + 1);
        }
    }
}
