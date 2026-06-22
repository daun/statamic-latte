<?php

use Statamic\Tags\Svg;

// CLASSIFY: OK — fixture SVG at tests/fixtures/svg/logo.svg; tests assert inline SVG markup

describe('svg', function () {
    beforeEach(function () {
        // Point public_path() at the fixtures root so public_path('svg/logo.svg') resolves.
        // Svg tag cascade: public_path('svg') + 'logo.svg' = tests/fixtures/svg/logo.svg.
        $this->app->usePublicPath(fixtures_path());

        // Disable sanitizer to avoid DOMSanitizer stripping our simple test SVG.
        Svg::disableSanitization();
    });

    afterEach(function () {
        Svg::enableSanitization();
    });

    test('inlines svg markup for known file', function () {
        $this->latte('{s:svg src: "logo"/}')
            ->assertSee('<svg', false)
            ->assertSee('<circle', false);
    });

    test('self-closing form returns full svg element', function () {
        $this->latte('{s:svg src: "logo"/}')
            ->assertSee('xmlns="http://www.w3.org/2000/svg"', false);
    });

    test('class param is injected onto svg element', function () {
        $this->latte('{s:svg src: "logo", class: "icon"/}')
            ->assertSee('class="icon"', false)
            ->assertSee('<svg', false);
    });

    test('pair body receives svg string — latte auto-escapes html chars in $value', function () {
        // The tag returns a raw HTML string; Latte escapes it inside the pair body.
        $this->latte('{s:svg src: "logo"}{$value}{/s:svg}')
            ->assertSee('&lt;svg', false)
            ->assertSee('circle', false);
    });

    test('returns empty string for unknown file', function () {
        $this->latte('[{s:svg src: "does-not-exist"/}]')
            ->assertSee('[]', false);
    });

    test('surrounding static content renders alongside svg', function () {
        $this->latte('icon: {s:svg src: "logo"/} end')
            ->assertSee('icon:')
            ->assertSee('<svg', false)
            ->assertSee('end');
    });
});
