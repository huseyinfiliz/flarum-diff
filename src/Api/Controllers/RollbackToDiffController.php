<?php

namespace TheTurk\Diff\Api\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use TheTurk\Diff\Api\Serializers\DiffSerializer;
use TheTurk\Diff\Commands\RollbackToDiff;
use Tobscure\JsonApi\Document;

/**
 * @TODO: Remove this in favor of one of the API resource classes that were added.
 *      Or extend an existing API Resource to add this to.
 *      Or use a vanilla RequestHandlerInterface controller.
 *      @link https://docs.flarum.org/2.x/extend/api#endpoints
 */
class RollbackToDiffController extends AbstractShowController
{
    /**
     * {@inheritdoc}
     */
    public $serializer = DiffSerializer::class;

    public function __construct(protected Dispatcher $bus)
    {
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        return $this->bus->dispatch(
            new RollbackToDiff(
                $request->getAttribute('actor'),
                Arr::get($request->getQueryParams(), 'id')
                // I could do that but can't rely on this value
                // Arr::get($request->getParsedBody(), 'maxRevisionCount')
            )
        );
    }
}
