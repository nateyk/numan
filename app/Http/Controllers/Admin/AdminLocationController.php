<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminLocationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $query = Location::query()->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('region_name', 'like', "%{$search}%");
            });
        }

        $locations = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/reference-data/locations/index', [
            'locations' => $locations,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region_name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:city,town,woreda'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name'].'-'.$validated['region_name']);
        $base = $validated['slug'];
        $i = 1;
        while (Location::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $base.'-'.$i++;
        }

        Location::create($validated);

        return back()->with('success', 'Location created.');
    }

    public function update(Request $request, Location $location): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'region_name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:city,town,woreda'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['boolean'],
        ]);

        $validated['slug'] = Str::slug($validated['name'].'-'.$validated['region_name']);
        $base = $validated['slug'];
        $i = 1;
        while (Location::where('slug', $validated['slug'])->where('id', '!=', $location->id)->exists()) {
            $validated['slug'] = $base.'-'.$i++;
        }

        $location->update($validated);

        return back()->with('success', 'Location updated.');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return back()->with('success', 'Location deleted.');
    }

    public function toggle(Location $location): RedirectResponse
    {
        $location->update(['is_active' => ! $location->is_active]);

        return back()->with('success', $location->is_active ? 'Location activated.' : 'Location deactivated.');
    }
}
