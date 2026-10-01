@props(['label', 'block' => false])

<div {{ $attributes->class(['view-project-field', 'view-project-field--block' => $block]) }}>
    <div class="view-project-field-label">{{ $label }}</div>
    <div class="view-project-value">{{ $slot }}</div>
</div>
