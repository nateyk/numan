<?php

namespace App\Http\Controllers\Freight;

use App\Http\Controllers\Controller;
use App\Services\RoutePreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoutePreviewController extends Controller
{
    public function preview(Request $request, RoutePreviewService $service): JsonResponse
    {
        $validated = $request->validate([
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],
            'to_lat' => ['required', 'numeric', 'between:-90,90'],
            'to_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $service->preview(
            (float) $validated['from_lat'],
            (float) $validated['from_lng'],
            (float) $validated['to_lat'],
            (float) $validated['to_lng']
        );

        if ($result === null) {
            return response()->json(['message' => 'Route unavailable.'], 422);
        }

        return response()->json($result);
    }
}
