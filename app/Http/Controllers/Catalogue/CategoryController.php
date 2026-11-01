<?php

namespace App\Http\Controllers\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogue\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public const ICONS = ['package', 'shopping-cart', 'store', 'boxes', 'sparkles', 'zap', 'award', 'folder', 'receipt', 'truck', 'phone', 'sun'];

    public function index(Request $request): View
    {
        $search = $request->input('search');

        $roots = Category::roots()
            ->withCount('products')
            ->with(['children' => fn ($q) => $q->withCount('products')->orderBy('name')])
            ->when($search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhereHas('children', fn ($c) => $c->where('name', 'like', "%{$search}%"))))
            ->orderBy('name')
            ->get();

        return view('catalogue.categories', [
            'roots' => $roots,
            'parents' => Category::roots()->orderBy('name')->pluck('name', 'id'),
            'icons' => self::ICONS,
            'stats' => [
                'total' => Category::count(),
                'parents' => Category::roots()->count(),
                'active' => Category::active()->count(),
            ],
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" created.");
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" updated.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages(['action' => "\"{$category->name}\" has products. Move them to another category first."]);
        }
        if ($category->children()->exists()) {
            throw ValidationException::withMessages(['action' => "\"{$category->name}\" has sub-categories. Delete or move them first."]);
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', "Category \"{$category->name}\" deleted.");
    }
}
