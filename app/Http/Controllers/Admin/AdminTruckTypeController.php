<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TruckType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminTruckTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $query = TruckType::query()->ordered();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return Inertia::render('admin/reference-data/truck-types/index', [
            'truckTypes' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:truck_types,name'],
            'is_active' => ['boolean'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $base = $validated['slug'];
        $i = 1;
        while (TruckType::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $base.'-'.$i++;
        }

        TruckType::create($validated);

        return back()->with('success', 'Truck type created.');
    }

    public function update(Request $request, TruckType $truckType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:truck_types,name,'.$truckType->id],
            'is_active' => ['boolean'],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $base = $validated['slug'];
        $i = 1;
        while (TruckType::where('slug', $validated['slug'])->where('id', '!=', $truckType->id)->exists()) {
            $validated['slug'] = $base.'-'.$i++;
        }

        $truckType->update($validated);

        return back()->with('success', 'Truck type updated.');
    }

    public function destroy(TruckType $truckType): RedirectResponse
    {
        $truckType->delete();

        return back()->with('success', 'Truck type deleted.');
    }

    public function toggle(TruckType $truckType): RedirectResponse
    {
        $truckType->update(['is_active' => ! $truckType->is_active]);

        return back()->with('success', $truckType->is_active ? 'Truck type activated.' : 'Truck type deactivated.');
    }
}
