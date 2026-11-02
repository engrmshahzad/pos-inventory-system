<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Branch;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public const PRESETS = [
        'today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week', 'this_month' => 'This month',
        'last_month' => 'Last month', 'last_30' => 'Last 30 days', 'this_year' => 'This year', 'custom' => 'Custom',
    ];

    public function index(Request $request): View
    {
        return view('reports.index', ['groups' => ReportRegistry::forUser($request->user())->groupBy('group')]);
    }

    public function show(Request $request, string $key): View
    {
        [$report, $ctx] = $this->prepare($request, $key);
        $result = $report->run($ctx);

        // Long tables are paged on screen; exports always contain every row.
        $page = max(1, $request->integer('page', 1));
        $rows = $result->statement ? null : new LengthAwarePaginator(
            $result->rows->forPage($page, 50)->values(), $result->rows->count(), 50, $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('reports.show', $this->viewData($report, $ctx, $result) + ['pagedRows' => $rows]);
    }

    public function export(Request $request, string $key, string $format)
    {
        [$report, $ctx] = $this->prepare($request, $key);
        $result = $report->run($ctx);
        $data = $this->viewData($report, $ctx, $result);
        $file = $report->key.'-'.$ctx->from->format('Ymd').'-'.$ctx->to->format('Ymd');

        activity('exported', "Exported report \"{$report->title}\" as {$format}");

        return match ($format) {
            'xlsx' => Excel::download(new ReportExport($report->title, $data['subtitle'], $result), "{$file}.xlsx"),
            'pdf' => Pdf::loadView('reports.print', $data + ['pdf' => true])
                ->setPaper('a4', count($result->columns) > 7 ? 'landscape' : 'portrait')->stream("{$file}.pdf"),
            default => view('reports.print', $data + ['pdf' => false]),
        };
    }

    /** @return array{0: Report, 1: ReportContext} */
    protected function prepare(Request $request, string $key): array
    {
        $user = $request->user();
        $report = ReportRegistry::find($key) ?? abort(404);
        abort_unless($report->allowedFor($user), 403);

        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $preset = $request->input('preset', $request->filled('from') ? 'custom' : 'this_month');
        [$from, $to] = $this->range($preset, $request);

        $branchId = $user->isOwner()
            ? ($request->has('branch') ? ($request->input('branch') === 'all' ? null : $request->integer('branch')) : currentBranchId())
            : $user->branch_id;

        $params = [];
        foreach ($report->filters() as $name => [, , , $default]) {
            $params[$name] = $request->input($name, $default);
        }

        return [$report, new ReportContext($from, $to, $branchId, $user, $params, $user->can('reports.profit'), $preset)];
    }

    protected function range(string $preset, Request $request): array
    {
        return match ($preset) {
            'today' => [today(), today()],
            'yesterday' => [today()->subDay(), today()->subDay()],
            'this_week' => [today()->startOfWeek(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'last_30' => [today()->subDays(29), today()],
            'this_year' => [today()->startOfYear(), today()],
            'custom' => [$request->filled('from') ? Carbon::parse($request->input('from')) : today()->startOfMonth(), $request->filled('to') ? Carbon::parse($request->input('to')) : today()],
            default => [today()->startOfMonth(), today()],
        };
    }

    protected function viewData(Report $report, ReportContext $ctx, $result): array
    {
        $branch = $ctx->branchId ? Branch::find($ctx->branchId) : null;
        $period = $report->usesDates ? fmtDate($ctx->from).' – '.fmtDate($ctx->to) : 'As of '.fmtDate(now(), true);

        return [
            'report' => $report,
            'ctx' => $ctx,
            'result' => $result,
            'branch' => $branch,
            'subtitle' => ($branch?->name ?? 'All branches').' · '.$period,
            'presets' => self::PRESETS,
            'branches' => $ctx->user->isOwner() ? Branch::orderBy('name')->get(['id', 'name']) : collect(),
        ];
    }
}
