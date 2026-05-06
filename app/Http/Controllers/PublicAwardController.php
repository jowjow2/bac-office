<?php

namespace App\Http\Controllers;

use App\Models\Award;
use App\Models\AuditLog;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PublicAwardController extends Controller
{
    public function index(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        try {
            $awards = Schema::hasTable('awards')
                ? Award::query()
                    ->with(['project', 'bid.user'])
                    ->when($query !== '', function ($builder) use ($query) {
                        $builder->where(function ($nested) use ($query) {
                            $nested->whereHas('project', function ($projectQuery) use ($query) {
                                $projectQuery->where('title', 'like', "%{$query}%")
                                    ->orWhere('description', 'like', "%{$query}%");
                            })->orWhereHas('bid.user', function ($userQuery) use ($query) {
                                $userQuery->where('name', 'like', "%{$query}%")
                                    ->orWhere('company', 'like', "%{$query}%");
                            });
                        });
                    })
                    ->latest('contract_date')
                    ->get()
                : collect();

        if (Schema::hasColumn('awards', 'certificate_number')) {
            $awards->each->ensureCertificateIdentity();
        }
        } catch (\Throwable) {
            $awards = collect();
        }

        return view('pages.awards', compact('awards', 'query'));
    }

    /**
     * Display the award certificate PDF by secure token (public)
     * URL: /certificate/{token}
     */
    public function showByToken(string $token)
    {
        $award = Award::where('qr_token', $token)->first();
        abort_unless($award, 404, 'Invalid or unavailable certificate.');

        $award->ensureCertificateIdentity();

        // Check certificate validity
        if (!$award->isCertificateViewable()) {
            abort(404, 'Invalid or unavailable certificate.');
        }

        // Check file exists
        if (!$award->hasCertificateFile()) {
            abort(404, 'Invalid or unavailable certificate.');
        }

        // Audit log: certificate viewed
        AuditLog::log('certificate_viewed', $award, [], [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $filePath = $award->certificate_file_path;
        $fileName = $award->getCertificateFileName();

        // Security headers and inline display
        return response()->file(
            Storage::disk('local')->path($filePath),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    /**
     * Generate QR code image by secure token (public)
     * URL: /qr/{token}.svg
     */
    public function qrByToken(string $token)
    {
        $award = Award::where('qr_token', $token)->first();
        abort_unless($award, 404);

        $award->ensureCertificateIdentity();
        abort_unless($award->isCertificateViewable(), 404);

        $renderer = new ImageRenderer(
            new RendererStyle(360, 4),
            new SvgImageBackEnd()
        );

        // QR code should contain the token-based certificate URL
        $url = route('certificate.view', ['token' => $award->qr_token]);

        $svg = (new Writer($renderer))->writeString($url);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function winnerName(Award $award): string
    {
        return $award->bid?->user?->company
            ?: ($award->bid?->user?->name ?? 'N/A');
    }
}
