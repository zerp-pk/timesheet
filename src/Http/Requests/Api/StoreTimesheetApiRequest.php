<?php

namespace Zerp\Timesheet\Http\Requests\Api;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Body for POST /api/timesheet/timesheets.
 *
 * Mirrors the web StoreTimesheetRequest: hours/minutes bounds, the three entry
 * types, and project_id becoming required only for the 'project' type. Staff
 * log time for themselves, so user_id is forced to the caller and only a
 * manager (manage-any-timesheet) may pass one. The controller does the
 * create-timesheet permission check and returns the shared 403 envelope, so
 * authorize() stays the base default (true). See zerp-pk/zerp#31.
 */
class StoreTimesheetApiRequest extends ApiFormRequest
{
    protected function prepareForValidation(): void
    {
        if (!Auth::user()->can('manage-any-timesheet')) {
            $this->merge(['user_id' => Auth::id()]);
        }
    }

    // A literal array on purpose: Scramble reads these rules statically to build
    // the documented request body, so project_id uses required_if rather than a
    // runtime `if ($this->type ...)` branch it could not see.
    public function rules(): array
    {
        return [
            'user_id'    => 'required|exists:users,id',
            'date'       => 'required|date',
            'hours'      => 'required|integer|min:0|max:12',
            'minutes'    => 'required|integer|min:1|max:60',
            'notes'      => 'nullable|string|max:1000',
            'type'       => 'required|in:clock_in_out,project,manual',
            'project_id' => 'required_if:type,project|nullable|integer',
            'task_id'    => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'minutes.min' => __('Minutes must be at least 1.'),
            'minutes.max' => __('Minutes cannot exceed 60.'),
            'hours.max'   => __('Hours cannot exceed 12.'),
        ];
    }
}
