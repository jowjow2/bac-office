{{-- Upload rows for one bid component; used by submit-bid-modal. --}}
<ul class="sb-files" data-sb-files="{{ $component }}">
    @foreach($items as $item)
        @php
            $inputId = 'doc-'.$pid.'-'.$item['key'];
            $onRecord = $draftFiles->get($item['key']);
            $mustUpload = $electronic && $item['required'] && ! ($isModifying && $onRecord);
            $fileError = old('project_id') == $pid ? $errors->first('documents.'.$item['key']) : null;
        @endphp
        <li class="sb-file {{ $onRecord ? 'has-record' : '' }} {{ $fileError ? 'is-invalid' : '' }}" data-upload-box data-label="{{ $item['label'] }}" data-required="{{ $item['required'] ? '1' : '0' }}" data-on-record="{{ $onRecord?->original_name }}">
            <span class="sb-file-icon" aria-hidden="true"><i class="fas {{ $component === 'financial' ? 'fa-file-invoice-dollar' : 'fa-file-shield' }}"></i></span>
            <div class="sb-file-copy">
                <span class="sb-file-title">
                    {{ $item['label'] }}
                    <span class="sb-tag {{ $item['required'] ? 'is-required' : '' }}">{{ $item['required'] ? 'Required' : 'If applicable' }}</span>
                </span>
                <span class="sb-file-meta">{{ $item['condition'] ? $item['condition'].' · ' : '' }}PDF, DOC, DOCX, XLS, XLSX</span>
                @if($onRecord)
                    <span class="sb-file-record"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ $isModifying ? 'Filed' : 'Draft copy on record' }}: {{ $onRecord->original_name }}{{ $isModifying ? ' (kept unless you choose a replacement)' : '' }}</span>
                @endif
                <span class="sb-file-chosen" data-file-name>Not selected</span>
                @if($fileError)
                    <span class="sb-error">{{ $fileError }}</span>
                @endif
            </div>
            <div class="sb-file-actions">
                <button type="button" class="sb-btn sb-btn--secondary sb-btn--sm" data-upload-change>Choose file</button>
                <button type="button" class="sb-btn sb-btn--ghost sb-btn--sm" data-upload-remove hidden>Remove</button>
            </div>
            <input type="file" class="sb-file-input" name="documents[{{ $item['key'] }}]" id="{{ $inputId }}" accept=".pdf,.doc,.docx,.xls,.xlsx" data-upload-input @if($mustUpload) data-required-upload required @endif>
        </li>
    @endforeach
</ul>
