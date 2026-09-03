<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Koperasi;
use App\Models\SystemOwnerAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'actor_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'koperasi_id' => ['nullable', 'integer', Rule::exists('koperasi', 'id')],
            'action' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'response_status' => ['nullable', 'integer', 'between:100,599'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $logs = SystemOwnerAuditLog::query()
            ->with(['actor:id,name,email', 'koperasi:id,nama'])
            ->when($filters['actor_user_id'] ?? null, fn ($query, $actorId) => $query->where('actor_user_id', $actorId))
            ->when($filters['koperasi_id'] ?? null, fn ($query, $koperasiId) => $query->where('koperasi_id', $koperasiId))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', 'like', '%'.$action.'%'))
            ->when($filters['method'] ?? null, fn ($query, $method) => $query->where('route', 'like', $method.' %'))
            ->when($filters['response_status'] ?? null, fn ($query, $status) => $query->where('response_status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('owner.activity-logs.index', [
            'logs' => $logs,
            'owners' => User::query()
                ->whereHas('roles', fn ($query) => $query->where('name', 'system_owner'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'koperasis' => Koperasi::query()->orderBy('nama')->get(['id', 'nama']),
        ]);
    }
}
