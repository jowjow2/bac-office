<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProcurementController extends Controller
{
    /**
     * Display a listing of procurements (public projects).
     */
    public function index()
    {
        try {
            $projects = Schema::hasTable('projects')
                ? Project::query()->visibleToPublic()->with('documents')->latest()->get()
                : collect();
        } catch (\Throwable) {
            $projects = collect();
        }

        return view('procurements.index', compact('projects'));
    }

    /**
     * Display the create procurement form.
     */
    public function create()
    {
        return view('procurements.create');
    }

    /**
     * Display published procurements.
     */
    public function publish()
    {
        try {
            $projects = Schema::hasTable('projects')
                ? Project::query()->visibleToPublic()->with('documents')->get()
                : collect();
        } catch (\Throwable) {
            $projects = collect();
        }

        return view('procurements.publish', compact('projects'));
    }
}
