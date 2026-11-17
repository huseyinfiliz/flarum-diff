<?php

namespace HuseyinFiliz\Diff\Tests\integration;

use HuseyinFiliz\Diff\Models\DiffArchive;

trait CreatesArchiveFixtures
{
    protected function createArchiveFixture(array $contents): void
    {
        $this->database();

        $archive = new DiffArchive();
        $archive->id = 1;
        $archive->post_id = 1;
        $archive->archive_no = 1;
        $archive->revision_count = count($contents);
        $archive->contents = gzcompress(json_encode($contents));
        $archive->save();
    }
}
