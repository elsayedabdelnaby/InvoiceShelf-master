<?php

namespace App\Http\Controllers\V1\Admin\Log;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\InvoicePaidAuditLog;
use Illuminate\Http\Request;

class AuditLogsController extends Controller
{
    /**
     * Display a listing of audit logs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('view-audit-logs');

        $query = InvoicePaidAuditLog::with(['invoice.customer', 'user'])
            ->whereHas('invoice', function ($q) {
                $q->where('company_id', request()->header('company'));
            });

        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        if ($request->has('user_search')) {
            $userSearch = $request->user_search;
            $query->whereHas('user', function ($q) use ($userSearch) {
                $q->where('name', 'like', "%{$userSearch}%")
                  ->orWhere('email', 'like', "%{$userSearch}%");
            });
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orderBy = $request->get('orderByField', 'created_at');
        $orderByDirection = $request->get('orderBy', 'desc');
        $query->orderBy($orderBy, $orderByDirection);

        $perPage = $request->get('limit', 15);
        $logs = $query->paginate($perPage);

        return AuditLogResource::collection($logs);
    }
} 