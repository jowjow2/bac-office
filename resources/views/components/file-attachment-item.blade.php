@props(['project', 'document', 'index'])

@php
    $fileName = $document->display_name ?: 'Project document';
    $extension = strtoupper(\App\Support\Uploads::extension($document->file_path, $fileName) ?: 'file');
    $fileSize = \App\Support\Uploads::size($document->file_path);
    $fileSizeLabel = $fileSize === null
        ? 'Size unavailable'
        : ($fileSize >= 1048576
            ? number_format($fileSize / 1048576, 1) . ' MB'
            : ($fileSize >= 1024
                ? number_format($fileSize / 1024, 0) . ' KB'
                : $fileSize . ' bytes'));
    $fileIcon = match (strtolower($extension)) {
        'doc', 'docx' => 'fa-file-word',
        'pdf' => 'fa-file-pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp' => 'fa-file-image',
        default => 'fa-file-lines',
    };
    $uploadedLabel = $document->created_at?->format('M d, Y') ?? 'Upload date unavailable';
@endphp

<a href="{{ route('admin.project.document.pdf', ['project' => $project, 'document' => $index]) }}"
   target="_blank"
   rel="noopener"
   class="view-project-file-item">
    <span class="view-project-file-icon" aria-hidden="true">
        <i class="fas {{ $fileIcon }}"></i>
    </span>
    <span class="view-project-file-copy">
        <span class="view-project-file-name">{{ $fileName }}</span>
        <span class="view-project-file-meta">{{ $extension }} | {{ $fileSizeLabel }} | Uploaded {{ $uploadedLabel }}</span>
    </span>
    <i class="fas fa-arrow-up-right-from-square view-project-file-arrow" aria-hidden="true"></i>
</a>
