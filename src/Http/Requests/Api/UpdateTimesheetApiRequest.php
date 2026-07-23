<?php

namespace Zerp\Timesheet\Http\Requests\Api;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Body for PUT /api/timesheet/timesheets/{id}. Same shape as the store request;
 * only a manager may reassign the entry to another user. See zerp-pk/zerp#31.
 */
class UpdateTimesheetApiRequest extends ApiFormRequest
{
    // Staff cannot reassign an entry to someone else, so user_id is stripped
    // for them before validation and only a manager's value survives. The rules
    // stay a literal array so Scramble can document the request body.
    protected function prepareForValidation(): void
    {
        if (!Auth::user()->can('manage-any-timesheet')) {
            $this->request->remove('user_id');
        }
    }

    public function rules(): array
    {
        return [
            'user_id'    => 'sometimes|required|exists:users,id',
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
