<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    /**
     * Run the migrations.
     */
    'up' => function (Builder $schema) {
        // Safety check: If table exists and has data, don't touch it
        // This protects existing revision history from namespace change issues
        if ($schema->hasTable('post_edit_histories')) {
            $connection = $schema->getConnection();
            $count = $connection->table('post_edit_histories')->count();
            
            if ($count > 0) {
                // Table exists with data - this is likely an upgrade from the-turk/flarum-diff
                // Do NOT drop the table, just return to preserve data
                return;
            }
            
            // Table exists but is empty - safe to recreate for clean schema
            $schema->dropIfExists('post_edit_histories');
        }

        $schema->create('post_edit_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id');
            $table->unsignedInteger('actor_id')->nullable();
            $table->unsignedSmallInteger('revision');
            $table->dateTime('created_at');
            $table->mediumText('content')->collation('utf8mb4_unicode_ci')->nullable();
            $table->unsignedInteger('deleted_user_id')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedInteger('rollbacked_user_id')->nullable();
            $table->dateTime('rollbacked_at')->nullable();
            // we can use this column later to find out
            // which revision created after rollback happened
            $table->unsignedInteger('rollbacked_to')->nullable();
            $table->unsignedInteger('archive_id')->nullable();
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null')->onUpdate('set null');
            $table->foreign('deleted_user_id')->references('id')->on('users')->onDelete('set null')->onUpdate('set null');
            $table->foreign('rollbacked_user_id')->references('id')->on('users')->onDelete('set null')->onUpdate('set null');
        });

        // workaround for self-referencing
        $schema->table('post_edit_histories', function (Blueprint $table) {
            $table->foreign('rollbacked_to')->references('id')->on('post_edit_histories')->onUpdate('set null')->onDelete('set null');
        });
    },

    /**
     * Reverse the migrations.
     */
    'down' => function (Builder $schema) {
        $schema->dropIfExists('post_edit_histories');
    },
];