{{-- A file field shown as a drop area: click or drop a file; the chosen name shows below. --}}
@php $required = $required ?? true; @endphp
<div class="ui-field">
    <span class="ui-label">{{ $label }} @if($required)<span class="ui-required">*</span>@else<span class="ui-optional">(optional)</span>@endif</span>
    <label class="infra-drop">
        <input type="file" name="document" class="infra-drop__input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @if($required) required @endif data-infra-file>
        <span class="infra-drop__icon" aria-hidden="true"><i class="fas fa-cloud-arrow-up"></i></span>
        <span class="infra-drop__text">
            <strong data-infra-file-name>Choose a file or drop it here</strong>
            <small>{{ $hint ?? 'PDF, image or Word file.' }}</small>
        </span>
    </label>
</div>
