<?php

/*
 * Slims vendor/ for the Vercel function bundle (run by `composer vercel` during
 * the Vercel build). Every deployment keeps its own copy of the function, and
 * Hobby counts all of them against Functions Storage.
 *
 * The AWS SDK ships clients and API models for ~430 services (about 44 MB);
 * this app only uses S3 (the optional `s3` uploads disk). Like the SDK's own
 * Aws\Script\Composer\Composer::removeUnusedServices — which cannot run here
 * because Vercel installs with --no-scripts — it removes every other service,
 * keeping S3 and the ones the SDK marks unsafe to delete (credentials, KMS).
 *
 * Runs only on Vercel (VERCEL=1) so a local `composer vercel` never touches
 * your vendor/. Pass a vendor path and --force to try it on a copy.
 */

$force = in_array('--force', $argv, true);
if (getenv('VERCEL') !== '1' && ! $force) {
    fwrite(STDOUT, "vercel-slim-vendor: not a Vercel build, nothing removed.\n");
    exit(0);
}

$vendor = rtrim($argv[1] ?? __DIR__.'/../vendor', '/\\');
if (str_starts_with($vendor, '--')) {
    $vendor = __DIR__.'/../vendor';
}
$sdk = $vendor.'/aws/aws-sdk-php/src';
$manifestFile = $sdk.'/data/manifest.json.php';
if (! is_file($manifestFile)) {
    fwrite(STDOUT, "vercel-slim-vendor: AWS SDK not found, nothing removed.\n");
    exit(0);
}

// Client namespaces to keep: what the app uses, plus what the SDK itself needs.
$keep = ['S3', 'Kms', 'SSO', 'SSOOIDC', 'Sts', 'Signin'];

$removeTree = function (string $path) use (&$removeTree): int {
    if (is_link($path) || is_file($path)) {
        $size = (int) @filesize($path);
        @unlink($path);

        return $size;
    }
    if (! is_dir($path)) {
        return 0;
    }
    $size = 0;
    foreach (scandir($path) as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $size += $removeTree($path.'/'.$entry);
        }
    }
    @rmdir($path);

    return $size;
};

$manifest = require $manifestFile;
$removed = 0;
$bytes = 0;
foreach ($manifest as $model => $attributes) {
    $namespace = $attributes['namespace'] ?? null;
    if ($namespace === null || in_array($namespace, $keep, true)) {
        continue;
    }
    $bytes += $removeTree($sdk.'/'.$namespace) + $removeTree($sdk.'/data/'.$model);
    $removed++;
}

fwrite(STDOUT, sprintf("vercel-slim-vendor: removed %d unused AWS services (%.1f MB).\n", $removed, $bytes / 1048576));
