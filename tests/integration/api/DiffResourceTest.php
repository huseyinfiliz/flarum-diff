<?php

namespace HuseyinFiliz\Diff\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Api\Endpoint\Show;
use Flarum\Api\Resource\PostResource;
use Flarum\Extend;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use HuseyinFiliz\Diff\Models\Diff;
use HuseyinFiliz\Diff\Models\DiffArchive;

class DiffResourceTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('huseyinfiliz-diff');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'moderator', 'email' => 'mod@machine.local', 'is_email_confirmed' => 1],
            ],
            Group::class => [
                ['id' => 4, 'name_singular' => 'Mod', 'name_plural' => 'Mods'],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 4],
            ],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'viewEditHistory'],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'selfDeleteEditHistory'],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'selfRollbackEditHistory'],
                ['group_id' => 4, 'permission' => 'viewEditHistory'],
                ['group_id' => 4, 'permission' => 'deleteEditHistory'],
                ['group_id' => 4, 'permission' => 'rollbackEditHistory'],
                ['group_id' => 4, 'permission' => 'discussion.hidePosts'],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Public Discussion', 'slug' => 'public-discussion', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 2, 'created_at' => Carbon::now()],
                ['id' => 2, 'title' => 'Hidden Discussion', 'slug' => 'hidden-discussion', 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'hidden_at' => Carbon::now(), 'created_at' => Carbon::now()],
            ],
            'posts' => [
                // Post 1: Public post with revisions
                [
                    'id' => 1,
                    'discussion_id' => 1,
                    'user_id' => 1,
                    'type' => 'comment',
                    'content' => '<t><p>Public post current content</p></t>',
                    'number' => 1,
                    'created_at' => Carbon::now()->subHours(2),
                    'edited_at' => Carbon::now()->subHour(),
                    'edited_user_id' => 1,
                ],
                // Post 2: Hidden post in public discussion
                [
                    'id' => 2,
                    'discussion_id' => 1,
                    'user_id' => 1,
                    'type' => 'comment',
                    'content' => '<t><p>Secret hidden post current content</p></t>',
                    'number' => 2,
                    'created_at' => Carbon::now()->subHours(2),
                    'edited_at' => Carbon::now()->subHour(),
                    'edited_user_id' => 1,
                    'hidden_at' => Carbon::now()->subMinutes(30),
                    'hidden_user_id' => 1,
                ],
            ],
            Diff::class => [
                // Revisions for public Post 1
                [
                    'id' => 1,
                    'post_id' => 1,
                    'revision' => 0,
                    'actor_id' => 1,
                    'content' => 'Public post original content',
                    'edit_reason' => 'HISTORICAL_REASON_MARKER',
                    'created_at' => Carbon::now()->subHours(2),
                ],
                [
                    'id' => 2,
                    'post_id' => 1,
                    'revision' => 1,
                    'actor_id' => 1,
                    'content' => null,
                    'created_at' => Carbon::now()->subHour(),
                ],
                // Revisions for hidden Post 2
                [
                    'id' => 3,
                    'post_id' => 2,
                    'revision' => 0,
                    'actor_id' => 1,
                    'content' => 'Secret original content',
                    'created_at' => Carbon::now()->subHours(2),
                ],
                [
                    'id' => 4,
                    'post_id' => 2,
                    'revision' => 1,
                    'actor_id' => 1,
                    'content' => null,
                    'created_at' => Carbon::now()->subHour(),
                ],
            ],
        ]);
    }

    public function test_guest_cannot_view_diffs()
    {
        $response = $this->send(
            $this->request('GET', '/api/diff')
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_user_can_view_revisions_of_public_post()
    {
        $response = $this->send(
            $this->request('GET', '/api/diff', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'filter' => ['post_id' => 1],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertCount(2, $body['data']);
    }

    public function test_guest_cannot_include_post_history()
    {
        $this->assertHistoryNotIncluded();
    }

    public function test_member_without_history_permission_cannot_include_post_history()
    {
        $this->revokeMemberHistoryPermission();

        $this->assertHistoryNotIncluded(2);
    }

    public function test_guest_cannot_include_history_in_post_lists()
    {
        $this->assertHistoryNotIncluded(null, '/api/posts', ['filter' => ['id' => '1']]);
    }

    public function test_member_without_history_permission_cannot_include_history_in_post_lists()
    {
        $this->revokeMemberHistoryPermission();

        $this->assertHistoryNotIncluded(2, '/api/posts', ['filter' => ['id' => '1']]);
    }

    public function test_guest_cannot_include_nested_post_history()
    {
        $this->assertHistoryNotIncluded(null, '/api/discussions/1', ['include' => 'firstPost.diff']);
    }

    public function test_member_without_history_permission_cannot_include_nested_post_history()
    {
        $this->revokeMemberHistoryPermission();

        $this->assertHistoryNotIncluded(2, '/api/discussions/1', ['include' => 'firstPost.diff']);
    }

    public function test_guest_cannot_include_preloaded_post_history()
    {
        $this->extend(
            (new Extend\ApiResource(PostResource::class))
                ->endpoint('show', fn (Show $endpoint) => $endpoint->eagerLoad(['diff']))
        );

        $this->assertHistoryNotIncluded();
    }

    public function test_authorized_member_can_include_post_history()
    {
        $response = $this->send(
            $this->request('GET', '/api/posts/1', ['authenticatedAs' => 2])
                ->withQueryParams(['include' => 'diff'])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertTrue($body['data']['attributes']['canViewEditHistory']);
        $this->assertCount(2, $body['data']['relationships']['diff']['data']);

        $revisions = array_values(array_filter($body['included'], fn (array $resource) => $resource['type'] === 'diff'));
        $this->assertCount(2, $revisions);
        $this->assertStringContainsString('Public post original content', $revisions[0]['attributes']['previewHtml']);
    }

    public function test_guest_cannot_include_archived_post_history()
    {
        $this->archiveOriginalRevision();

        $this->assertHistoryNotIncluded();
    }

    public function test_authorized_member_can_include_archived_post_history()
    {
        $this->archiveOriginalRevision();

        $response = $this->send(
            $this->request('GET', '/api/posts/1', ['authenticatedAs' => 2])
                ->withQueryParams(['include' => 'diff'])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Public post original content', (string) $response->getBody());
    }

    public function test_included_deleted_revision_does_not_disclose_its_content()
    {
        $this->database()->table('post_edit_histories')->where('id', 1)->update(['deleted_at' => Carbon::now()]);

        $response = $this->send(
            $this->request('GET', '/api/posts/1', ['authenticatedAs' => 2])
                ->withQueryParams(['include' => 'diff'])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('Public post original content', (string) $response->getBody());
    }

    public function test_member_without_history_permission_cannot_read_history_directly()
    {
        $this->revokeMemberHistoryPermission();

        foreach (['/api/diff', '/api/diff/1'] as $path) {
            $response = $this->send($this->request('GET', $path, ['authenticatedAs' => 2]));

            $this->assertEquals(403, $response->getStatusCode());
        }
    }

    public function test_post_edits_record_the_previous_content_and_a_new_revision()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 1,
                'json' => ['data' => ['attributes' => ['content' => 'Updated public post content']]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $revisions = $this->database()->table('post_edit_histories')
            ->where('post_id', 1)
            ->orderBy('revision')
            ->get();

        $this->assertCount(3, $revisions);
        $this->assertStringContainsString('Public post current content', $revisions[1]->content);
        $this->assertEquals(2, $revisions[2]->revision);
        $this->assertNull($revisions[2]->content);
    }

    public function test_history_permission_does_not_expose_hidden_posts_through_includes()
    {
        $response = $this->send(
            $this->request('GET', '/api/posts/2', ['authenticatedAs' => 2])
                ->withQueryParams(['include' => 'diff'])
        );

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringNotContainsString('Secret original content', (string) $response->getBody());
    }

    public function test_self_delete_permission_does_not_require_history_permission()
    {
        $this->revokeMemberHistoryPermission();
        $this->database()->table('posts')->where('id', 1)->update(['user_id' => 2]);

        $response = $this->send($this->request('DELETE', '/api/diff/1', ['authenticatedAs' => 2]));

        $this->assertEquals(204, $response->getStatusCode());
        $revision = $this->database()->table('post_edit_histories')->where('id', 1)->first();
        $this->assertNotNull($revision->deleted_at);
    }

    public function test_moderator_can_delete_another_authors_revision()
    {
        $response = $this->send($this->request('DELETE', '/api/diff/1', ['authenticatedAs' => 3]));

        $this->assertEquals(204, $response->getStatusCode());
        $revision = $this->database()->table('post_edit_histories')->where('id', 1)->first();
        $this->assertNotNull($revision->deleted_at);
    }

    public function test_member_cannot_delete_another_authors_revision()
    {
        $response = $this->send($this->request('DELETE', '/api/diff/1', ['authenticatedAs' => 2]));

        $this->assertEquals(403, $response->getStatusCode());
        $revision = $this->database()->table('post_edit_histories')->where('id', 1)->first();
        $this->assertNull($revision->deleted_at);
    }

    public function test_self_rollback_permission_does_not_require_history_permission()
    {
        $this->revokeMemberHistoryPermission();
        $this->database()->table('posts')->where('id', 1)->update(['user_id' => 2]);

        $response = $this->send($this->request('POST', '/api/diff/1', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $response->getStatusCode());
        $post = $this->database()->table('posts')->where('id', 1)->first();
        $this->assertStringContainsString('Public post original content', $post->content);
    }

    protected function revokeMemberHistoryPermission(): void
    {
        $this->database()->table('group_permission')
            ->where('group_id', Group::MEMBER_ID)
            ->where('permission', 'viewEditHistory')
            ->delete();
    }

    protected function archiveOriginalRevision(): void
    {
        $this->prepareDatabase([
            DiffArchive::class => [[
                'id' => 1,
                'post_id' => 1,
                'archive_no' => 1,
                'revision_count' => 1,
                'contents' => gzcompress(json_encode([1 => 'Public post original content'])),
            ]],
        ]);

        $this->database()->table('post_edit_histories')->where('id', 1)->update(['content' => null, 'archive_id' => 1]);
    }

    protected function assertHistoryNotIncluded(?int $actor = null, string $path = '/api/posts/1', array $query = []): void
    {
        $response = $this->send(
            $this->request('GET', $path, $actor === null ? [] : ['authenticatedAs' => $actor])
                ->withQueryParams(array_merge(['include' => 'diff'], $query))
        );

        $this->assertEquals(200, $response->getStatusCode());
        $json = (string) $response->getBody();
        $body = json_decode($json, true);
        $this->assertStringNotContainsString('Public post original content', $json);
        $this->assertStringNotContainsString('Secret original content', $json);
        $this->assertStringNotContainsString('HISTORICAL_REASON_MARKER', $json);

        $resources = isset($body['data']['type']) ? [$body['data']] : $body['data'];
        $resources = array_merge($resources, $body['included'] ?? []);
        $postCount = 0;

        foreach ($resources as $resource) {
            $this->assertNotEquals('diff', $resource['type']);

            if ($resource['type'] === 'posts') {
                $postCount++;
                $this->assertFalse($resource['attributes']['canViewEditHistory']);
                $this->assertArrayNotHasKey('diff', $resource['relationships'] ?? []);
            }
        }

        $this->assertGreaterThan(0, $postCount);
    }

    public function test_user_cannot_view_revisions_of_hidden_post_via_index()
    {
        // Normal user (ID 2) has viewEditHistory permission, but cannot view hidden post 2
        $response = $this->send(
            $this->request('GET', '/api/diff', [
                'authenticatedAs' => 2,
            ])->withQueryParams([
                'filter' => ['post_id' => 2],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        // Must be empty: user cannot see hidden post 2 revisions!
        $this->assertCount(0, $body['data']);
    }

    public function test_user_cannot_view_revisions_of_hidden_post_via_show()
    {
        // Normal user (ID 2) tries to directly fetch diff 3 (which belongs to hidden post 2)
        $response = $this->send(
            $this->request('GET', '/api/diff/3', [
                'authenticatedAs' => 2,
            ])
        );

        // Must be 404 Not Found because diff is scoped out by visibility
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_moderator_can_view_revisions_of_hidden_post()
    {
        // Moderator (ID 3) can view hidden posts
        $response = $this->send(
            $this->request('GET', '/api/diff', [
                'authenticatedAs' => 3,
            ])->withQueryParams([
                'filter' => ['post_id' => 2],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertCount(2, $body['data']);
    }

    public function test_moderator_can_show_revision_of_hidden_post()
    {
        $response = $this->send(
            $this->request('GET', '/api/diff/3', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals(3, $body['data']['id']);
    }

    public function test_user_cannot_delete_revision_of_unauthorized_post()
    {
        $response = $this->send(
            $this->request('DELETE', '/api/diff/3', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_user_cannot_rollback_revision_of_unauthorized_post()
    {
        $response = $this->send(
            $this->request('POST', '/api/diff/3', [
                'authenticatedAs' => 2,
            ])
        );

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }
}
