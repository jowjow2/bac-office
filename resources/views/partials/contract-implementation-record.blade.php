{{-- The supporting document and remarks every implementation step records. Used inside partials.contract-implementation. --}}
<div class="ci-grid is-two">
    <div class="ci-field">
        <label class="ci-label" for="{{ $uid }}-{{ \Illuminate\Support\Str::slug($fileLabel) }}">{{ $fileLabel }} <span class="ci-req" aria-hidden="true">*</span></label>
        <input class="ci-input ci-file" type="file" id="{{ $uid }}-{{ \Illuminate\Support\Str::slug($fileLabel) }}" name="document" accept="{{ $accept }}" required>
        @if($err('document'))<span class="ci-error">{{ $err('document') }}</span>@endif
    </div>
    <div class="ci-field">
        <label class="ci-label" for="{{ $uid }}-{{ \Illuminate\Support\Str::slug($remarksLabel) }}-text">{{ $remarksLabel }} <span class="ci-req" aria-hidden="true">*</span></label>
        <textarea class="ci-input" id="{{ $uid }}-{{ \Illuminate\Support\Str::slug($remarksLabel) }}-text" name="remarks" rows="2" maxlength="2000" required>{{ $old('remarks') }}</textarea>
        @if($err('remarks'))<span class="ci-error">{{ $err('remarks') }}</span>@endif
    </div>
</div>
