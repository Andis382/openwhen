<?php

namespace App\Http\Controllers;

use App\Reports\VisitStats;
use App\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Closed visits by driver, route and week, and the cash each trip brought back. */
class ReportController extends Controller
{
    public function index(Request $request, VisitStats $stats): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $clock = LocalTime::for($request->user()->organization);
        $to = isset($data['to']) ? $clock->date($data['to']) : $clock->today();
        $from = isset($data['from']) ? $clock->date($data['from']) : $to->subWeeks(8)->addDay();
        if ($from->diffInDays($to) > 366) {
            $from = $to->subDays(366);
        }

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total' => $stats->closedRate($from, $to),
            'byDriver' => $stats->byDriver($from, $to),
            'byRoute' => $stats->byRoute($from, $to),
            'byWeek' => $stats->weekly($from, $to),
            'cash' => $stats->cash($from, $to),
        ]);
    }
}
