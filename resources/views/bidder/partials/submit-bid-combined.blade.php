{{--
    The technical component as ONE file: the bidder merges the required documents into a single PDF
    and uploads it once. The BAC still checks each requirement, since every item is recorded against
    that file. The per-document upload table stays available as "Upload separately".
    Variables: $items (technical requirement items), $pid, $isModifying, $draftFiles.
--}}
@php
    $required = collect($items)->where('required', true)->values();
    $optional = collect($items)->where('required', false)->values();
    $startSeparate = $isModifying && $draftFiles->isNotEmpty();
    $maxMb = (int) round(((int) config('bac-office.registration.max_document_size_kb', 20480)) / 1024);
    $combinedId = 'doc-'.$pid.'-technical_combined';
@endphp
<div class="sb-mode" role="radiogroup" aria-label="How to upload the technical documents">
    <label class="sb-mode-opt">
        <input type="radio" name="technical_mode" value="combined" data-sb-mode @checked(! $startSeparate)>
        <span class="sb-mode-copy"><strong>One combined file</strong><small>Recommended. Merge everything into a single PDF.</small></span>
    </label>
    <label class="sb-mode-opt">
        <input type="radio" name="technical_mode" value="separate" data-sb-mode @checked($startSeparate)>
        <span class="sb-mode-copy"><strong>Separate files</strong><small>Upload each document on its own.</small></span>
    </label>
</div>

<div class="sb-combined" data-sb-combined @if($startSeparate) hidden @endif>
    <div class="sb-drop" data-upload-box data-label="Combined technical file" data-required="1" data-on-record="">
        <span class="sb-drop-icon" aria-hidden="true"><i class="fas fa-file-pdf"></i></span>
        <div class="sb-drop-copy">
            <strong>Upload your technical documents as one file</strong>
            <span>PDF, DOC, DOCX, XLS or XLSX, up to {{ $maxMb }} MB.</span>
            <span class="sb-drop-chosen" data-file-name>No file selected</span>
        </div>
        <div class="sb-drop-actions">
            <button type="button" class="sb-btn sb-btn--primary sb-btn--sm" data-upload-change>Choose file</button>
            <button type="button" class="sb-btn sb-btn--ghost sb-btn--sm" data-upload-remove hidden>Remove</button>
        </div>
        <input type="file" class="sb-file-input" name="documents[technical_combined]" id="{{ $combinedId }}" accept=".pdf,.doc,.docx,.xls,.xlsx" data-upload-input data-combined-input @if($required->isNotEmpty()) data-required-upload required @endif @if($startSeparate) disabled @endif>
    </div>

    <div class="sb-includes">
        <h4>Your file must include <span>{{ $required->count() }} required {{ \Illuminate\Support\Str::plural('document', $required->count()) }}</span></h4>
        <p>Put them in this order, one after the other. The BAC checks each one against this list.</p>
        <ol class="sb-includes-list">
            @foreach($required as $item)
                <li><span class="sb-includes-no">{{ $loop->iteration }}</span><span>{{ $item['label'] }}@if($item['condition'])<small>{{ $item['condition'] }}</small>@endif</span></li>
            @endforeach
        </ol>
        @if($optional->isNotEmpty())
            <p class="sb-includes-extra"><i class="fas fa-circle-info" aria-hidden="true"></i> Add these only if they apply to you: {{ $optional->pluck('label')->implode('; ') }}.</p>
        @endif
    </div>
</div>
