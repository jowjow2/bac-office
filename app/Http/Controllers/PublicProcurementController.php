<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\QrCodeService;
use Illuminate\Http\Request;

class PublicProcurementController extends Controller
{
    public function index(Request $request, QrCodeService $qrCodes)
    {
        $query = trim((string) $request->query('q', ''));

        $projects = Project::query()
            ->visibleToPublic()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($nested) use ($query) {
                    $nested->where('title', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                });
            })
            ->latest()
            ->get()
            ->map(function (Project $project) use ($request, $qrCodes) {
                $publicUrl = $this->publicProjectUrl($request, $project);
                $scanUrl = $this->publicProjectScanUrl($request, $project);

                $project->setAttribute('public_url', $publicUrl);
                $project->setAttribute('scan_url', $scanUrl);
                $project->setAttribute('qr_code_data_uri', $qrCodes->toDataUri($scanUrl, 120));

                return $project;
            });

        return view('pages.procurement', compact('projects', 'query'));
    }

    public function show(Request $request, Project $project, QrCodeService $qrCodes)
    {
        abort_unless(in_array($project->status, Project::PUBLIC_STATUSES, true), 404);

        $project->loadCount('bids');

        $projectUrl = $this->publicProjectUrl($request, $project);
        $projectScanUrl = $this->publicProjectScanUrl($request, $project);

        return view('pages.procurement-show', [
            'project' => $project,
            'projectUrl' => $projectUrl,
            'projectScanUrl' => $projectScanUrl,
            'qrCodeDataUri' => $qrCodes->toDataUri($projectScanUrl, 220),
        ]);
    }

    public function scan(Request $request, Project $project)
    {
        abort_unless(in_array($project->status, Project::PUBLIC_STATUSES, true), 404);

        $user = $request->user();

        if ($user && $user->role === 'bidder' && $project->status === 'open') {
            return redirect()->route('bidder.available-projects', [
                'scan_project' => $project->id,
            ]);
        }

        return redirect()->route('public.procurement.show', $project);
    }

    protected function publicProjectUrl(Request $request, Project $project): string
    {
        return rtrim($request->root(), '/') . route('public.procurement.show', $project, false);
    }

    protected function publicProjectScanUrl(Request $request, Project $project): string
    {
        return rtrim($request->root(), '/') . route('public.procurement.scan', $project, false);
    }
}
