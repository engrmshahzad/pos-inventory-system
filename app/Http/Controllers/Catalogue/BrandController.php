<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\BrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $brands = Brand::withCount('products')
            ->when($request->input('search'), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->get();

        return view('catalogue.brands', ['brands' => $brands]);
    }

    public function store(BrandRequest $request): RedirectResponse
    {
        $brand = Brand::create(['name' => $request->validated('name')]);
        $this->saveLogo($request, $brand);

        return redirect()->route('brands.index')->with('success', "Brand \"{$brand->name}\" created.");
    }

    public function update(BrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update(['name' => $request->validated('name')]);
        $this->saveLogo($request, $brand);

        return redirect()->route('brands.index')->with('success', "Brand \"{$brand->name}\" updated.");
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->exists()) {
            throw ValidationException::withMessages(['action' => "\"{$brand->name}\" has products and cannot be deleted."]);
        }

        $brand->delete();

        return redirect()->route('brands.index')->with('success', "Brand \"{$brand->name}\" deleted.");
    }

    protected function saveLogo(BrandRequest $request, Brand $brand): void
    {
        if (! $request->hasFile('logo') && ! $request->boolean('remove_logo')) {
            return;
        }

        if ($brand->logo) {
            Storage::disk('public')->delete($brand->logo);
        }

        $brand->update(['logo' => $request->file('logo')?->store('brands', 'public')]);
    }
}
