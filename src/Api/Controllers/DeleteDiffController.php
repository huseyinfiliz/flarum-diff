<?php

namespace TheTurk\Diff\Api\Controllers;

use Flarum\Api\Controller\AbstractDeleteController;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use TheTurk\Diff\Api\Serializers\DiffSerializer;
use TheTurk\Diff\Commands\DeleteDiff;

/**
 * @TODO: Remove this in favor of one of the API resource classes that were added.
 *      Or extend an existing API Resource to add this to.
 *      Or use a vanilla RequestHandlerInterface controller.
 *      @link https://docs.flarum.org/2.x/extend/api#endpoints
 */
class DeleteDiffController extends AbstractDeleteController
{
    /**
     * {@inheritdoc}
     */
    public $serializer = DiffSerializer::class;

    public function __construct(protected Dispatcher $bus)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function delete(ServerRequestInterface $request): void
    {
        return $this->bus->dispatch(
            new DeleteDiff(
                $request->getAttribute('actor'),
                Arr::get($request->getQueryParams(), 'id')
            )
        );
    }
}
