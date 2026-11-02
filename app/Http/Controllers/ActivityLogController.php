<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
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
        ]);
    }
}
