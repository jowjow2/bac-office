<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\DocumentPreview;
use App\Support\Uploads;
use App\Support\QrSvg;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PublicProcurementController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $category = strtolower(trim((string) $request->query('category', '')));

        $categoryCounts = collect();

        try {
            $baseQuery = Project::query()
                ->visibleToPublic()
                ->where(function ($status) {
                    $status->where('status', '!=', 'awarded')
                        ->orWhereHas('bids', fn ($bid) => $bid->whereNotNull('notice_of_award_at')->where('notice_of_award_at', '>', now()));
                })
                ->when($query !== '', function ($builder) use ($query) {
                    $builder->where(function ($nested) use ($query) {
                        $nested->where('title', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%");
                    });
                });

            $categoryCounts = Schema::hasTable('projects')
                ? (clone $baseQuery)->selectRaw('LOWER(category) as category, COUNT(*) as total')
                    ->groupBy('category')
                    ->pluck('total', 'category')
                : collect();

            $projects = Schema::hasTable('projects')
                ? (clone $baseQuery)
                    ->when($category !== '', fn ($builder) => $builder->whereRaw('LOWER(category) = ?', [$category]))
                    ->with(['documents', 'schedule'])
                    ->latest()
                    ->get()
                : collect();

            // Bidders first see what they can still submit to, soonest deadline first.
            [$upcoming, $others] = $projects->partition(fn (Project $project) => $project->status === 'open'
                && $project->bidSubmissionDeadline()?->isFuture());
            $projects = $upcoming->sortBy(fn (Project $project) => $project->bidSubmissionDeadline()->getTimestamp())
                ->concat($others)->values();
        } catch (Throwable) {
            $projects = collect();
        }

        return view('pages.procurement', compact('projects', 'query', 'category', 'categoryCounts'));
    }

    public function show(Project $project)
    {
        // Published and its publication time reached (server clock, Philippine time).
        abort_unless($project->isPubliclyVisible(), 404);

        // No bid counts on the public page: the number of bidders stays confidential.
        $project->loadMissing(['documents', 'schedule']);

        return view('pages.procurement-show', compact('project'));
    }

    public function qr(Project $project)
    {
        // Published and its publication time reached (server clock, Philippine time).
        abort_unless($project->isPubliclyVisible(), 404);

        $svg = QrSvg::render($this->publicProjectActionUrl($project), 300, 3);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function previewDocument(Project $project, string $document)
    {
        // Published and its publication time reached (server clock, Philippine time).
        abort_unless($project->isPubliclyVisible(), 404);

        $project->loadMissing('documents');
        $documentMeta = $this->projectDocumentMeta($project, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return redirect()->route('public.procurement.document.pdf', ['project' => $project, 'document' => $document]);
    }

    public function streamDocumentPdf(Project $project, string $document)
    {
        // Published and its publication time reached (server clock, Philippine time).
        abort_unless($project->isPubliclyVisible(), 404);

        $project->loadMissing('documents');
        $documentMeta = $this->projectDocumentMeta($project, $document);

        abort_unless(filled($documentMeta['path']), 404);

        return $this->streamDocumentPdfPreview(
            $documentMeta['path'],
            $documentMeta['display_name'],
            $documentMeta['label']
        );
    }

    protected function publicProjectActionUrl(Project $project): string
    {
        return match ($project->status) {
            'open' => route('login.page', ['qr_project' => $project->id]),
            'awarded' => route('public.awards', ['q' => $project->title]),
            default => route('public.procurement.show', $project),
        };
    }

    protected function projectDocumentMeta(Project $project, string $document): array
    {
        abort_unless(ctype_digit($document), 404);

        $projectDocument = $project->publicDocuments()->get((int) $document);

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


