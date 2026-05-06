<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\DocumentPreview;
use App\Support\Uploads;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PublicProcurementController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        try {
            $projects = Schema::hasTable('projects')
                ? Project::query()
                    ->visibleToPublic()
                    ->with('documents')
                    ->when($query !== '', function ($builder) use ($query) {
                        $builder->where(function ($nested) use ($query) {
                            $nested->where('title', 'like', "%{$query}%")
                                ->orWhere('description', 'like', "%{$query}%");
                        });
                    })
                    ->latest()
                    ->get()
                : collect();
        } catch (Throwable) {
            $projects = collect();
        }

        return view('pages.procurement', compact('projects', 'query'));
    }

    public function show(Project $project)
    {
        abort_unless(in_array($project->status, Project::PUBLIC_STATUSES, true), 404);

        $project->loadCount('bids');
        $project->loadMissing('documents');

        return view('pages.procurement-show', compact('project'));
    }

    public function previewDocument(Project $project, string $document)
    {
        abort_unless(in_array($project->status, Project::PUBLIC_STATUSES, true), 404);

        $project->loadMissing('documents');
        $documentMeta = $this->projectDocumentMeta($project, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return redirect()->route('public.procurement.document.pdf', ['project' => $project, 'document' => $document]);
    }

    public function streamDocumentPdf(Project $project, string $document)
    {
        abort_unless(in_array($project->status, Project::PUBLIC_STATUSES, true), 404);

        $project->loadMissing('documents');
        $documentMeta = $this->projectDocumentMeta($project, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return $this->streamDocumentPdfPreview(
            $documentMeta['path'],
            $documentMeta['display_name'],
            $documentMeta['label']
        );
    }

    protected function projectDocumentMeta(Project $project, string $document): array
    {
        abort_unless(ctype_digit($document), 404);

        $projectDocument = $project->uploadedDocuments()->values()->get((int) $document);

        abort_unless($projectDocument !== null, 404);

        return [
            'label' => 'Bidding File',
            'path' => $projectDocument->file_path,
            'display_name' => $projectDocument->display_name,
        ];
    }

    protected function streamDocumentPdfPreview(string $path, ?string $displayName, string $documentLabel)
    {
        $resolvedDisplayName = Uploads::fileName($path, $displayName) ?? 'document';
        $pdfFilename = $this->pdfPreviewFilename($resolvedDisplayName);

        if (Uploads::extension($path, $resolvedDisplayName) === 'pdf') {
            $contents = Uploads::contents($path);

            if (is_string($contents) && $contents !== '') {
                return response($contents, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $pdfFilename . '"',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }
        }

        $preview = DocumentPreview::forUpload($path, $resolvedDisplayName);

        return Pdf::loadView('admin.bid-document-pdf', [
            'preview' => $preview,
            'documentLabel' => $documentLabel,
        ])->setPaper('a4')->stream($pdfFilename);
    }

    protected function pdfPreviewFilename(string $displayName): string
    {
        $baseName = pathinfo($displayName, PATHINFO_FILENAME) ?: 'document';
        $safeName = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName), '-');

        return ($safeName !== '' ? $safeName : 'document') . '.pdf';
    }
}
