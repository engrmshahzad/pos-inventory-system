<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        // Dummy numbers until real data arrives in Phase 6.
        $kpis = [
            ['label' => "Today's Sales", 'value' => money(184250), 'icon' => 'banknote', 'color' => 'primary', 'trend' => 12.5],
            ['label' => "Today's Profit", 'value' => money(36820), 'icon' => 'trending-up', 'color' => 'success', 'trend' => 8.2],
            ['label' => "Today's Orders", 'value' => formatNumber(143), 'icon' => 'shopping-cart', 'color' => 'info', 'trend' => -3.4],
            ['label' => "Today's Expenses", 'value' => money(12400), 'icon' => 'wallet', 'color' => 'warning', 'trend' => 4.1],
        ];

        if ($request->user()->isCashier()) {
            unset($kpis[1]);
        }

        return view('dashboard', [
            'greeting' => $greeting,
            'kpis' => array_values($kpis),
            'chart' => [
                'labels' => collect(range(13, 0))->map(fn ($d) => now()->subDays($d)->format('d M'))->all(),
                'sales' => [142000, 156500, 138200, 171300, 165800, 189400, 176200, 158900, 194600, 201300, 187500, 210800, 198300, 184250],
                'profit' => [28400, 31300, 27600, 34200, 33100, 37800, 35200, 31700, 38900, 40200, 37500, 42100, 39600, 36820],
            ],
        ]);
    }
}
