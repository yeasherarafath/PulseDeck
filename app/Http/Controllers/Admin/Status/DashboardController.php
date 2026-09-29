<?php

namespace App\Http\Controllers\Admin\Status;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Empty Phase 0 dashboard. Real cards arrive in Phase 6 (final-plan §4).
     */
    public function index(): View
    {
        return view('admin.status.dashboard');
    }
}
