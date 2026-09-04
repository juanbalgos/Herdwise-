<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PregnancyRecordResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReproductionController extends Controller
{
    public function pregnancies(Request $request): AnonymousResourceCollection
    {
        $farm = $request->user()->farms()->wherePivot('is_active', true)->firstOrFail();

        return PregnancyRecordResource::collection($farm->pregnancyRecords()->with(['animal.species', 'breedingRecord'])->latest('checked_on')->paginate());
    }
}
