<?php

describe('layout', function () {
    test('resolves the default layout', function () {
        $response = $this->getFrontendResponse('/testable');

        expect($response->getContent())
            ->toContain('<title>Testable</title>')
            ->toContain('<h1>Testable</h1>');
    });

    test('resolves a custom layout', function () {
        $response = $this->getFrontendResponse('/testable-with-layout');

        expect($response->getContent())
            ->toContain('<title>Custom: Testable With Layout</title>')
            ->toContain('<h1>Testable With Layout</h1>');
    });
});
