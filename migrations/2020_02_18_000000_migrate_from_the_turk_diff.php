<?php

/**
 * This migration handles the namespace change from the-turk-diff to huseyinfiliz-diff.
 * It updates the migrations table to prevent data loss when upgrading from the original extension.
 */

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $connection = $schema->getConnection();
        
        // Check if there are any migrations from the old extension
        $oldMigrations = $connection->table('migrations')
            ->where('extension', 'the-turk-diff')
            ->count();
        
        if ($oldMigrations > 0) {
            // Update the extension name in migrations table to prevent re-running migrations
            // This preserves all existing data in post_edit_histories and post_edit_histories_archive
            $connection->table('migrations')
                ->where('extension', 'the-turk-diff')
                ->update(['extension' => 'huseyinfiliz-diff']);
        }
    },

    'down' => function (Builder $schema) {
        // Reverting this migration would cause issues, so we do nothing
        // If someone really needs to revert, they can manually update the migrations table
    },
];