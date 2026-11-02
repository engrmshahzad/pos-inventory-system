<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseCategoryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $category = ExpenseCategory::create($this->validated($request));

        return back()->with('success', "Category \"{$category->name}\" added.");
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $expenseCategory->update($this->validated($request, $expenseCategory));

        return back()->with('success', "Category \"{$expenseCategory->name}\" updated.");
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        if ($expenseCategory->expenses()->withTrashed()->exists()) {
            throw ValidationException::withMessages(['action' => "\"{$expenseCategory->name}\" has expenses and cannot be deleted. Deactivate it instead."]);
        }
        $expenseCategory->delete();

        return back()->with('success', 'Category deleted.');
    }

    protected function validated(Request $request, ?ExpenseCategory $category = null): array
    {
        $request->merge(['is_active' => $request->boolean('is_active', true)]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories')->ignore($category)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['boolean'],
        ]);
    }
}
