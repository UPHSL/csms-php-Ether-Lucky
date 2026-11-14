<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboardService): View
    {
        return view('dashboard', [
            'summary' => $dashboardService->getSummary(),
        ]);
    }
}
