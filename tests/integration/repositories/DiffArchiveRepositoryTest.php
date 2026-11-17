<?php

namespace HuseyinFiliz\Diff\Tests\integration\repositories;

use Carbon\Carbon;
use Flarum\Testing\integration\TestCase;
use HuseyinFiliz\Diff\Models\DiffArchive;
use HuseyinFiliz\Diff\Repositories\DiffArchiveRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DiffArchiveRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-diff');
        $this->prepareDatabase([
            'discussions' => [[
                'id' => 1,
                'title' => 'Archive cache fixture',
                'slug' => 'archive-cache-fixture',
                'created_at' => Carbon::now(),
            ]],
            'posts' => [[
                'id' => 1,
                'discussion_id' => 1,
                'number' => 1,
                'type' => 'comment',
                'content' => '<t>Current content</t>',
                'created_at' => Carbon::now(),
            ]],
            DiffArchive::class => [[
                'id' => 1,
                'post_id' => 1,
                'archive_no' => 1,
                'revision_count' => 2,
                'contents' => gzcompress(json_encode([1 => 'First revision', 2 => 'Second revision'])),
            ]],
        ]);
    }

    public function test_shared_archive_is_loaded_once_for_multiple_revision_reads()
    {
        $database = $this->database();
        $database->enableQueryLog();
        $database->flushQueryLog();
        $repository = new DiffArchiveRepository();

        $this->assertSame('First revision', $repository->getArchivedContent(1, 1));
        $this->assertSame('Second revision', $repository->getArchivedContent(1, 2));
        $this->assertSame('First revision', $repository->getArchivedContent(1, 1));
        $this->assertNull($repository->getArchivedContent(1, 999));
        $this->assertCount(1, $database->getQueryLog());
    }

    public function test_appending_content_invalidates_the_cached_archive()
    {
        $this->database();
        $repository = new DiffArchiveRepository();
        $this->assertSame('First revision', $repository->getArchivedContent(1, 1));

        $repository->archiveContent(1, 3, 'Third revision');

        $this->assertSame('Third revision', $repository->getArchivedContent(1, 3));
        $this->assertSame('Second revision', $repository->getArchivedContent(1, 2));
    }

    public function test_deleting_content_invalidates_the_cached_archive()
    {
        $this->database();
        $repository = new DiffArchiveRepository();
        $this->assertSame('First revision', $repository->getArchivedContent(1, 1));

        $repository->deleteArchivedContent(1, 1);

        $this->assertNull($repository->getArchivedContent(1, 1));
        $this->assertSame('Second revision', $repository->getArchivedContent(1, 2));
    }

    public function test_deleting_the_last_revision_does_not_return_cached_content()
    {
        $this->database();
        $repository = new DiffArchiveRepository();
        $repository->deleteArchivedContent(1, 1);
        $this->assertSame('Second revision', $repository->getArchivedContent(1, 2));
        $repository->deleteArchivedContent(1, 2);

        $this->expectException(ModelNotFoundException::class);
        $repository->getArchivedContent(1, 2);
    }
}
