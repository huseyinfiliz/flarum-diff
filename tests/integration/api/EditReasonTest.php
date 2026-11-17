<?php

namespace HuseyinFiliz\Diff\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use HuseyinFiliz\Diff\Models\Diff;
use HuseyinFiliz\Diff\Tests\integration\CreatesArchiveFixtures;

class EditReasonTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use CreatesArchiveFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-diff');
        $this->setting('allow_post_editing', '-1');
        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'editor', 'email' => 'editor@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'reader', 'email' => 'reader@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [['id' => 4, 'name_singular' => 'Editor', 'name_plural' => 'Editors']],
            'group_user' => [['user_id' => 3, 'group_id' => 4]],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'viewEditHistory'],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'discussion.reply'],
                ['group_id' => 4, 'permission' => 'discussion.editPosts'],
            ],
            'discussions' => [[
                'id' => 1,
                'title' => 'Edit reason fixture',
                'slug' => 'edit-reason-fixture',
                'user_id' => 2,
                'first_post_id' => 1,
                'comment_count' => 1,
                'created_at' => Carbon::now()->subHours(2),
            ]],
            'posts' => [[
                'id' => 1,
                'discussion_id' => 1,
                'number' => 1,
                'user_id' => 2,
                'type' => 'comment',
                'content' => '<t>Current public content</t>',
                'created_at' => Carbon::now()->subHours(2),
                'edited_at' => Carbon::now()->subHour(),
            ]],
            Diff::class => [
                ['id' => 1, 'post_id' => 1, 'actor_id' => 2, 'revision' => 0, 'content' => 'Original content', 'created_at' => Carbon::now()->subHours(2)],
                ['id' => 2, 'post_id' => 1, 'actor_id' => 2, 'revision' => 1, 'content' => null, 'edit_reason' => 'Earlier reason', 'created_at' => Carbon::now()->subHour()],
            ],
        ]);
    }

    public function test_author_reason_is_trimmed_and_attached_only_to_the_new_revision()
    {
        $this->edit(['content' => 'Revised content', 'editReason' => '  Clarify the explanation  ']);

        $latest = $this->latestRevision();
        $this->assertEquals(2, $latest->revision);
        $this->assertSame('Clarify the explanation', $latest->edit_reason);
        $this->assertEquals(2, $latest->actor_id);
        $this->assertSame('Earlier reason', $this->database()->table('post_edit_histories')->where('id', 2)->value('edit_reason'));

        $response = $this->send($this->request('GET', '/api/diff/'.$latest->id, ['authenticatedAs' => 2]));
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Clarify the explanation', $body['data']['attributes']['editReason']);
    }

    public function test_moderator_reason_is_recorded_with_the_editing_actor()
    {
        $this->edit(['content' => 'Moderated content', 'editReason' => 'Remove an off-topic sentence'], 3);

        $latest = $this->latestRevision();
        $this->assertEquals(3, $latest->actor_id);
        $this->assertSame('Remove an off-topic sentence', $latest->edit_reason);
    }

    public function test_first_edit_leaves_the_original_revision_without_a_reason()
    {
        $this->database()->table('post_edit_histories')->where('post_id', 1)->delete();
        $this->edit(['content' => 'First recorded edit', 'editReason' => 'Explain the first change']);

        $revisions = $this->database()->table('post_edit_histories')->where('post_id', 1)->orderBy('revision')->get();
        $this->assertCount(2, $revisions);
        $this->assertEquals(0, $revisions[0]->revision);
        $this->assertNull($revisions[0]->edit_reason);
        $this->assertSame('Explain the first change', $revisions[1]->edit_reason);
    }

    public function test_edit_without_a_reason_does_not_inherit_the_previous_reason()
    {
        $this->edit(['content' => 'Revised without a reason']);

        $this->assertNull($this->latestRevision()->edit_reason);
    }

    public function test_blank_reason_is_stored_as_null()
    {
        $this->edit(['content' => 'Revised with whitespace', 'editReason' => " \n\t "]);

        $this->assertNull($this->latestRevision()->edit_reason);
    }

    public function test_null_reason_is_accepted()
    {
        $this->edit(['content' => 'Revised with null', 'editReason' => null]);

        $this->assertNull($this->latestRevision()->edit_reason);
    }

    public function test_reason_accepts_500_unicode_characters()
    {
        $reason = str_repeat('é', 500);
        $this->edit(['content' => 'Unicode reason', 'editReason' => $reason]);

        $this->assertSame($reason, $this->latestRevision()->edit_reason);
    }

    public function test_oversized_reason_is_rejected_without_creating_a_revision()
    {
        $this->edit(['content' => 'Invalid edit', 'editReason' => str_repeat('x', 501)], 2, 422);

        $this->assertEquals(2, $this->database()->table('post_edit_histories')->count());
        $this->assertSame('<t>Current public content</t>', $this->database()->table('posts')->where('id', 1)->value('content'));
    }

    public function test_non_string_reason_is_rejected()
    {
        $this->edit(['content' => 'Invalid edit', 'editReason' => ['unexpected']], 2, 422);

        $this->assertEquals(2, $this->database()->table('post_edit_histories')->count());
    }

    public function test_reason_does_not_grant_permission_to_edit_another_authors_post()
    {
        $this->edit(['content' => 'Unauthorized edit', 'editReason' => 'Not permitted'], 4, 403);

        $this->assertEquals(2, $this->database()->table('post_edit_histories')->count());
    }

    public function test_editors_without_history_permission_cannot_read_recorded_reasons()
    {
        $this->database()->table('group_permission')
            ->where('group_id', Group::MEMBER_ID)
            ->where('permission', 'viewEditHistory')
            ->delete();
        $this->edit(['content' => 'Edited without history access', 'editReason' => 'Protected reason marker']);

        $latest = $this->latestRevision();
        $this->assertSame('Protected reason marker', $latest->edit_reason);

        $response = $this->send(
            $this->request('GET', '/api/posts/1', ['authenticatedAs' => 2])->withQueryParams(['include' => 'diff'])
        );
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Protected reason marker', (string) $response->getBody());

        $response = $this->send($this->request('GET', '/api/diff/'.$latest->id, ['authenticatedAs' => 2]));
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_reason_alone_does_not_create_or_rewrite_a_revision()
    {
        $this->edit(['editReason' => 'Should not overwrite history']);

        $this->assertEquals(2, $this->database()->table('post_edit_histories')->count());
        $this->assertSame('Earlier reason', $this->latestRevision()->edit_reason);
    }

    public function test_unchanged_content_does_not_create_or_rewrite_a_revision()
    {
        $this->edit(['content' => 'Current public content', 'editReason' => 'No actual changes']);

        $this->assertEquals(2, $this->database()->table('post_edit_histories')->count());
        $this->assertSame('Earlier reason', $this->latestRevision()->edit_reason);
    }

    public function test_deleted_revision_reason_is_removed_and_not_serialized()
    {
        $response = $this->send($this->request('DELETE', '/api/diff/2', ['authenticatedAs' => 1]));
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull($this->database()->table('post_edit_histories')->where('id', 2)->value('edit_reason'));

        $response = $this->send($this->request('GET', '/api/diff/2', ['authenticatedAs' => 2]));
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertNull($body['data']['attributes']['editReason']);
        $this->assertStringNotContainsString('Earlier reason', (string) $response->getBody());
    }

    public function test_archiving_content_preserves_the_revision_reason()
    {
        $this->createArchiveFixture([2 => 'Archived revision content']);
        $this->database()->table('post_edit_histories')->where('id', 2)->update(['archive_id' => 1]);

        $response = $this->send($this->request('GET', '/api/diff/2', ['authenticatedAs' => 2]));
        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Earlier reason', $body['data']['attributes']['editReason']);
        $this->assertStringContainsString('Archived revision content', $body['data']['attributes']['previewHtml']);
    }

    public function test_rollback_does_not_copy_the_target_revisions_reason()
    {
        $this->database()->table('post_edit_histories')->where('id', 2)->update(['content' => 'Intermediate content']);
        $this->database()->table('post_edit_histories')->insert([
            'post_id' => 1, 'actor_id' => 2, 'revision' => 2, 'content' => null, 'created_at' => Carbon::now()->subMinutes(30),
        ]);

        $response = $this->send($this->request('POST', '/api/diff/2', ['authenticatedAs' => 1]));
        $this->assertEquals(200, $response->getStatusCode());
        $latest = $this->latestRevision();
        $this->assertEquals(3, $latest->revision);
        $this->assertNull($latest->edit_reason);
        $this->assertSame('Earlier reason', $this->database()->table('post_edit_histories')->where('id', 2)->value('edit_reason'));
    }

    public function test_main_post_only_setting_still_excludes_reply_revisions()
    {
        $this->setting('huseyinfiliz-diff.mainPostOnly', true);
        $this->database()->table('posts')->where('id', 1)->update(['number' => 2]);
        $this->edit(['content' => 'Updated reply', 'editReason' => 'Reply correction']);

        $this->assertEquals(2, $this->database()->table('post_edit_histories')->count());
    }

    public function test_reason_field_is_shown_by_default_in_forum_configuration()
    {
        $response = $this->send($this->request('GET', '/api'));

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['data']['attributes']['showDiffEditReason']);
    }

    public function test_hidden_reason_field_preserves_history_and_api_integrations()
    {
        $this->setting('huseyinfiliz-diff.showEditReason', '0');
        $response = $this->send($this->request('GET', '/api'));

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['data']['attributes']['showDiffEditReason']);

        $this->edit(['content' => 'Edited by an API integration', 'editReason' => 'Integration reason']);
        $this->assertSame('Integration reason', $this->latestRevision()->edit_reason);
        $this->assertSame('Earlier reason', $this->database()->table('post_edit_histories')->where('id', 2)->value('edit_reason'));
    }

    protected function edit(array $attributes, int $actor = 2, int $status = 200): void
    {
        $response = $this->send($this->request('PATCH', '/api/posts/1', [
            'authenticatedAs' => $actor,
            'json' => ['data' => ['attributes' => $attributes]],
        ]));

        $this->assertEquals($status, $response->getStatusCode(), (string) $response->getBody());
    }

    protected function latestRevision(): object
    {
        return $this->database()->table('post_edit_histories')->where('post_id', 1)->orderByDesc('revision')->first();
    }
}
