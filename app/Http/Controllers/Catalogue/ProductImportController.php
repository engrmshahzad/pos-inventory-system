<?php

namespace App\Http\Controllers\Catalogue;

use App\Exports\ProductTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\ProductsImport;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductImportController extends Controller
{
    public function create(): View
    {
        return view('products.import', [
            'columns' => array_merge(ProductTemplateExport::COLUMNS, ProductTemplateExport::stockColumns()),
            'report' => session('import_report'),
        ]);
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new ProductTemplateExport, 'products-import-template.xlsx');
    }

    public function store(Request $request, ProductService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        // Non-owners may only load opening stock into their own branch.
        $import = new ProductsImport($service, $request->user()->isOwner() ? null : $request->user()->branch_id);
        Excel::import($import, $request->file('file'));

        activity('imported', "Imported {$import->imported} products (".count($import->failures).' failed)');

        $type = $import->failures ? ($import->imported ? 'warning' : 'error') : 'success';

        return redirect()->route('products.import')
            ->with($type, "{$import->imported} product(s) imported, ".count($import->failures).' row(s) failed.')
            ->with('import_report', ['imported' => $import->imported, 'failures' => $import->failures]);
    }
}
