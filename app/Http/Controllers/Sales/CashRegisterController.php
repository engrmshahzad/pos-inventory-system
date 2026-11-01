<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Services\CashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    public function __construct(protected CashRegisterService $service)
    {
    }

    /** Current shift summary + recent shifts. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $branch = currentBranch();
        $register = $branch ? CashRegister::openFor($user, $branch->id) : null;

        $history = CashRegister::with(['user:id,name', 'branch:id,name,code'])
            ->when(! $user->isOwner(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($user->isOwner() && $branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->when($user->isCashier(), fn ($q) => $q->where('user_id', $user->id))
            ->latest('id')
            ->paginate(10);

        return view('register.index', [
            'register' => $register,
            'summary' => $register ? $this->service->summary($register) : null,
            'history' => $history,
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $branch = currentBranch() ?? abort(422, 'Choose a branch first.');
        $data = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'opening_note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->open($request->user(), $branch->id, (float) $data['opening_cash'], $data['opening_note'] ?? null);

        return redirect()->to($request->input('redirect') === 'register' ? route('register.index') : route('pos.index'))
            ->with('success', 'Shift opened. Happy selling!');
    }

    public function close(Request $request, CashRegister $register): RedirectResponse
    {
        abort_unless($register->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'closing_note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->service->close($register, (float) $data['counted_cash'], $data['closing_note'] ?? null);

        return redirect()->route('register.report', $register)->with('success', 'Shift closed.');
    }

    public function report(Request $request, CashRegister $register): View
    {
        $user = $request->user();
        $allowed = $user->isOwner()
            || ($register->branch_id === $user->branch_id && ($user->isManager() || $register->user_id === $user->id));
        abort_unless($allowed, 403);

        return view('register.report', [
            'register' => $register->load(['user', 'branch']),
            'summary' => $this->service->summary($register),
        ]);
    }
}
