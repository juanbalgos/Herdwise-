<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\HealthRecordResource;
use App\Http\Resources\ReminderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CareActivityController extends Controller
{
    public function healthRecords(Request $request): AnonymousResourceCollection
    {
        $farm = $request->user()->farms()->wherePivot('is_active', true)->firstOrFail();

        return HealthRecordResource::collection($farm->healthRecords()->with(['animal', 'batch', 'recorder'])->latest('observed_on')->paginate());
    }

    public function reminders(Request $request): AnonymousResourceCollection
    {
        $farm = $request->user()->farms()->wherePivot('is_active', true)->firstOrFail();

        return ReminderResource::collection($farm->reminders()->with(['animal', 'batch'])->where('status', 'pending')->orderBy('due_on')->paginate());
    }
}
