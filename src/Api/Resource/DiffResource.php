<?php

namespace HuseyinFiliz\Diff\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Extension\ExtensionManager;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\Post\PostRepository;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\Factory\RendererFactory;
use Symfony\Contracts\Translation\TranslatorInterface;
use HuseyinFiliz\Diff\Commands\DeleteDiff;
use HuseyinFiliz\Diff\Commands\RollbackToDiff;
use HuseyinFiliz\Diff\Models\Diff;
use HuseyinFiliz\Diff\Repositories\DiffArchiveRepository;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends AbstractDatabaseResource<Diff>
 */
class DiffResource extends AbstractDatabaseResource
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected CommentPost $commentPost,
        protected ExtensionManager $extensions,
        protected TranslatorInterface $translator,
        protected DiffArchiveRepository $diffArchive,
        protected Dispatcher $bus,
        protected PostRepository $posts
    ) {
    }

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
        $actor = $context->getActor();

        $query->whereHas('post', function (Builder $postQuery) use ($actor) {
            $postQuery->whereVisibleTo($actor);
        });
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->paginate()
                ->defaultInclude(['actor', 'deletedUser', 'rollbackedUser'])
                ->defaultSort('-revision')
                ->before(function (Context $context) {
                    $context->getActor()->assertCan('viewEditHistory');
                })
                ->eagerLoad(['actor', 'deletedUser', 'rollbackedUser', 'post']),

            Endpoint\Show::make()
                ->authenticated()
                ->before(function (Context $context) {
                    $context->getActor()->assertCan('viewEditHistory');
                }),

            Endpoint\Delete::make()
                ->can(function (Diff $diff, Context $context) {
                    $actor = $context->getActor();
                    $post = $this->posts->findOrFail($diff->post_id, $actor);
                    $isSelf = $actor->id === $post->user_id;

                    return $actor->can('deleteEditHistory')
                        || ($isSelf && $actor->can('selfDeleteEditHistory'));
                })
                ->action(function (Context $context) {
                    $diffId = Arr::get($context->request->getQueryParams(), 'id');
                    $this->bus->dispatch(
                        new DeleteDiff($context->getActor(), $diffId)
                    );
                }),

            // Rollback endpoint - POST /api/diff/{id}
            // Frontend calls: POST /api/diff/{rollbackTo}
            Endpoint\Show::make('rollback')
                ->route('POST', '/{id}')
                ->authenticated()
                ->before(function (Context $context) {
                    $diffId = Arr::get($context->request->getQueryParams(), 'id');
                    $diff = Diff::findOrFail($diffId);

                    $actor = $context->getActor();
                    $post = $this->posts->findOrFail($diff->post_id, $actor);
                    $isSelf = $actor->id === $post->user_id;

                    if (!$actor->can('rollbackEditHistory')
                        && !($isSelf && $actor->can('selfRollbackEditHistory'))) {
                        throw new \Flarum\User\Exception\PermissionDeniedException();
                    }

                    $this->bus->dispatch(
                        new RollbackToDiff($actor, $diffId)
                    );
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('revision'),
            Schema\DateTime::make('createdAt')
                ->property('created_at'),
            Schema\DateTime::make('deletedAt')
                ->property('deleted_at'),
            Schema\DateTime::make('rollbackedAt')
                ->property('rollbacked_at'),

            Schema\Boolean::make('canDeleteEditHistory')
                ->get(function (Diff $diff, Context $context) {
                    $actor = $context->getActor();
                    $post = $diff->relationLoaded('post') ? $diff->post : $this->getPost($diff->post_id);
                    if (!$post) return false;

                    $isSelf = $actor->id === $post->user_id;
                    return $actor->can('deleteEditHistory')
                        || ($isSelf && $actor->can('selfDeleteEditHistory'));
                }),

            Schema\Str::make('inlineHtml')
                ->get(fn (Diff $diff, Context $context) => $this->getDiffHtml($diff, 'Inline')),

            Schema\Str::make('sideBySideHtml')
                ->get(fn (Diff $diff, Context $context) => $this->getDiffHtml($diff, 'SideBySide')),

            Schema\Str::make('combinedHtml')
                ->get(fn (Diff $diff, Context $context) => $this->getDiffHtml($diff, 'Combined')),

            Schema\Str::make('previewHtml')
                ->get(fn (Diff $diff, Context $context) => $this->getPreviewHtml($diff)),

            Schema\Str::make('comparisonBetween')
                ->get(fn (Diff $diff, Context $context) => $this->getComparisonBetween($diff)),

            Schema\Relationship\ToOne::make('actor')
                ->includable()
                ->type('users'),

            Schema\Relationship\ToOne::make('deletedUser')
                ->includable()
                ->type('users'),

            Schema\Relationship\ToOne::make('rollbackedUser')
                ->includable()
                ->type('users'),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('revision'),
        ];
    }

    protected array $postsCache = [];
    protected array $maxRevisionsCache = [];
    protected array $compareWithCache = [];
    protected array $oldRevisionContentCache = [];
    protected array $differCache = [];

    protected function getPost(int $postId): ?Post
    {
        return $this->postsCache[$postId] ??= Post::find($postId);
    }

    protected function getMaxRevision(int $postId): int
    {
        return $this->maxRevisionsCache[$postId] ??= (int) Diff::where('post_id', $postId)->max('revision');
    }

    protected function getCompareWith(Diff $diff): ?Diff
    {
        if (! array_key_exists($diff->id, $this->compareWithCache)) {
            $this->compareWithCache[$diff->id] = Diff::where('post_id', $diff->post_id)
                ->where('revision', '<', $diff->revision)
                ->whereNull('deleted_at')
                ->orderBy('revision', 'DESC')
                ->first();
        }

        return $this->compareWithCache[$diff->id];
    }

    protected function getOldRevisionContent(Diff $diff, ?Diff $compareWith): string
    {
        if (isset($this->oldRevisionContentCache[$diff->id])) {
            return $this->oldRevisionContentCache[$diff->id];
        }

        if ($compareWith === null) {
            $post = $diff->relationLoaded('post') ? $diff->post : $this->getPost($diff->post_id);
            $content = $post?->content ?? '';
        } elseif ($compareWith->archive_id !== null) {
            $content = $this->diffArchive->getArchivedContent($compareWith->archive_id, $compareWith->id);
        } else {
            $content = $compareWith->content ?? '';
        }

        return $this->oldRevisionContentCache[$diff->id] = $content;
    }

    protected function getRevisionContent(Diff $diff): ?string
    {
        if ($diff->deleted_at !== null) {
            return null;
        }

        if ($diff->archive_id !== null) {
            return $this->diffArchive->getArchivedContent($diff->archive_id, $diff->id);
        }

        $revisionCount = $this->getMaxRevision($diff->post_id);

        if ($diff->revision == $revisionCount && $diff->content === null) {
            $post = $diff->relationLoaded('post') ? $diff->post : $this->getPost($diff->post_id);
            return $post?->content;
        }

        return $diff->content;
    }

    protected function getPreviewHtml(Diff $diff): ?string
    {
        $content = $this->getRevisionContent($diff);
        if ($content === null) {
            return null;
        }

        return $this->formatContent($content);
    }

    protected function formatContent(string $content): string
    {
        if ($this->settings->get('huseyinfiliz-diff.textFormatting', true)) {
            return $this->commentPost->getFormatter()->render(
                $this->commentPost->getFormatter()->parse($content, $this->commentPost),
                $this->commentPost
            );
        }

        return \htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    }

    protected function getComparisonBetween(Diff $diff): ?string
    {
        if ($diff->deleted_at !== null) {
            return null;
        }

        $revisionCount = $this->getMaxRevision($diff->post_id);

        $comparisonArray = [
            'new' => [
                'revision' => $diff->revision,
                'diffId' => $diff->id,
            ],
        ];

        $compareWith = $this->getCompareWith($diff);

        if ($diff->revision == 0 || ($diff->revision == $revisionCount && $compareWith === null)) {
            $comparisonArray['old'] = [
                'revision' => $diff->revision,
                'diffId' => $diff->id,
            ];
        } elseif ($compareWith === null) {
            $comparisonArray['old'] = [
                'revision' => -1,
                'diffId' => null,
            ];
        } else {
            $comparisonArray['old'] = [
                'revision' => $compareWith->revision,
                'diffId' => $compareWith->id,
            ];
        }

        return json_encode($comparisonArray);
    }

    protected function getDiffHtml(Diff $diff, string $rendererType): ?string
    {
        if ($diff->deleted_at !== null) {
            return null;
        }

        $currentRevision = $this->getRevisionContent($diff);
        if ($currentRevision === null) {
            return null;
        }

        $revisionCount = $this->getMaxRevision($diff->post_id);
        $compareWith = $this->getCompareWith($diff);

        // Preview mode - no comparison needed
        if ($diff->revision == 0 || ($diff->revision == $revisionCount && $compareWith === null)) {
            return null;
        }

        // Get old revision content
        $oldRevision = $this->getOldRevisionContent($diff, $compareWith);

        if (! isset($this->differCache[$diff->id])) {
            $ignoreCase = $ignoreWhiteSpace = false;

            if ($this->extensions->isEnabled('the-turk-quiet-edits')) {
                $ignoreCase = $this->settings->get('the-turk-quiet-edits.ignoreCase', true);
                $ignoreWhiteSpace = $this->settings->get('the-turk-quiet-edits.ignoreWhitespace', true);
            }

            $this->differCache[$diff->id] = new Differ(
                explode("\n", $oldRevision),
                explode("\n", $currentRevision),
                [
                    'context' => (int) $this->settings->get('huseyinfiliz-diff.neighborLines', 2),
                    'ignoreCase' => $ignoreCase,
                    'ignoreWhitespace' => $ignoreWhiteSpace,
                ]
            );
        }

        $differ = $this->differCache[$diff->id];

        $rendererOptions = [
            'detailLevel' => $this->settings->get('huseyinfiliz-diff.detailLevel', 'line'),
            'separateBlock' => (bool) $this->settings->get('huseyinfiliz-diff.separateBlock', true),
            'lineNumbers' => false,
            'wrapperClasses' => ['HuseyinFilizDiff', 'CustomDiff', 'diff-wrapper'],
            'resultForIdenticals' => '<div class="noDiff"><p>' . $this->translator->trans('huseyinfiliz-diff.forum.noDiff') . '</p></div>',
            'mergeThreshold' => \HuseyinFiliz\Diff\Jobs\ArchiveDiffs::sanitizeFloat($this->settings->get('huseyinfiliz-diff.mergeThreshold', 0.8)),
        ];

        $renderer = RendererFactory::make($rendererType, $rendererOptions);
        return $renderer->render($differ);
    }
}