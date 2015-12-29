<?php

namespace FalconSearch\Http\Controllers;

use FalconSearch\Http\Requests;
use FalconSearch\Services\SearchService;
use Symfony\Component\HttpFoundation\JsonResponse;

class SearchController extends Controller
{

    /**
     * @var SearchService
     */
    protected $search;

    /**
     * SearchController constructor.
     *
     * @param SearchService $search
     */
    public function __construct(SearchService $search)
    {
        $this->search = $search;
    }

    public function search(Requests\SearchRequest $request)
    {
        $results = $this->search->searchDocs($request->getQuery(), $request->getPage());

        return JsonResponse::create($results);
    }
    
}
