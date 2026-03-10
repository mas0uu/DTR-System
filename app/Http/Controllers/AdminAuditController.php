<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditController extends Controller
{
    public function index(): Response
    {
        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    'id' => $log->id,
                    'actor_id' => $log->actor_id,
                    'actor_name' => $log->actor?->name ?? 'System',
                    'actor_email' => $log->actor?->email,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'before_json' => $log->before_json,
                    'after_json' => $log->after_json,
                    'reason' => $log->reason,
                    'ip_address' => $log->ip_address,
                    'created_at' => optional($log->created_at)?->toDateTimeString(),
                ];
            });

        return Inertia::render('Admin/Audit/Index', [
            'audit_logs' => $logs,
        ]);
    }
}
