<?php

namespace App\Http\Controllers;

use App\Models\Project;
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

        return view('pages.procurement-show', compact('project'));
    }
}
