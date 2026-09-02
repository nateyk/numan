<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CargoType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminCargoTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $query = CargoType::query()->ordered();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return Inertia::render('admin/reference-data/cargo-types/index', [
            'cargoTypes' => $query->paginate(15)->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cargo_types,name'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $base = $validated['slug'];
        $i = 1;
        while (CargoType::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $base.'-'.$i++;
        }

        CargoType::create($validated);

        return back()->with('success', 'Cargo type created.');
    }

    public function update(Request $request, CargoType $cargoType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:cargo_types,name,'.$cargoType->id],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $base = $validated['slug'];
        $i = 1;
        while (CargoType::where('slug', $validated['slug'])->where('id', '!=', $cargoType->id)->exists()) {
            $validated['slug'] = $base.'-'.$i++;
        }

        $cargoType->update($validated);

        return back()->with('success', 'Cargo type updated.');
    }

    public function destroy(CargoType $cargoType): RedirectResponse
    {
        $cargoType->delete();

        return back()->with('success', 'Cargo type deleted.');
    }

    public function toggle(CargoType $cargoType): RedirectResponse
    {
        $cargoType->update(['is_active' => ! $cargoType->is_active]);

        return back()->with('success', $cargoType->is_active ? 'Cargo type activated.' : 'Cargo type deactivated.');
    }
}
