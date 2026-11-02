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

    /** Exports above this many rows are built in the background (queue) instead of during the request. */
    public const QUEUE_THRESHOLD = 2000;

    /** Larger PDFs run out of memory; the user is pointed to Excel / Print instead. */
    public const PDF_MAX_ROWS = 500;

    public function export(Request $request, string $key, string $format)
    {
        [$report, $ctx] = $this->prepare($request, $key);
        $result = $report->run($ctx);
        $data = $this->viewData($report, $ctx, $result);
        $file = $report->key.'-'.$ctx->from->format('Ymd').'-'.$ctx->to->format('Ymd');

        // dompdf needs ~0.3 MB per table row; 1,500 rows exhausted a 512 MB limit.
        if ($format === 'pdf' && $result->rows->count() > self::PDF_MAX_ROWS) {
            return back()->with('error', number_format($result->rows->count()).' rows is too many for a PDF (max '.number_format(self::PDF_MAX_ROWS).'). Use Excel or Print, or choose a shorter date range.');
        }

        activity('exported', "Exported report \"{$report->title}\" as {$format}");

        if ($format === 'xlsx' && $result->rows->count() > self::QUEUE_THRESHOLD) {
            \App\Jobs\ExportReportJob::dispatch($report->key, $ctx->user->id, $ctx->from->toDateString(), $ctx->to->toDateString(),
                $ctx->branchId, $ctx->params, $ctx->preset, $data['subtitle']);

            return back()->with('info', number_format($result->rows->count()).' rows — the Excel file is being prepared. You will get a notification with the download link.');
        }

        return match ($format) {
            'xlsx' => Excel::download(new ReportExport($report->title, $data['subtitle'], $result), "{$file}.xlsx"),
            'pdf' => Pdf::loadView('reports.print', $data + ['pdf' => true])
                ->setPaper('a4', count($result->columns) > 7 ? 'landscape' : 'portrait')->stream("{$file}.pdf"),
            default => view('reports.print', $data + ['pdf' => false]),
        };
    }

    /** Download a finished background export (only the user who requested it). */
    public function download(Request $request, string $file)
    {
        abort_unless(preg_match('/^u(\d+)-[\w\-]+\.xlsx$/', $file, $m) === 1 && (int) $m[1] === $request->user()->id, 404);
        abort_unless(\Illuminate\Support\Facades\Storage::disk('local')->exists("exports/{$file}"), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download("exports/{$file}");
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
