{{--
    The technical component as ONE file: the bidder merges the required documents into a single PDF
    and uploads it once. The BAC still checks each requirement, since every item is recorded against
    that file. The per-document upload table stays available as "Separate files".
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
        <span>One combined file <small>Recommended</small></span>
    </label>
    <label class="sb-mode-opt">
        <input type="radio" name="technical_mode" value="separate" data-sb-mode @checked($startSeparate)>
        <span>Separate files</span>
    </label>
</div>

<div class="sb-combined" data-sb-combined @if($startSeparate) hidden @endif>
    {{-- The one file: the whole card is the upload target. --}}
    <div class="sb-drop" data-upload-box data-label="Combined technical file" data-required="1" data-on-record="">
        <span class="sb-drop-icon" aria-hidden="true"><i class="fas fa-file-arrow-up"></i></span>
        <div class="sb-drop-copy">
            <span class="sb-drop-title">Technical documents, all in one file <span class="sb-star" aria-label="required">*</span></span>
            <span class="sb-drop-meta">Merge the {{ $required->count() }} documents below into one file, in the same order &middot; PDF, DOC, DOCX, XLS or XLSX &middot; max {{ $maxMb }} MB</span>
            <span class="sb-file-chosen" data-file-name>Not selected</span>
        </div>
        <div class="sb-file-actions">
            <button type="button" class="sb-btn sb-btn--primary sb-btn--sm" data-upload-change>Upload file</button>
            <button type="button" class="sb-btn sb-btn--ghost sb-btn--sm" data-upload-remove hidden>Remove</button>
        </div>
        <input type="file" class="sb-file-input" name="documents[technical_combined]" id="{{ $combinedId }}" accept=".pdf,.doc,.docx,.xls,.xlsx" data-upload-input data-combined-input @if($required->isNotEmpty()) data-required-upload required @endif @if($startSeparate) disabled @endif>
    </div>

    {{-- What the file must contain, in order; each ticks green once the file is chosen. --}}
    <div class="sb-merge">
        <p class="sb-merge-title">Inside your file, in this order <span>{{ $required->count() }} documents</span></p>
        <ol class="sb-merge-list">
            @foreach($required as $item)
                <li title="{{ $item['condition'] ?: $item['label'] }}">
                    <span class="sb-merge-num" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span class="sb-merge-label">{{ $item['label'] }}@if($item['condition'])<small>{{ $item['condition'] }}</small>@endif</span>
                    <i class="fas fa-circle-check sb-merge-check" aria-hidden="true"></i>
                </li>
            @endforeach
        </ol>
        @if($optional->isNotEmpty())
            <p class="sb-list-note"><i class="fas fa-circle-info" aria-hidden="true"></i> Add these only if they apply to you: {{ $optional->pluck('label')->implode('; ') }}.</p>
        @endif
    </div>
</div>
