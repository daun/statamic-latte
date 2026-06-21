<?php

// CLASSIFY: FIXTURE — no asset containers configured; tests verify Latte compilation and graceful empty/null output

describe('asset', function () {
    test('compiles self-closing asset tag without crashing', function () {
        // CLASSIFY: FIXTURE — no containers; tag returns null, self-closing outputs nothing
        $this->latte('{s:asset url: "files/image.jpg"/}')
            ->assertSee('');
    });

    test('pair body is skipped when the asset is null', function () {
        // CLASSIFY: OK — null result skips the pair body (Antlers parity); no crash.
        $this->latte('[{s:asset url: "files/image.jpg"}{$value->url}{/s:asset}]')
            ->assertSee('[]', false);
    });

    test('renders surrounding static content', function () {
        // CLASSIFY: FIXTURE — no containers
        $this->latte('img: {s:asset url: "files/image.jpg"/} end')
            ->assertSee('img:')
            ->assertSee('end');
    });

    test('supports as: param to capture asset into variable', function () {
        // CLASSIFY: FIXTURE — no containers; variable is null/empty
        $this->latte('{s:asset as: img, url: "files/image.jpg"}{/s:asset}')
            ->assertSee('');
    });

    test('pair body with container param is also skipped when null', function () {
        // CLASSIFY: OK — container param doesn't change the null result; body still skipped.
        $this->latte('[{s:asset container: "files", url: "image.jpg"}{$value->url}{/s:asset}]')
            ->assertSee('[]', false);
    });
});
