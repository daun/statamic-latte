<?php

test('renders s:link tag', function () {
    $this->latte('A link to {s:link to: "fanny-packs"}{$result}{/s:link}')->assertSee('A link to /fanny-packs');
});
