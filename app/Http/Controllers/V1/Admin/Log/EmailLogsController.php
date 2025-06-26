<?php

namespace App\Http\Controllers\V1\Admin\Log;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmailLogResource;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogsController extends Controller
{
    /**
     * Display a listing of email logs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $this->authorize('view-email-logs');

        $query = EmailLog::with(['mailable'])
            ->whereHas('mailable', function ($q) {
                $q->where('company_id', request()->header('company'));
            });

        if ($request->has('mailable_type')) {
            $query->where('mailable_type', $request->mailable_type);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('to', 'like', "%{$search}%")
                  ->orWhere('from', 'like', "%{$search}%");
            });
        }

        $orderBy = $request->get('orderByField', 'created_at');
        $orderByDirection = $request->get('orderBy', 'desc');
        $query->orderBy($orderBy, $orderByDirection);

        $perPage = $request->get('limit', 15);
        $logs = $query->paginate($perPage);

        return EmailLogResource::collection($logs);
    }
} 