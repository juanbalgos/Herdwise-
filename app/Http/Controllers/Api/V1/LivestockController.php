<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnimalResource;
use App\Http\Resources\LivestockBatchResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LivestockController extends Controller
{
    public function animals(Request $request): AnonymousResourceCollection
    {
        $farm = $this->currentFarm($request);

        return AnimalResource::collection($farm->animals()->with(['species', 'breed'])->latest()->paginate());
    }

    public function batches(Request $request): AnonymousResourceCollection
    {
        $farm = $this->currentFarm($request);

        return LivestockBatchResource::collection($farm->batches()->with(['species', 'breed'])->latest()->paginate());
    }
}
