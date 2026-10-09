@php($requestSteps = ['Terms', 'Dokumento', 'Details', 'Valid ID', 'Review'])
<div class="steps">
    <ol class="progress-steps progress-steps-5" data-portal-i18n-aria-label="Mga hakbang sa pag-request" aria-label="{{ __('Mga hakbang sa pag-request') }}">
        @foreach($requestSteps as $index => $label)
            <li @class(['step', 'done' => $index + 1 < $current, 'active' => $index + 1 === $current]) @if($index + 1 === $current) aria-current="step" @endif><span><span data-portal-i18n="{{ $label }}">{{ __($label) }}</span></span></li>
        @endforeach
    </ol>
    <p class="steps-summary"><span data-portal-i18n="Step :current of :total" data-portal-params="{{ json_encode(['current' => $current, 'total' => count($requestSteps)]) }}">{{ __('Step :current of :total', ['current' => $current, 'total' => count($requestSteps)]) }}</span>: <span data-portal-i18n="{{ $title }}">{{ __($title) }}</span></p>
</div>
