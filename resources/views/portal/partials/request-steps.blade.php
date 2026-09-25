@php($requestSteps = ['Terms', 'Dokumento', 'Details', 'Valid ID', 'Review'])
<div class="steps">
    <ol class="progress-steps progress-steps-5" aria-label="Mga hakbang sa pag-request">
        @foreach($requestSteps as $index => $label)
            <li @class(['step', 'done' => $index + 1 < $current, 'active' => $index + 1 === $current]) @if($index + 1 === $current) aria-current="step" @endif><span>{{ $label }}</span></li>
        @endforeach
    </ol>
    <p class="steps-summary">Step {{ $current }} of {{ count($requestSteps) }}: {{ $title }}</p>
</div>
