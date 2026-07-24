<?php

namespace Zerp\Timesheet\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Zerp\Timesheet\Models\Timesheet;

/**
 * Summary numbers for the Timesheet home screen. Scoped the same way the list
 * is: a manager sees the whole company, staff see only their own entries.
 * See zerp-pk/zerp#31.
 */
class DashboardApiController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        try {
            if (!Auth::user()->can('manage-timesheet')) {
                return $this->errorResponse(__('Permission denied'), null, 403);
            }

            $base = fn () => Timesheet::query()->where(function ($q) {
                if (Auth::user()->can('manage-any-timesheet')) {
                    $q->where('created_by', creatorId());
                } elseif (Auth::user()->can('manage-own-timesheet')) {
                    $q->where('user_id', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

            $now = Carbon::now();

            $totalEntries = (clone $base())->count();
            $totalMinutes = $this->minutes(clone $base());
            $monthMinutes = $this->minutes((clone $base())->whereMonth('date', $now->month)->whereYear('date', $now->year));

            $byType = (clone $base())
                ->selectRaw('type, COUNT(*) as entries, COALESCE(SUM(hours * 60 + minutes), 0) as total_minutes')
                ->groupBy('type')
                ->get()
                ->map(fn ($row) => [
                    'type'    => $row->type,
                    'entries' => (int) $row->entries,
                    'hours'   => round($row->total_minutes / 60, 2),
                ]);

            $recent = (clone $base())->with('user:id,name')->latest()->limit(5)->get()
                ->map(fn ($t) => [
                    'id'        => $t->id,
                    'user_name' => $t->user->name ?? null,
                    'date'      => $t->date?->format('Y-m-d'),
                    'hours'     => $t->hours,
                    'minutes'   => $t->minutes,
                    'type'      => $t->type,
                ]);

            return $this->successResponse([
                'stats' => [
                    'total_entries'      => $totalEntries,
                    'total_hours'        => round($totalMinutes / 60, 2),
                    'this_month_hours'   => round($monthMinutes / 60, 2),
                ],
                'by_type'        => $byType,
                'recent_entries' => $recent,
            ], __('Dashboard retrieved successfully'));
        } catch (\Throwable $e) {
            Log::error('Timesheet dashboard API error', ['message' => $e->getMessage()]);
            return $this->errorResponse(__('Something went wrong'), null, 500);
        }
    }

    /** Total logged time in minutes for a scoped query, summed in the database. */
    private function minutes($query): int
    {
        return (int) $query->sum(DB::raw('hours * 60 + minutes'));
    }
}
