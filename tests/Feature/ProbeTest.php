<?php

use Statamic\Facades\Entry;

function probeEntry()
{
    return Entry::query()->where('collection', 'pages')->where('slug', 'testable-html')->first();
}

test('probe: strict types config', function () {
    dump('strict_types: '.var_export(config('latte.strict_types'), true));
    expect(true)->toBeTrue();
});

test('probe: latte own Html through upper', function () {
    try {
        $out = $this->latte('{capture $x}<b>hi</b>{/capture}{$x|upper}')->__toString();
        dump('capture|upper OK: '.$out);
    } catch (\Throwable $e) {
        dump('capture|upper THREW: '.$e::class.' — '.$e->getMessage());
    }
    expect(true)->toBeTrue();
});

test('probe: HtmlValue through core filters', function () {
    $filters = [
        'upper', 'lower', 'capitalize', 'firstUpper', 'truncate:20', 'substr:0,5',
        'stripHtml', 'stripTags', 'trim', 'spaceless', 'indent', 'repeat:2',
        'replace:"a","b"', 'breaklines', 'length', 'webalize', 'checkUrl',
        'padLeft:100', 'batch:2', 'implode', 'reverse', 'escapeHtml',
    ];

    foreach ($filters as $filter) {
        try {
            $out = $this->latte('{$page->content|'.$filter.'}', ['page' => probeEntry()])->__toString();
            dump("|$filter → ".mb_substr($out, 0, 60));
        } catch (\Throwable $e) {
            dump("|$filter THREW: ".$e::class.' — '.mb_substr($e->getMessage(), 0, 120));
        }
    }

    expect(true)->toBeTrue();
});
