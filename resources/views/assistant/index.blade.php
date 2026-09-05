@extends('layouts.app')

@section('title', 'Record Assistant · HerdWise')
@section('heading', 'Record assistant')

@section('content')
    <section class="assistant-hero">
        <div>
            <span class="eyebrow light">FARM-ONLY DECISION SUPPORT</span>
            <h2>Ask HerdWise about {{ $farm->name }}</h2>
            <p>Get a concise answer from the selected farm's inventory, care, reminder, breeding, and growth records.</p>
        </div>
        <div class="assistant-shield"><span>✦</span><b>Private by design</b><small>Only this farm's bounded record summary is reviewed.</small></div>
    </section>

    <div class="assistant-metrics">
        <article><span class="green">◉</span><div><small>INDIVIDUALS</small><strong>{{ $context['inventory']['individuals'] }}</strong><p>active records</p></div></article>
        <article><span class="lime">▤</span><div><small>BATCH POPULATION</small><strong>{{ $context['inventory']['batch_population'] }}</strong><p>across {{ $context['inventory']['active_batches'] }} active batches</p></div></article>
        <article><span class="amber">!</span><div><small>NEEDS ATTENTION</small><strong>{{ $context['attention']['total'] }}</strong><p>individual animals</p></div></article>
        <article><span class="blue">◇</span><div><small>PENDING CARE</small><strong>{{ $context['reminders']['pending'] }}</strong><p>{{ $context['reminders']['overdue'] }} overdue</p></div></article>
    </div>

    <div class="assistant-layout">
        <section class="panel assistant-chat">
            <div class="panel-head">
                <div><span class="eyebrow">ASK A QUESTION</span><h3>Farm record query</h3></div>
                <span class="assistant-mode">{{ config('herdwise.assistant.provider') === 'openai' ? 'Live AI ready when configured' : 'Local analysis' }}</span>
            </div>

            @if($errors->any())
                <div class="errors"><b>Please check your question.</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form class="assistant-form" method="post" action="{{ route('assistant.ask') }}">
                @csrf
                <label for="question">What would you like to know?</label>
                <textarea id="question" name="question" rows="4" maxlength="800" required placeholder="Example: Which animals need attention today?">{{ old('question', $question) }}</textarea>
                <div class="assistant-form-footer">
                    <small>Answers use current recorded data and may be incomplete when records are missing.</small>
                    <button class="btn" type="submit">Ask HerdWise ✦</button>
                </div>
            </form>

            @if($result)
                <article class="assistant-answer" aria-live="polite">
                    <div class="assistant-answer-head">
                        <span>✦</span>
                        <div><small>{{ $result['mode'] === 'openai' ? 'LIVE AI · FARM RECORDS' : 'BUILT-IN · FARM RECORDS' }}</small><h3>HerdWise answer</h3></div>
                    </div>
                    @if($result['notice'])<p class="assistant-notice">{{ $result['notice'] }}</p>@endif
                    <p class="assistant-answer-text">{{ $result['answer'] }}</p>
                    <footer>Record snapshot generated {{ \Carbon\CarbonImmutable::parse($context['generated_at'])->setTimezone($farm->timezone)->format('M j, Y · g:i A') }} · {{ $farm->timezone }}</footer>
                </article>
            @endif
        </section>

        <aside class="panel assistant-sidebar">
            <span class="eyebrow">TRY A FOCUSED QUESTION</span>
            <h3>Suggested prompts</h3>
            <div class="assistant-prompts">
                @foreach([
                    ['!', 'Which animals need attention today?'],
                    ['◇', 'What care is overdue or due soon?'],
                    ['▦', 'Summarize the current livestock inventory.'],
                    ['♧', 'Are there upcoming expected deliveries?'],
                    ['⌁', 'Show the latest recorded weights.'],
                ] as [$symbol, $prompt])
                    <form method="post" action="{{ route('assistant.ask') }}">
                        @csrf
                        <input type="hidden" name="question" value="{{ $prompt }}">
                        <button type="submit"><span>{{ $symbol }}</span>{{ $prompt }}</button>
                    </form>
                @endforeach
            </div>
            <div class="assistant-boundary">
                <b>Veterinary safety boundary</b>
                <p>The assistant summarizes existing records. It does not diagnose, prescribe medicine, recommend doses, or replace a veterinarian.</p>
            </div>
        </aside>
    </div>
@endsection
