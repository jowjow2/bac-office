@props(['title'])

<section {{ $attributes->merge(['class' => 'view-project-summary-section']) }}>
    <h3 class="view-project-summary-section-title">{{ $title }}</h3>
    {{ $slot }}
</section>
