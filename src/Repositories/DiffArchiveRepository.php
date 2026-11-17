<?php

namespace HuseyinFiliz\Diff\Repositories;

use HuseyinFiliz\Diff\Models\DiffArchive;
use Illuminate\Support\Arr;

class DiffArchiveRepository
{
    /** @var array<int, array<int, string>> */
    protected array $contentsCache = [];

    /**
     * Get a new query builder.
     *
     * @return Builder
     */
    public function query()
    {
        return DiffArchive::query();
    }

    /**
     * Compress and archive the revision.
     * Archived revision columns are just compressed arrays
     * consists of [revisionId] => [revisionContent] for each revision.
     * We're uncompressing them, merging with new revision
     * and compressing them again altogether.
     *
     * @param int    $postId
     * @param int    $diffId
     * @param string $diff
     */
    public function archiveContent(int $postId, int $diffId, string $diff)
    {
        $query = $this->query()->where('post_id', $postId);

        if ($query->exists()) {
            $lastArchiveNo = $query->max('archive_no');
            $lastArchive = $query->where('archive_no', $lastArchiveNo)->firstOrFail();

            // add new revision to archive and
            // compress it altogether with other revisions
            $newContent = $this->setContents(
                Arr::prepend(
                    $this->getContents($lastArchive->contents),
                    $diff,
                    $diffId
                )
            );

            // we'll create a new row for the post in the archive table
            // if this new addition exceeds MEDIUMBLOB
            // which can store max. (2^24 - 1) bytes
            if (mb_strlen($newContent, '8bit') > (2 ** 24) - 1) {
                $query = new DiffArchive();
                $query->post_id = $postId;
                $query->archive_no = $lastArchiveNo + 1;
            } else {
                $query = $lastArchive;
                $query->revision_count = $query->revision_count + 1;
            }

            $query->contents = $newContent;
        } else {
            // create a new row for the post
            $query = new DiffArchive();
            $query->post_id = $postId;

            // compress revision contents
            // [revisionId] => [revisionContent]
            $query->contents = $this->setContents(
                [
                    $diffId => $diff,
                ]
            );
        }

        $query->save();
        unset($this->contentsCache[$query->id]);

        return $query;
    }

    /**
     * Get and uncompress archived revision.
     * This is like looking for a needle in a haystack.
     *
     * @param int $archiveId
     * @param int $diffId
     *
     * @return string
     */
    public function getArchivedContent(int $archiveId, int $diffId)
    {
        if (! array_key_exists($archiveId, $this->contentsCache)) {
            $query = $this->query()->findOrFail($archiveId);
            $this->contentsCache[$archiveId] = $this->getContents($query->contents);
        }

        return Arr::get(
            $this->contentsCache[$archiveId],
            $diffId,
            null
        );
    }

    /**
     * Delete archived revision.
     *
     * @param int $archiveId
     * @param int $diffId
     */
    public function deleteArchivedContent(int $archiveId, int $diffId)
    {
        $query = $this->query()->findOrFail($archiveId);

        // revision count after deletion
        $newRevisionCount = $query->revision_count - 1;

        // if there will be no revisions after deleting this,
        // then it's meanless to keep post's row.
        // so we're deleting row instead of deleting the revision
        // in archive contents.
        if (! ($newRevisionCount > 0)) {
            $deleted = $query->delete();
            unset($this->contentsCache[$archiveId]);

            return $deleted;
        }

        // uncompress the compressed content.
        $contents = $this->getContents($query->contents);

        // delete revision using its id.
        Arr::forget($contents, $diffId);

        // compress other revisions again.
        $query->contents = $this->setContents($contents);

        // set new revision count.
        $query->revision_count = $newRevisionCount;

        $saved = $query->save();
        unset($this->contentsCache[$archiveId]);

        return $saved;
    }

    /**
     * Uncompress the compressed content.
     *
     * @param string $contents
     *
     * @return array
     */
    public function getContents(string $contents)
    {
        return json_decode(\gzuncompress($contents), true);
    }

    /**
     * Compress the array content.
     *
     * @param array $contents
     *
     * @return string
     */
    public function setContents(array $contents)
    {
        return \gzcompress(json_encode($contents), -1);
    }
}
