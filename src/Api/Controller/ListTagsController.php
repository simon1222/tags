<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\Tags\Api\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Tags\Api\Serializer\TagSerializer;
use Flarum\Tags\Tag;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListTagsController extends AbstractListController
{
    /**
     * {@inheritdoc}
     */
    public $serializer = TagSerializer::class;

    /**
     * {@inheritdoc}
     */
    public $include = [
        'parent'
    ];

    /**
     * {@inheritdoc}
     */
    public $optionalInclude = [
        'lastPostedDiscussion',
        'children',
        'state'
    ];

    /**
     * @var \Flarum\Tags\Tag
     */
    protected $tags;

    /**
     * @param \Flarum\Tags\Tag $tags
     */
    public function __construct(Tag $tags)
    {
        $this->tags = $tags;
    }

    /**
     * {@inheritdoc}
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = $request->getAttribute('actor');
        $include = $this->extractInclude($request);

        if (in_array('lastPostedDiscussion', $include)) {
            $include = array_merge($include, ['lastPostedDiscussion.tags', 'lastPostedDiscussion.state']);
        }

        $tags = $this->tags->whereVisibleTo($actor)->withStateFor($actor)->get();

        return $tags->load($include);
    }
}
