<?php

// Load the shared vendor autoloader (symlinked from main repo).
$loader = require __DIR__ . '/../vendor/autoload.php';

// Prepend the worktree's app/ and database/ directories so new/changed classes
// in this branch are found before falling back to the main repo.
$loader->addPsr4('App\\',      [__DIR__ . '/../app/'],      true);
$loader->addPsr4('Database\\', [__DIR__ . '/../database/'], true);

// The vendor classmap takes precedence over PSR-4 for classes that already existed
// in the main repo. Override classmap entries for all PHP files in this worktree's
// app/ so that modified files are loaded from the worktree, not the main repo.
$worktreeAppDir = __DIR__ . '/../app';
$classMapOverrides = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($worktreeAppDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $relativePath = substr($file->getPathname(), strlen($worktreeAppDir) + 1);
    // Convert path to class name: App\Modules\Operations\... etc
    $className = 'App\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
    $classMapOverrides[$className] = $file->getPathname();
}

$loader->addClassMap($classMapOverrides);
