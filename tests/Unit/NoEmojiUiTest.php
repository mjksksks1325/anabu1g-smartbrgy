<?php

it('keeps active interface assets and templates free of emoji', function () {
    $projectDirectory = dirname(__DIR__, 2);

    foreach (['app/Notifications', 'resources/views', 'resources/css', 'resources/js', 'public/css', 'public/js'] as $relativeDirectory) {
        $directory = new RecursiveDirectoryIterator($projectDirectory.'/'.$relativeDirectory, FilesystemIterator::SKIP_DOTS);

        foreach (new RecursiveIteratorIterator($directory) as $file) {
            if (! $file->isFile() || str_contains(strtolower($file->getFilename()), 'backup')) {
                continue;
            }

            $hasEmoji = preg_match('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{FE0F}]/u', file_get_contents($file->getPathname()));

            expect($hasEmoji)->toBe(0, $file->getPathname());
        }
    }
});
