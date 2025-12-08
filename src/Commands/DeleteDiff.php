<?php

namespace TheTurk\Diff\Commands;

use Flarum\User\User;

class DeleteDiff
{
    public function __construct(public User $actor, public int $diffId)
    {
    }
}
