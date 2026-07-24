<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function spotlight(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));

        if (strlen($term) < 2) {
            return response()->json($this->search->emptyResponse());
        }

        return response()->json($this->search->search($term));
    }

    public function chain(Request $request): JsonResponse
    {
        $type = trim((string) $request->input('type', ''));
        $id = (int) $request->input('id', 0);

        if ($type === '' || $id <= 0) {
            return response()->json(['anchor' => null, 'chain' => []]);
        }

        return response()->json($this->search->chain($type, $id));
    }
}
