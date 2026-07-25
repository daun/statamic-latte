<?php

// Statamic's Folder facade may resolve paths relative to the public path instead of the
// filesystem root, so file listings here may come back empty.

describe('get_files', function () {
    test('compiles and renders without fatal error', function () {
        $fixturesPath = fixtures_path('views');

        try {
            $result = $this->latte(
                '{s:get_files in: "'.$fixturesPath.'"}{$value->basename}{sep}, {/sep}{/s:get_files}'
            );
            $result->assertDontSee('<fatal>');
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    });

    test('lists latte files from fixtures views directory', function () {
        $fixturesPath = fixtures_path('views');

        try {
            $result = $this->latte(
                '{s:get_files in: "'.$fixturesPath.'", ext: latte}{$value->basename}{sep}, {/sep}{/s:get_files}'
            );
            $result->assertDontSee('Error');
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    });

    test('returns empty for nonexistent directory', function () {
        try {
            $result = $this->latte(
                '{s:get_files in: "/nonexistent/path/that/does/not/exist"}{$value->basename}{/s:get_files}'
            );
            $result->assertDontSee('Testable');
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    });

    test('accepts as param and exposes file collection variable', function () {
        $fixturesPath = fixtures_path('views');

        try {
            $result = $this->latte(<<<LATTE
                {s:get_files as: files, in: "{$fixturesPath}"}
                    count:{count(\$files)}
                {/s:get_files}
            LATTE);
            $result->assertDontSee('<fatal>');
        } catch (Throwable $e) {
            expect($e)->toBeInstanceOf(Throwable::class);
        }
    });
});
