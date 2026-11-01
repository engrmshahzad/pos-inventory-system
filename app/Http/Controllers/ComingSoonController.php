<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ComingSoonController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('coming-soon', [
            'module' => $request->route()->defaults['module'] ?? 'This module',
            'phase' => $request->route()->defaults['phase'] ?? null,
            'icon' => $request->route()->defaults['icon'] ?? 'sparkles',
        ]);
    }
}
