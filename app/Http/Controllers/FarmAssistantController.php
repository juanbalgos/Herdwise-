<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskFarmAssistantRequest;
use App\Services\FarmAssistantService;
use App\Services\FarmRecordContextBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class FarmAssistantController extends Controller
{
    public function index(Request $request, FarmRecordContextBuilder $contextBuilder): View
    {
        $farm = $this->currentFarm($request);

        return view('assistant.index', [
            'farm' => $farm,
            'context' => $contextBuilder->build($farm),
            'question' => '',
            'result' => null,
        ]);
    }

    public function ask(
        AskFarmAssistantRequest $request,
        FarmRecordContextBuilder $contextBuilder,
        FarmAssistantService $assistant,
    ): View {
        $farm = $this->currentFarm($request);
        $question = $request->validated('question');
        $context = $contextBuilder->build($farm);

        return view('assistant.index', [
            'farm' => $farm,
            'context' => $context,
            'question' => $question,
            'result' => $assistant->answer($request->user(), $question, $context),
        ]);
    }
}
