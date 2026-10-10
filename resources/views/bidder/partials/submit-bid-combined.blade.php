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
    <div class="sb-filetable">
        <ul class="sb-files">
            <li class="sb-file" data-upload-box data-label="Combined technical file" data-required="1" data-on-record="">
                <span class="sb-file-icon" aria-hidden="true"><i class="fas fa-file-pdf"></i></span>
                <div class="sb-file-copy">
                    <span class="sb-file-title">Technical documents, all in one file <span class="sb-star" aria-label="required">*</span></span>
                    <span class="sb-file-meta">PDF, DOC, DOCX, XLS or XLSX &middot; max {{ $maxMb }} MB</span>
                    <div class="sb-file-status"><span class="sb-file-chosen" data-file-name>Not selected</span></div>
                </div>
                <div class="sb-file-actions">
                    <button type="button" class="sb-btn sb-btn--secondary sb-btn--sm" data-upload-change>Upload</button>
                    <button type="button" class="sb-btn sb-btn--ghost sb-btn--sm" data-upload-remove hidden>Remove</button>
                </div>
                <input type="file" class="sb-file-input" name="documents[technical_combined]" id="{{ $combinedId }}" accept=".pdf,.doc,.docx,.xls,.xlsx" data-upload-input data-combined-input @if($required->isNotEmpty()) data-required-upload required @endif @if($startSeparate) disabled @endif>
            </li>
        </ul>
    </div>

    <p class="sb-list-label">Your file must include these {{ $required->count() }} documents, in this order</p>
    <div class="sb-filetable">
        <ul class="sb-files sb-files--ref">
            @foreach($required as $item)
                <li class="sb-file sb-file--ref">
                    <span class="sb-file-icon" aria-hidden="true"><i class="fas fa-file-lines"></i></span>
                    <div class="sb-file-copy">
                        <span class="sb-file-title">{{ $loop->iteration }}. {{ $item['label'] }} <span class="sb-star" aria-label="required">*</span></span>
                        @if($item['condition'])<span class="sb-file-meta">{{ $item['condition'] }}</span>@endif
                    </div>
                    <span class="sb-ref-state"><span class="sb-ref-wait">Not yet</span><span class="sb-ref-done"><i class="fas fa-check" aria-hidden="true"></i> In your file</span></span>
                </li>
            @endforeach
        </ul>
    </div>
    @if($optional->isNotEmpty())
        <p class="sb-list-note"><i class="fas fa-circle-info" aria-hidden="true"></i> Add these only if they apply to you: {{ $optional->pluck('label')->implode('; ') }}.</p>
    @endif
</div>
