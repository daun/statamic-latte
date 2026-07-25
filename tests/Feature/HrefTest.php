<?php

use Daun\StatamicLatte\Data\Content;
use Daun\StatamicLatte\Latte\Support\Href;
use Illuminate\Http\Request;
use Latte\CompileException;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;

/**
 * Bind a fake current request so URL::getCurrent() (which reads request()->path())
 * resolves to a known page for the current/ancestor comparisons.
 */
function currentPage(string $path): void
{
    app()->instance('request', Request::create($path));
}

describe('Href::resolve', function () {
    test('passes a string URL through unchanged', function () {
        expect(Href::resolve('/about'))->toBe('/about')
            ->and(Href::resolve('https://example.com'))->toBe('https://example.com')
            ->and(Href::resolve('mailto:a@b.com'))->toBe('mailto:a@b.com');
    });

    test('resolves null and empty to null', function () {
        expect(Href::resolve(null))->toBeNull()
            ->and(Href::resolve(''))->toBeNull();
    });

    test('resolves an Entry to its url', function () {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'testable')->first();

        expect($entry)->not->toBeNull()
            ->and(Href::resolve($entry))->toBe($entry->url());
    });

    test('resolves a Content-wrapped Entry to its url', function () {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'testable')->first();

        expect(Href::resolve(Content::wrap($entry)))->toBe($entry->url());
    });

    test('resolves a Term to its url', function () {
        $term = Term::query()->where('taxonomy', 'topics')->first();

        expect($term)->not->toBeNull()
            ->and(Href::resolve($term))->toBe($term->url());
    });

    test('resolves an Asset to its url', function () {
        $asset = Asset::find('assets::img/example.jpg');

        expect($asset)->not->toBeNull()
            ->and(Href::resolve($asset))->toBe($asset->url());
    });

    test('resolves a Site to its url', function () {
        $site = Site::current();

        expect(Href::resolve($site))->toBe($site->url());
    });

    test('resolves an object without a url to null', function () {
        expect(Href::resolve(new stdClass))->toBeNull();
    });
});

describe('n:href attribute', function () {
    test('renders only href for an internal link', function () {
        currentPage('/');

        $this->latte('<a n:href="\'/about\'">Go</a>')
            ->assertSee('<a href="/about">Go</a>', false);
    });

    test('adds target and rel for an external link', function () {
        $this->latte('<a n:href="\'https://example.com\'">Go</a>')
            ->assertSee('<a href="https://example.com" target="_blank" rel="noopener">Go</a>', false);
    });

    test('renders nothing for a null URL', function () {
        $this->latte('<a n:href="$url">Go</a>', ['url' => null])
            ->assertSee('<a>Go</a>', false);
    });

    test('renders nothing for an empty URL', function () {
        $this->latte('<a n:href="$url">Go</a>', ['url' => ''])
            ->assertSee('<a>Go</a>', false);
    });

    test('renders only href for a mailto link', function () {
        $this->latte('<a n:href="\'mailto:hi@example.com\'">Mail</a>')
            ->assertSee('<a href="mailto:hi@example.com">Mail</a>', false);
    });

    test('renders only href for a tel link', function () {
        $this->latte('<a n:href="\'tel:+123456\'">Call</a>')
            ->assertSee('<a href="tel:+123456">Call</a>', false);
    });

    test('escapes the href value', function () {
        $this->latte('<a n:href="$url">Go</a>', ['url' => '/search?a=1&b=2'])
            ->assertSee('href="/search?a=1&amp;b=2"', false);
    });

    test('resolves an Entry passed directly', function () {
        $entry = Entry::query()->where('collection', 'pages')->where('slug', 'testable')->first();

        $this->latte('<a n:href="$entry">Go</a>', ['entry' => $entry])
            ->assertSee('href="'.e($entry->url()).'"', false);
    })->skip(fn () => ! Entry::query()->where('collection', 'pages')->where('slug', 'testable')->first()?->url(), 'entry has no url');
});

describe('guards', function () {
    test('throws when combined with a literal href', function () {
        expect(fn () => $this->latte('<a href="/x" n:href="\'/y\'">Go</a>')->__toString())
            ->toThrow(CompileException::class, 'It is not possible to combine href with n:href');
    });

    test('throws when used as a paired or standalone tag', function () {
        expect(fn () => $this->latte('{href \'/x\'}Go{/href}')->__toString())
            ->toThrow(CompileException::class);
    });
});

describe('overrides', function () {
    test('keeps an author-supplied target on an external link, still adds rel', function () {
        $this->latte('<a target="_self" n:href="\'https://example.com\'">Go</a>')
            ->assertSee('target="_self"', false)
            ->assertSee('rel="noopener"', false)
            ->assertDontSee('target="_blank"', false);
    });

    test('keeps an author-supplied rel on an external link, still adds target', function () {
        $this->latte('<a rel="noopener noreferrer" n:href="\'https://example.com\'">Go</a>')
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('target="_blank"', false)
            ->assertDontSee('rel="noopener"><', false);
    });

    test('keeps an author-supplied aria-current on the current page', function () {
        currentPage('/blog/post');

        expect(Href::attrs('/blog/post', ['aria-current']))->toBe(' href="/blog/post"');
    });

    test('keeps an author-supplied aria-current on an ancestor', function () {
        currentPage('/blog/post');

        expect(Href::attrs('/blog', ['aria-current']))->toBe(' href="/blog"');
    });

    test('override list does not touch href', function () {
        expect(Href::attrs('/x', ['target', 'rel', 'aria-current']))->toBe(' href="/x"');
    });
});

describe('current & ancestor', function () {
    test('marks the current page', function () {
        currentPage('/blog/post');

        expect(Href::attrs('/blog/post'))->toBe(' href="/blog/post" aria-current="page"');
    });

    test('ignores a trailing slash when matching the current page', function () {
        currentPage('/blog/post');

        expect(Href::attrs('/blog/post/'))->toBe(' href="/blog/post/" aria-current="page"');
    });

    test('marks an ancestor of the current page', function () {
        currentPage('/blog/post');

        expect(Href::attrs('/blog'))->toBe(' href="/blog" aria-current="true"');
    });

    test('does not mark the site root as an ancestor', function () {
        currentPage('/blog/post');

        expect(Href::attrs('/'))->toBe(' href="/"');
    });

    test('marks the home link current on the home page', function () {
        currentPage('/');

        expect(Href::attrs('/'))->toBe(' href="/" aria-current="page"');
    });

    test('does not mark an unrelated page', function () {
        currentPage('/blog/post');

        expect(Href::attrs('/about'))->toBe(' href="/about"');
    });
});
