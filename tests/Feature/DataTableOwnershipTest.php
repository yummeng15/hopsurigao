<?php

test('admin tables belong to the project and do not import the external table package', function () {
    $root = dirname(__DIR__, 2);

    expect(file_get_contents($root.'/package.json'))->not->toContain('kikiloaw')
        ->and(file_get_contents($root.'/resources/css/app.css'))->not->toContain('kikiloaw')
        ->and(file_exists($root.'/resources/js/components/tables/DataTable.vue'))->toBeTrue();

    $pages = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/resources/js/pages')
    );

    foreach ($pages as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'vue') {
            continue;
        }

        expect(file_get_contents($file->getPathname()))->not->toContain('kikiloaw');
    }
});
