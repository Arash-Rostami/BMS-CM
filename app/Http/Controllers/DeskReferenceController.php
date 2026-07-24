<?php

namespace App\Http\Controllers;

use App\Models\DeskReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeskReferenceController extends Controller
{
    public function acknowledge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => 'required|string',
            'version' => 'required|integer',
        ]);

        $knownGroups = collect(config('desk-reference'))->pluck('group')->unique();
        abort_unless($knownGroups->contains($data['group']), 422);

        DeskReference::updateOrCreate(
            ['user_id' => auth()->id(), 'group_key' => $data['group']],
            ['version' => $data['version'], 'acknowledged_at' => now()],
        );

        return response()->json(['ok' => true]);
    }
}
