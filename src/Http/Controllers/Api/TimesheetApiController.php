<?php

namespace Zerp\Timesheet\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Zerp\Timesheet\Events\CreateTimesheet;
use Zerp\Timesheet\Events\DestroyTimesheet;
use Zerp\Timesheet\Events\UpdateTimesheet;
use Zerp\Timesheet\Http\Requests\Api\StoreTimesheetApiRequest;
use Zerp\Timesheet\Http\Requests\Api\UpdateTimesheetApiRequest;
use Zerp\Timesheet\Models\Timesheet;

/**
 * REST API for the Timesheet module, backing the Flutter app. Mirrors the web
 * TimesheetController: the same manage-any / manage-own visibility, the same
 * per-entry permissions, and company (created_by) tenant scoping. Responses use
 * the shared {success, message, data} envelope. See zerp-pk/zerp#31.
 *
 * Not ported from web yet: the clock_in_out entries are not checked against HRM
 * attendance remaining-hours here. That guard lives in the web controller as a
 * private method coupled to HRM; sharing it cleanly is a follow-up rather than a
 * copy-paste. A client can still over-log clock_in_out time until then.
 */
class TimesheetApiController extends Controller
{
    use ApiResponseTrait;

    private const SORTABLE = ['id', 'date', 'hours', 'minutes', 'type', 'user_id', 'created_at'];

