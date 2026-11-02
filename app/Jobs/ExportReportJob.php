<?php

namespace App\Jobs;

use App\Exports\ReportExport;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Reports\ReportContext;
use App\Reports\ReportRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/** Builds a large report's Excel file in the background, then notifies the user with a download link. */
class ExportReportJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public string $reportKey,
        public int $userId,
        public string $from,
        public string $to,
        public ?int $branchId,
        public array $params,
        public string $preset,
        public string $subtitle,
    ) {
    }

    public function handle(): void
    {
        $user = User::findOrFail($this->userId);
        $report = ReportRegistry::find($this->reportKey);

        if (! $report || ! $report->allowedFor($user)) {
            return;
        }

        $ctx = new ReportContext(Carbon::parse($this->from), Carbon::parse($this->to), $this->branchId, $user, $this->params, $user->can('reports.profit'), $this->preset);
        $result = $report->run($ctx);

        // The user id in the file name is how the download route checks ownership.
        $file = "u{$user->id}-{$report->key}-".now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.xlsx';
        Excel::store(new ReportExport($report->title, $this->subtitle, $result), "exports/{$file}", 'local');

        $user->notify(new AppNotification(
            'Your export is ready',
            "{$report->title} — ".number_format($result->rows->count()).' rows. Click to download.',
            route('exports.download', $file), 'download', 'success', 'export_ready',
        ));
    }
}
