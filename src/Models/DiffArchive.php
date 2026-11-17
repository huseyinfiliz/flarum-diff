<?php

namespace HuseyinFiliz\Diff\Models;

use Flarum\Database\AbstractModel;

class DiffArchive extends AbstractModel
{
    public $timestamps = false;

    /**
     * {@inheritdoc}
     */
    protected $table = 'post_edit_histories_archive';

    public function setContentsAttribute(?string $value): void
    {
        if ($value === null || $this->getConnection()->getDriverName() !== 'pgsql') {
            $this->attributes['contents'] = $value;

            return;
        }

        // PostgreSQL bytea values must be bound as binary rather than UTF-8 text.
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $value);
        rewind($stream);
        $this->attributes['contents'] = $stream;
    }

    public function getContentsAttribute(mixed $value): ?string
    {
        if (is_resource($value)) {
            rewind($value);
            $contents = stream_get_contents($value);
            rewind($value);

            return $contents;
        }

        return $value;
    }
}
