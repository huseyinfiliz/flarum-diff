<?php

namespace TheTurk\Diff\Commands;

use Flarum\User\User;

class RollbackToDiff
{
    public function __construct(public User $actor, public int $diffId)
    {
    }
}
