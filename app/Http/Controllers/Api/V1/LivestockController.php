<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnimalResource;
use App\Http\Resources\LivestockBatchResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LivestockController extends Controller
{
    public function animals(): AnonymousResourceCollection
    {
        $farm = request()->user()->farms()->wherePivot('is_active', true)->firstOrFail();

        return AnimalResource::collection($farm->animals()->with(['species', 'breed'])->latest()->paginate());
    }

    public function batches(): AnonymousResourceCollection
    {
        $farm = request()->user()->farms()->wherePivot('is_active', true)->firstOrFail();

        return LivestockBatchResource::collection($farm->batches()->with(['species', 'breed'])->latest()->paginate());
    }
}
