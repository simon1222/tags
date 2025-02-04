<?php

/*
 * This file is part of Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Flarum\Tags\Content;

use Flarum\Api\Client;
use Flarum\Api\Controller\ListDiscussionsController;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Flarum\Tags\Api\Controller\ShowTagController;
use Flarum\Tags\TagRepository;
use Flarum\User\User;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface as Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class Tag
{
    /**
     * @var Client
     */
    protected $api;

    /**
     * @var Factory
     */
    protected $view;

    /**
     * @var TagRepository
     */
    protected $tags;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @param Client $api
     * @param Factory $view
     * @param TagRepository $tags
     * @param TranslatorInterface $translator
     */
    public function __construct(Client $api, Factory $view, TagRepository $tags, TranslatorInterface $translator)
    {
        $this->api = $api;
        $this->view = $view;
        $this->tags = $tags;
        $this->translator = $translator;
    }

    public function __invoke(Document $document, Request $request)
    {
        $queryParams = $request->getQueryParams();
        $actor = RequestUtil::getActor($request);

        $slug = Arr::pull($queryParams, 'slug');
        $sort = Arr::pull($queryParams, 'sort');
        $q = Arr::pull($queryParams, 'q', '');
        $page = Arr::pull($queryParams, 'page', 1);

        $sortMap = $this->getSortMap();

        $tagId = $this->tags->getIdForSlug($slug);
        $tag = $this->tags->findOrFail($tagId, $actor);

        $params = [
            'sort' => $sort && isset($sortMap[$sort]) ? $sortMap[$sort] : '',
            'filter' => [
                'tag' => "$slug"
            ],
            'page' => ['offset' => ($page - 1) * 20, 'limit' => 20]
        ];

        $apiDocument = $this->getApiDocument($actor, $params);

        $tagsDocument = $this->getTagsDocument($actor, $slug);

        $apiDocument->included[] = $tagsDocument->data;
        foreach ((array) $tagsDocument->included as $tag) {
            $apiDocument->included[] = $tag;
        }

        $document->title = $tag->name;
        if ($tag->description) {
            $document->meta['description'] = $tag->description;
        } else {
            $document->meta['description'] = $this->translator->trans('flarum-tags.forum.tag.meta_description_text', ['{tag}' => $tag->name]);
        }
        $document->content = $this->view->make('tags::frontend.content.tag', compact('apiDocument', 'page', 'tag'));
        $document->payload['apiDocument'] = $apiDocument;

        return $document;
    }

    /**
     * Get a map of sort query param values and their API sort params.
     *
     * @return array
     */
    private function getSortMap()
    {
        return [
            'latest' => '-lastPostedAt',
            'top' => '-commentCount',
            'newest' => '-createdAt',
            'oldest' => 'createdAt'
        ];
    }

    /**
     * Get the result of an API request to list discussions.
     *
     * @param User $actor
     * @param array $params
     * @return object
     */
    private function getApiDocument(User $actor, array $params)
    {
        return json_decode($this->api->send(ListDiscussionsController::class, $actor, $params)->getBody());
    }

    private function getTagsDocument(User $actor, string $slug)
    {
        return json_decode($this->api->send(ShowTagController::class, $actor, [
            'slug' => $slug,
            'include' => 'children,children.parent,parent,parent.children.parent,state'
        ])->getBody());
    }
}
