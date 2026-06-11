<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) { }

    public function spotlight(Request $request): JsonResponse
    {
        $term = trim((string)$request->input('q', ''));

        if (strlen($term) < 2) return response()->json($this->search->emptyResponse());

        return response()->json($this->search->search($term));
    }
}
