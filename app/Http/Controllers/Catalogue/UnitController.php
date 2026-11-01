<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\UnitRequest;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        return view('catalogue.units', ['units' => Unit::withCount('products')->orderBy('name')->get()]);
    }

    public function store(UnitRequest $request): RedirectResponse
    {
        $unit = Unit::create($request->validated());

        return redirect()->route('units.index')->with('success', "Unit \"{$unit->name}\" created.");
    }

    public function update(UnitRequest $request, Unit $unit): RedirectResponse
    {
        $unit->update($request->validated());

        return redirect()->route('units.index')->with('success', "Unit \"{$unit->name}\" updated.");
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->products()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['action' => "\"{$unit->name}\" is used by products and cannot be deleted."]);
        }

        $unit->delete();

        return redirect()->route('units.index')->with('success', "Unit \"{$unit->name}\" deleted.");
    }
}
