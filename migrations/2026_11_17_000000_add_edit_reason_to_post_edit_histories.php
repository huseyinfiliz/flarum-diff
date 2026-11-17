<?php

use Flarum\Database\Migration;

return Migration::addColumns('post_edit_histories', [
    'edit_reason' => ['string', 'length' => 500, 'nullable' => true],
]);
