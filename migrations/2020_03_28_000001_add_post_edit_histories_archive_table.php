<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    /**
     * Run the migrations.
     */
    'up' => function (Builder $schema) {
        // Safety check: If archive table exists and has data, don't touch it
        // This protects existing archived revisions from namespace change issues
        if ($schema->hasTable('post_edit_histories_archive')) {
            $connection = $schema->getConnection();
            $count = $connection->table('post_edit_histories_archive')->count();
            
            if ($count > 0) {
                // Table exists with data - this is an upgrade from the-turk/flarum-diff
                // Do NOT drop the table, preserve all archived data
                return;
            }
            
            // Table exists but is empty - safe to recreate for clean schema
            // But first, we need to drop the foreign key from post_edit_histories
            if ($schema->hasTable('post_edit_histories')) {
                try {
                    $schema->table('post_edit_histories', function (Blueprint $table) {
                        // Drop foreign key constraint first
                        $table->dropForeign(['archive_id']);
                    });
                } catch (\Exception $e) {
                    // Foreign key might not exist or have different name, try alternate names
                    try {
                        $schema->table('post_edit_histories', function (Blueprint $table) {
                            $table->dropForeign('post_edit_histories_archive_id_foreign');
                        });
                    } catch (\Exception $e2) {
                        // FK doesn't exist, that's fine
                    }
                }
            }
            
            $schema->dropIfExists('post_edit_histories_archive');
        }

        $schema->create('post_edit_histories_archive', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id')->unsigned();
            $table->unsignedTinyInteger('archive_no')->unsigned()->default('1');
            // total revisions inside this archive (including revision 0 - the original content)
            $table->unsignedSmallInteger('revision_count')->unsigned()->default('1');
            $table->binary('contents')->nullable();
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade')->onUpdate('cascade');
        });

        // Add foreign key to post_edit_histories if table exists and doesn't have this FK yet
        if ($schema->hasTable('post_edit_histories')) {
            // Check if archive_id column exists
            if ($schema->hasColumn('post_edit_histories', 'archive_id')) {
                try {
                    $schema->table('post_edit_histories', function (Blueprint $table) {
                        $table->foreign('archive_id')->references('id')->on('post_edit_histories_archive')->onUpdate('set null')->onDelete('set null');
                    });
                } catch (\Exception $e) {
                    // Foreign key likely already exists, which is fine
                }
            }
        }

        // workaround for creating MEDIUMBLOB type using Schemas
        $connection = $schema->getConnection();
        $prefix = $connection->getTablePrefix();
        $connection->statement('ALTER TABLE '.$prefix.'post_edit_histories_archive MODIFY contents MEDIUMBLOB');
    },

    /**
     * Reverse the migrations.
     */
    'down' => function (Builder $schema) {
        // Drop foreign key from post_edit_histories first
        if ($schema->hasTable('post_edit_histories')) {
            try {
                $schema->table('post_edit_histories', function (Blueprint $table) {
                    $table->dropForeign(['archive_id']);
                });
            } catch (\Exception $e) {
                // FK might not exist
            }
        }
        
        $schema->dropIfExists('post_edit_histories_archive');
    },
];