<?php

use Statamic\Facades\Cascade;
use Statamic\View\Antlers\Language\Runtime\LiteralReplacementManager;

beforeEach(function () {
    // Reset the cross-engine content stores so static section state from one
    // test (Cascade, the view factory, and Antlers' literal replacements)
    // doesn't leak into the next, mirroring a fresh request.
    Cascade::instance()->clearSections();
    app('view')->flushSections();
    LiteralReplacementManager::resetLiteralState();
});

describe('section / yield', function () {
    test('yields a section defined earlier in the same template (latte -> latte)', function () {
        $this->latte("{section 'msg'}Hello Latte{/section}[{yield 'msg' /}]")
            ->assertSee('[Hello Latte]', false);
    });

    test('yields a section defined later in the template (forward reference)', function () {
        $this->latte("[{yield 'msg' /}]{section 'msg'}Deferred{/section}")
            ->assertSee('[Deferred]', false);
    });

    test('reads a section defined in antlers (antlers -> latte)', function () {
        $this->latte("{antlers}{{ section:msg }}From Antlers{{ /section:msg }}{/antlers}[{yield 'msg' /}]")
            ->assertSee('[From Antlers]', false);
    });

    test('exposes a latte section to antlers yield (latte -> antlers)', function () {
        $this->latte("{section 'msg'}From Latte{/section}[{antlers}{{ yield:msg }}{/antlers}]")
            ->assertSee('[From Latte]', false);
    });

    test('falls back to inner default content when no section is defined', function () {
        $this->latte("[{yield 'missing'}Fallback{/yield}]")
            ->assertSee('[Fallback]', false);
    });

    test('prefers section content over the inner default', function () {
        $this->latte("{section 'msg'}Real{/section}[{yield 'msg'}Fallback{/yield}]")
            ->assertSee('[Real]', false)
            ->assertDontSee('Fallback');
    });

    test('supports a self-closing yield', function () {
        $this->latte("{section 'a'}AA{/section}[{yield 'a' /}]")
            ->assertSee('[AA]', false);
    });

    test('accepts a dynamic section name', function () {
        $this->latte('{section $name}Dyn{/section}[{yield $name /}]', ['name' => 'block'])
            ->assertSee('[Dyn]', false);
    });
});
