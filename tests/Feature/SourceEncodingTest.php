<?php

use Symfony\Component\Finder\Finder;

/*
 * Guards against double-encoded text (UTF-8 saved after being read as
 * Windows-1252), which shows on the pages as "â‚±" for ₱ or "Â·" for ·.
 */
it('has no double-encoded characters in the app, views, scripts or styles', function () {
    // A lead byte of a UTF-8 sequence (Ã Â â ð) followed by a Windows-1252 continuation character.
    $mojibake = '/[ÃÂâð][\x{0080}-\x{00BF}\x{0152}\x{0153}\x{0160}\x{0161}\x{0178}\x{017D}\x{017E}\x{0192}\x{02C6}\x{02DC}\x{2013}\x{2014}\x{2018}-\x{201E}\x{2020}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2122}]/u';

    $files = Finder::create()->files()
        ->in([base_path('app'), base_path('resources'), base_path('routes'), base_path('config')])
        ->name(['*.php', '*.js', '*.css']);

    $broken = [];
    foreach ($files as $file) {
        foreach (preg_split('/\R/', $file->getContents()) as $index => $line) {
            if (preg_match($mojibake, $line)) {
                $broken[] = $file->getRelativePathname().':'.($index + 1);
            }
        }
    }

    expect($broken)->toBe([]);
});
