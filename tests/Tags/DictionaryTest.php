<?php

// Countries items expose: name, iso2, iso3, region, subregion, emoji, label, value, key.

describe('dictionary', function () {
    test('iterates countries dictionary and exposes name via $value->name', function () {
        $this->latte('{s:dictionary handle: "countries"}{$value->name}{sep},{/sep}{/s:dictionary}')
            ->assertSee('Germany');
    });

    test('exposes iso2 field via $value->iso2', function () {
        $this->latte('{s:dictionary handle: "countries"}{$value->iso2}{sep},{/sep}{/s:dictionary}')
            ->assertSee('DE'); // Germany iso2
    });

    test('supports wildcard tag method shorthand: s:dictionary:countries', function () {
        $this->latte('{s:dictionary:countries}{$value->name}{sep},{/sep}{/s:dictionary:countries}')
            ->assertSee('Germany');
    });

    test('supports as: param to capture the full options collection', function () {
        $this->latte('{s:dictionary handle: "countries", as: options}{foreach $options as $o}{$o->iso2}{sep},{/sep}{/foreach}{/s:dictionary}')
            ->assertSee('DE');
    });

    test('iterates timezones dictionary and exposes value', function () {
        $this->latte('{s:dictionary handle: "timezones"}{$value->value}{sep},{/sep}{/s:dictionary}')
            ->assertSee('UTC');
    });
});
