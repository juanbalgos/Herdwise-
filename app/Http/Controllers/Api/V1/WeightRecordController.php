<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WeightRecordResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WeightRecordController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $farm = $request->user()->farms()->wherePivot('is_active', true)->firstOrFail();

        return WeightRecordResource::collection($farm->weightRecords()->with(['animal.species', 'batch.species'])->latest('measured_on')->paginate());
    }
}
