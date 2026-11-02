<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\ExpenseService;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    public function run(ExpenseService $expenses): void
    {
        $categories = collect([
            ['Rent', '#6366F1'], ['Electricity', '#F59E0B'], ['Salaries', '#10B981'], ['Transport', '#0EA5E9'],
            ['Tea / refreshment', '#EC4899'], ['Maintenance', '#8B5CF6'], ['Other', '#64748B'],
        ])->mapWithKeys(fn ($c) => [$c[0] => ExpenseCategory::firstOrCreate(['name' => $c[0]], ['color' => $c[1], 'is_active' => true])->id]);

        if (Expense::exists()) {
            return;
        }

        mt_srand(11);
        $scale = ['HYD' => 1.0, 'KHI' => 1.6, 'SUK' => 0.7];

        foreach (Branch::orderBy('id')->get() as $branch) {
            $manager = User::role('manager')->where('branch_id', $branch->id)->first();
            $k = $scale[$branch->code] ?? 1;
            $add = fn ($date, $cat, $amount, $method, $paidTo, $note = null) => $date->lte(today())
                ? $expenses->create($manager, $branch, ['expense_category_id' => $categories[$cat], 'amount' => round($amount), 'expense_date' => $date->toDateString(), 'method' => $method, 'paid_to' => $paidTo, 'note' => $note])
                : null;

            foreach ([2, 1, 0] as $m) {
                $month = today()->subMonthsNoOverflow($m)->startOfMonth();
                $add($month->copy()->addDays(0), 'Rent', 12000 * $k, 'bank', 'Landlord', 'Shop rent '.$month->format('M Y'));
                $add($month->copy()->addDays(1), 'Salaries', 15000 * $k, 'bank', 'Staff', 'Salaries '.$month->format('M Y'));
                $add($month->copy()->addDays(9), 'Electricity', mt_rand(3500, 5500) * $k, 'bank', $branch->code === 'KHI' ? 'K-Electric' : ($branch->code === 'HYD' ? 'HESCO' : 'SEPCO'));
                $add($month->copy()->addDays(14), 'Maintenance', mt_rand(1000, 3000), 'cash', 'Electrician', 'AC / lights repair');
                foreach (range(0, 27, 7) as $d) {
                    $add($month->copy()->addDays($d + 2), 'Transport', mt_rand(400, 900) * $k, 'cash', 'Loader rickshaw');
                }
                foreach (range(0, 27, 2) as $d) {
                    $add($month->copy()->addDays($d), 'Tea / refreshment', mt_rand(150, 350), 'cash', 'Chai wala');
                }
            }
        }
    }
}
