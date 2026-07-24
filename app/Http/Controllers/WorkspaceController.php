<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function __construct(private readonly WorkspaceSearchService $workspace) {}

    /**
     * Search the records of a whitelisted resource across all of its columns.
     * Powers the landing-page "Your Workspace" record-pinning UI.
     */
    public function records(Request $request, string $resource): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        return response()->json(['data' => $this->workspace->search($resource, $term)]);
    }
}
