<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            \App\Http\Middleware\ApplyProcurementClock::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\TouchUserPresence::class,
        ]);

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'staff' => \App\Http\Middleware\StaffMiddleware::class,
            'bidder' => \App\Http\Middleware\BidderMiddleware::class,
            'approved.bidder' => \App\Http\Middleware\ApprovedBidderMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // One short line per error first: serverless logs (Vercel) keep only the
        // end of a long entry, which cut the message off the full stack trace.
        $exceptions->report(function (\Throwable $exception) {
            if (app()->runningUnitTests()) {
                return;
            }
            $relative = fn (string $file) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            // The first frames in the app's own code (not vendor), where the cause usually is.
            $frames = collect($exception->getTrace())
                ->filter(fn ($frame) => isset($frame['file']) && ! str_contains($frame['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR))
                ->take(3)
                ->map(fn ($frame) => $relative($frame['file']).':'.($frame['line'] ?? '?'))
                ->implode(' < ');
            $previous = $exception->getPrevious();
            error_log(sprintf('[app-error] %s: %s at %s:%d%s%s',
                $exception::class,
                \Illuminate\Support\Str::limit(str_replace(["\r", "\n"], ' ', $exception->getMessage()), 600),
                $relative($exception->getFile()),
                $exception->getLine(),
                $frames !== '' ? ' | via '.$frames : '',
                $previous ? ' | caused by '.$previous::class.': '.\Illuminate\Support\Str::limit(str_replace(["\r", "\n"], ' ', $previous->getMessage()), 300) : ''
            ));

            // Logging to stderr (Vercel): this line is the report; a full stack trace
            // after it would push it out of the platform's truncated log entry.
            if (config('logging.default') === 'stderr') {
                return false;
            }
        });
    })->create();
