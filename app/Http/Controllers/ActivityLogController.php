<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        $logs = ActivityLog::with(['user:id,name', 'branch:id,code'])
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('branch'), fn ($q) => $q->where('branch_id', $request->integer('branch')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('search'), fn ($q) => $q->where('description', 'like', '%'.$request->input('search').'%'))
            ->latest('id')
            ->paginate(50)->withQueryString();

        return view('activity.index', [
            'logs' => $logs,
            'users' => User::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'branches' => Branch::withTrashed()->orderBy('name')->pluck('name', 'id'),
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'clearOptions' => self::CLEAR_OPTIONS,
            'total' => ActivityLog::count(),
        ]);
    }

    /** Clear options: days to keep => label. */
    public const CLEAR_OPTIONS = ['30' => 'Older than 30 days', '90' => 'Older than 90 days', '180' => 'Older than 6 months', '365' => 'Older than 1 year', 'all' => 'Everything'];

    public function destroy(ActivityLog $log): RedirectResponse
    {
        $log->delete();
        activity('activity_deleted', 'Deleted 1 activity log entry');

        return back()->with('success', 'Entry deleted.');
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']])['ids'];
        $deleted = ActivityLog::whereIn('id', $ids)->delete();
        activity('activity_deleted', "Deleted {$deleted} activity log ".str('entry')->plural($deleted));

        return back()->with('success', "{$deleted} ".str('entry')->plural($deleted).' deleted.');
    }

    /** Needs the owner's password. Always leaves one entry saying who cleared what. */
    public function clear(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('clearLog', [
            'older_than' => ['required', Rule::in(array_keys(self::CLEAR_OPTIONS))],
            'password' => ['required', 'current_password'],
        ]);

        $deleted = ActivityLog::query()
            ->when($data['older_than'] !== 'all', fn ($q) => $q->where('created_at', '<', now()->subDays((int) $data['older_than'])))
            ->delete();

        $what = strtolower(self::CLEAR_OPTIONS[$data['older_than']]);
        activity('activity_cleared', "Activity log cleared ({$what}): ".number_format($deleted).' '.str('entry')->plural($deleted).' removed');

        return redirect()->route('activity.index')->with('success', number_format($deleted).' '.str('entry')->plural($deleted).' cleared.');
    }
}
