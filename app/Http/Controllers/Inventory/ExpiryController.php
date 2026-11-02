<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\ExpiryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpiryController extends Controller
{
    public function index(Request $request, ExpiryService $expiry): View
    {
        $days = $request->integer('days') ?: $expiry->warningDays();
        $batches = $expiry->batches(currentBranchId(), min(max($days, 1), 365));

        return view('expiry.index', [
            'expired' => $batches->where('expired', true)->values(),
            'soon' => $batches->where('expired', false)->values(),
            'days' => $days,
        ]);
    }
}
