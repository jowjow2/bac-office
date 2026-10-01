<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\BidDocument;
use App\Support\BidDocumentReviewWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BidDocumentReviewController extends Controller
{
    public function reviewSubmission(Request $request, Bid $bid, BidDocumentReviewWorkflow $workflow)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'request_revision'])],
            'requirement_key' => ['required_if:action,request_revision', 'nullable', 'string', 'max:120'],
            'comment' => ['required_if:action,request_revision', 'nullable', 'string', 'min:5', 'max:500'],
        ], [
            'comment.required_if' => 'Enter a short message explaining what the bidder needs to fix.',
            'comment.min' => 'The revision message must be at least 5 characters.',
            'comment.max' => 'The revision message may not exceed 500 characters.',
        ]);

        if ($validated['action'] === 'approve') {
            $count = $workflow->approveSubmission($bid, $request->user());
            $message = $count > 0 ? 'Submission approved. The bidder has been notified.' : 'There are no pending technical documents to approve.';
        } else {
            $workflow->requestRevisionForRequirement(
                $bid,
                $validated['requirement_key'],
                $request->user(),
                trim($validated['comment']),
            );
            $message = 'Revision requested. The bidder has been notified.';
        }

        $route = $request->user()->role === 'admin' ? 'admin.bids' : 'staff.review-bids';
        return redirect()->route($route, $request->user()->role === 'admin' ? ['view_bid' => $bid->id] : [])
            ->with('success', $message);
    }
    public function review(Request $request, Bid $bid, BidDocument $bidDocument, BidDocumentReviewWorkflow $workflow)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'needs_revision'])],
            'comment' => ['nullable', 'string', 'max:1500', 'required_if:status,needs_revision', 'min:5'],
        ], [
            'comment.required_if' => 'Enter a clear reason for requesting a revision.',
            'comment.min' => 'The revision reason must be at least 5 characters.',
        ]);

        $workflow->review($bid, $bidDocument, $request->user(), $validated['status'], trim((string) ($validated['comment'] ?? '')) ?: null);

        return redirect()->route($request->user()->role === 'admin' ? 'admin.bids' : 'staff.review-bids',
            $request->user()->role === 'admin' ? ['view_bid' => $bid->id] : [])
            ->with('success', $validated['status'] === 'accepted' ? 'Document accepted.' : 'Revision requested. The bidder has been notified.');
    }

    public function replace(Request $request, Bid $bid, BidDocument $bidDocument, BidDocumentReviewWorkflow $workflow)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:'.(int) config('bac-office.registration.max_document_size_kb', 20480)],
        ]);

        $workflow->replace($bid, $bidDocument, $request->user(), $request->file('file'));

        return redirect()->route('bidder.my-bids', ['revision_bid' => $bid->id])
            ->with('success', 'Replacement uploaded and sent to the BAC for review. Your previous version remains in the revision history.');
    }
}