<?php

namespace HuseyinFiliz\Diff\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Post\CommentPost;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use HuseyinFiliz\Diff\Models\Diff;

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
                ['id' => 1, 'title' => 'Public Discussion', 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 2, 'created_at' => Carbon::now()],
                ['id' => 2, 'title' => 'Hidden Discussion', 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'hidden_at' => Carbon::now(), 'created_at' => Carbon::now()],
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

        $this->assertEquals(401, $response->getStatusCode());
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