    public function index(Request $request)
    {
        try {
            if (!Auth::user()->can('manage-timesheet')) {
                return $this->errorResponse(__('Permission denied'), null, 403);
            }

            $timesheets = Timesheet::with(['user:id,name'])
                ->tap(fn ($q) => $this->scope($q))
                ->when($request->search, fn ($q, $s) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$s}%")))
                ->when($request->type, fn ($q, $t) => $q->where('type', $t))
                ->when($request->date, fn ($q, $d) => $q->whereDate('date', $d))
                ->when($request->user_id, fn ($q, $id) => $q->where('timesheets.user_id', (int) $id))
                ->tap(fn ($q) => $this->applySort($q, $request))
                ->paginate($request->get('per_page', 10))
                ->withQueryString();

            $timesheets->getCollection()->transform(fn ($t) => $this->present($t));

            return $this->paginatedResponse($timesheets, __('Timesheets retrieved successfully'));
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    public function store(StoreTimesheetApiRequest $request)
    {
        try {
            if (!Auth::user()->can('create-timesheet')) {
                return $this->errorResponse(__('Permission denied'), null, 403);
            }

            $validated = $request->validated();

            if (!$this->userInCompany($validated['user_id'])) {
                return $this->errorResponse(__('The selected user is invalid.'), null, 422);
            }

            $timesheet = new Timesheet();
            $timesheet->fill($validated);
            $timesheet->creator_id = Auth::id();
            $timesheet->created_by = creatorId();
            $timesheet->save();

            CreateTimesheet::dispatch($request, $timesheet);

            return $this->successResponse($this->present($timesheet->load('user:id,name')), __('Timesheet created successfully'), 201);
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    public function show($id)
    {
        try {
            if (!Auth::user()->can('manage-timesheet')) {
                return $this->errorResponse(__('Permission denied'), null, 403);
            }

            $timesheet = Timesheet::with(['user:id,name'])->where('id', $id)->tap(fn ($q) => $this->scope($q))->first();

            if (!$timesheet) {
                return $this->errorResponse(__('Timesheet not found'), null, 404);
            }

            return $this->successResponse($this->present($timesheet), __('Timesheet details retrieved successfully'));
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    public function update(UpdateTimesheetApiRequest $request, $id)
    {
        try {
            if (!Auth::user()->can('edit-timesheet')) {
                return $this->errorResponse(__('Permission denied'), null, 403);
            }

            $timesheet = Timesheet::where('id', $id)->tap(fn ($q) => $this->scope($q))->first();
            if (!$timesheet) {
                return $this->errorResponse(__('Timesheet not found'), null, 404);
            }

            $validated = $request->validated();

            if (isset($validated['user_id']) && !$this->userInCompany($validated['user_id'])) {
                return $this->errorResponse(__('The selected user is invalid.'), null, 422);
            }

            $timesheet->update($validated);

            UpdateTimesheet::dispatch($request, $timesheet);

            return $this->successResponse($this->present($timesheet->load('user:id,name')), __('Timesheet updated successfully'));
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    public function destroy($id)
    {
        try {
            if (!Auth::user()->can('delete-timesheet')) {
                return $this->errorResponse(__('Permission denied'), null, 403);
            }

            $timesheet = Timesheet::where('id', $id)->tap(fn ($q) => $this->scope($q))->first();
            if (!$timesheet) {
                return $this->errorResponse(__('Timesheet not found'), null, 404);
            }

            DestroyTimesheet::dispatch($timesheet);
            $timesheet->delete();

            return $this->successResponse(null, __('Timesheet deleted successfully'));
        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }

    /**
     * Limit a query to what the caller may see: a manager sees the whole
     * company, staff see only their own rows, anyone else sees nothing. Mirrors
     * the web controller's visibility rules.
     */
    private function scope(Builder $query): void
    {
        $query->where(function ($q) {
            if (Auth::user()->can('manage-any-timesheet')) {
                $q->where('timesheets.created_by', creatorId());
            } elseif (Auth::user()->can('manage-own-timesheet')) {
                $q->where('timesheets.user_id', Auth::id());
            } else {
                $q->whereRaw('1 = 0');
            }
        });
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sort = $request->get('sort');
        if (!in_array($sort, self::SORTABLE, true)) {
            $query->latest();
            return;
        }

        $direction = in_array($request->get('direction'), ['asc', 'desc'], true) ? $request->get('direction') : 'asc';

        if ($sort === 'user_id') {
            $query->join('users', 'timesheets.user_id', '=', 'users.id')
                ->orderBy('users.name', $direction)
                ->select('timesheets.*');
            return;
        }

        $query->orderBy('timesheets.' . $sort, $direction);
    }

    /** True when the user belongs to the caller's company. Closes the cross-tenant gap the web's plain exists:users leaves open. */
    private function userInCompany($userId): bool
    {
        return (int) $userId === Auth::id()
            || User::where('id', $userId)->where('created_by', creatorId())->exists();
    }

    /**
     * The single shape a timesheet is returned in, shared by list, show and
     * create so they cannot drift. Project and task names are added only when
     * Taskly is installed, the same as the web screen.
     */
    private function present(Timesheet $timesheet): array
    {
        $data = [
            'id'         => $timesheet->id,
            'user_id'    => $timesheet->user_id,
            'user_name'  => $timesheet->user->name ?? null,
            'project_id' => $timesheet->project_id,
            'task_id'    => $timesheet->task_id,
            'date'       => $timesheet->date?->format('Y-m-d'),
            'hours'      => $timesheet->hours,
            'minutes'    => $timesheet->minutes,
            'notes'      => $timesheet->notes,
            'type'       => $timesheet->type,
            'created_by' => $timesheet->created_by,
        ];

        if (module_is_active('Taskly')) {
            $data['project_name'] = $this->tasklyName('\Zerp\Taskly\Models\Project', $timesheet->project_id, 'name');
            $data['task_name']    = $this->tasklyName('\Zerp\Taskly\Models\ProjectTask', $timesheet->task_id, 'title');
        }

        return $data;
    }

    private function tasklyName(string $model, $id, string $column): ?string
    {
        if (!$id || !class_exists($model)) {
            return null;
        }

        try {
            return optional($model::find($id))->{$column} ?? 'N/A';
        } catch (\Throwable $e) {
            return 'N/A';
        }
    }

    private function fail(\Throwable $e)
    {
        Log::error('Timesheet API error', ['message' => $e->getMessage()]);
        return $this->errorResponse(__('Something went wrong'), null, 500);
    }
}
