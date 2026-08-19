<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $logs = SmsLog::query()
            ->latest()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->paginate(25)
            ->withQueryString();

        return view('admin.sms-logs.index', compact('logs'));
    }
}
