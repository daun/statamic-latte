<?php

use Statamic\Facades\Cascade;
use Statamic\Facades\Data;
use Statamic\Facades\Entry;
use Statamic\Structures\Page;

function pageCascade(string $slug = 'testable'): array
{
    $entry = Entry::query()->where('slug', $slug)->first();

    return Cascade::withContent($entry)->hydrate()->toArray();
}

function requestCascade(string $uri = '/testable'): array
{
    $content = Data::findByRequestUrl('http://localhost'.$uri);

    return Cascade::withContent($content)->hydrate()->toArray();
}

describe('cascade mode', function () {
    test('keeps page fields when set to full', function () {
        config()->set('statamic-latte.cascade', 'full');

        $this->latte('[{$title}][{$page->title}]', pageCascade())
            ->assertSee('[Testable][Testable]', false);
    });

    test('strips page fields by default', function () {
        $this->latte(
            '[{$title ?? "none"}][{$url ?? "none"}][{$page->title}][{$page->url}]',
            pageCascade()
        )->assertSee('[none][none][Testable][/testable]', false);
    });

    test('strips page fields when the page is a structure page', function () {
        expect(requestCascade()['page'])->toBeInstanceOf(Page::class);

        $this->latte(
            '[{$title ?? "none"}][{$url ?? "none"}][{$page->title}][{$page->url}]',
            requestCascade()
        )->assertSee('[none][none][Testable][/testable]', false);
    });

    test('leaves non-page cascade variables alone', function () {
        $this->latte('[{$homepage}][{$environment}]', pageCascade())
            ->assertSee('[/][testing]', false);
    });

    test('keeps variables passed explicitly under a page field name', function () {
        $this->latte('[{$title}]', [...pageCascade(), 'title' => 'Passed in'])
            ->assertSee('[Passed in]', false);
    });

    test('leaves the cascade itself untouched, so tag context survives', function () {
        $this->latte('[{$title ?? "none"}]', pageCascade())->assertSee('[none]', false);

        expect(Cascade::instance()->toArray())->toHaveKeys(['title', 'url', 'id']);
    });

    test('renders antlers islands off the page object', function () {
        $this->latte('{antlers}{{ page:title }}{/antlers}', pageCascade())
            ->assertSee('Testable', false);
    });

    test('resolves layouts with page fields stripped', function () {
        expect($this->getFrontendResponse('/testable')->getContent())
            ->toContain('<title>Testable</title>')
            ->toContain('<h1>Testable</h1>');
    });
});
