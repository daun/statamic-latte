<?php

/*
 * CLASSIFICATION PER METHOD
 *
 * form:create   — OK (data-array path): when canParseContents()=false (always in Latte proxy),
 *                 create() returns an associative data array, NOT rendered <form> HTML.
 *                 Normalizer wraps it in a Content object. Pair body receives $value = Content.
 *                 Access $value->fields (list), $value->honeypot (string), $value->attrs (Content),
 *                 etc. Recommended Latte idiom: use `as: form` to capture, then build <form>
 *                 manually and loop $form->fields directly in Latte foreach.
 *
 * form:errors   — PARTIALLY OK / BEHAVIOUR SHIFT: In the Latte proxy $this->content is always ''
 *                 (body is Latte, not handed to the tag). errors() detects this via
 *                 `$this->content === ''` and returns a boolean instead of a loop. Result: false
 *                 (no errors) → body skipped (D2); true (errors exist) → body renders with
 *                 $value = true, NOT individual error strings. For error iteration, read
 *                 $value->errors / $value->error from a form:create data capture.
 *
 * form:success  — OK: returns the session success string or null. null → body skipped (D2).
 *
 * form:fields   — I6-LIMITED: calls Antlers::parse() on $this->content which is '' in the
 *                 proxy. Also reads $this->context['fields'] which is not populated outside an
 *                 Antlers form:create pair. Returns empty string. Use $form->fields from a
 *                 form:create as: capture instead.
 *
 * form:set      — I6-LIMITED: calls $this->parse() which returns [] (empty array) when no
 *                 parser/tagRenderer is set. Injects form handle into context (useless in Latte).
 *                 Use `in:` directly on each tag instead.
 *
 * form:submissions — OK (iterable path): returns query results; no submissions in test env.
 */

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Statamic\Facades\Blink;

/**
 * Seed the session the way a real (failed/successful) Statamic form submission
 * would, so the post-submission state can be tested without an HTTP POST.
 * Statamic reads this state from the session keyed by `form.{handle}`
 * (see Statamic\Tags\Concerns\GetsFormSession).
 */
function seedFormErrors(array $errors): void
{
    $bag = new ViewErrorBag;
    $bag->put('form.contact', new MessageBag($errors));
    session()->put('errors', $bag);
}

beforeEach(function () {
    // Wire the forms directory to the test fixture.
    // statamic.forms.forms is the config key read by FormRepository::find() / all().
    config(['statamic.forms.forms' => fixtures_path('forms')]);

    // Clear Blink caches so each test gets a fresh form resolve.
    Blink::store()->flush();
});

// ---------------------------------------------------------------------------
// form:create — data-array path (canParseContents() = false in Latte proxy)
// ---------------------------------------------------------------------------

describe('form:create', function () {
    test('self-closing outputs nothing — returns data array, not HTML', function () {
        // CLASSIFY: OK (data-array path)
        // create() returns an associative array → Content wrapper.
        // stringifyResult(Content) = '' because Content is not scalar/Stringable.
        $this->latte('{s:form:create in: contact /}')
            ->assertDontSee('<form', false)
            ->assertDontSee('</form>', false);
    });

    test('pair body receives $value as Content with form data', function () {
        // CLASSIFY: OK
        // create() returns array; Normalizer wraps it in Content (not iterable via proxy).
        // Body executes once with $value = Content.
        // $value->honeypot defaults to 'honeypot' per the Form model getter.
        $this->latte('{s:form:create in: contact}{$value->honeypot}{/s:form:create}')
            ->assertSee('honeypot');
    });

    test('fields list is accessible from $value->fields', function () {
        // CLASSIFY: OK
        // $value->fields is a sequential array of field-data Content objects,
        // resolved from the form blueprint (name, email).
        $this->latte(<<<'LATTE'
            {s:form:create in: contact}
                {foreach $value->fields as $f}{$f->handle} {/foreach}
            {/s:form:create}
        LATTE)
            ->assertSee('name')
            ->assertSee('email');
    });

    test('attrs array carries method and action', function () {
        // CLASSIFY: OK
        // $value->attrs is Content(['method' => 'POST', 'action' => '...']).
        $this->latte('{s:form:create in: contact}{$value->attrs->method}{/s:form:create}')
            ->assertSee('POST');
    });

    test('recommended Latte idiom: capture with as: and build form manually', function () {
        // CLASSIFY: OK — RECOMMENDED IDIOM
        // Since create() never emits <form> HTML in the Latte proxy, build the wrapper
        // manually and loop $form->fields in a native Latte {foreach}.
        $this->latte(<<<'LATTE'
            {s:form:create as: form, in: contact}
                <form method="post">
                    {foreach $form->fields as $field}
                        <input name="{$field->handle}">
                    {/foreach}
                </form>
            {/s:form:create}
        LATTE)
            ->assertSee('<form method="post">', false)
            ->assertSee('name="name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('</form>', false);
    });
});

// ---------------------------------------------------------------------------
// form:errors — boolean path (content always '' in proxy)
// ---------------------------------------------------------------------------

describe('form:errors', function () {
    test('pair body is skipped when there are no session errors', function () {
        // CLASSIFY: OK (boolean path)
        // errors() detects $this->content === '' and returns bool.
        // No session errors → false → proxy skips body (D2 check).
        $this->latte('{s:form:errors in: contact}HAS ERRORS{/s:form:errors}')
            ->assertDontSee('HAS ERRORS');
    });

    test('self-closing returns false (stringified to empty) with no errors', function () {
        // CLASSIFY: OK (boolean path)
        // Booleans are stringified to '' by stringifyResult.
        $this->latte('[{s:form:errors in: contact /}]')
            ->assertSee('[]', false);
    });

    test('errors boolean usable in {if} helper', function () {
        // CLASSIFY: OK
        // s() helper fetches the boolean; {if false} skips the body.
        $this->latte("{if s('form:errors', ['in' => 'contact'])}HAS ERRORS{/if}")
            ->assertDontSee('HAS ERRORS');
    });

    test('individual errors must be read from form:create data, not form:errors pair', function () {
        // CLASSIFY: BEHAVIOUR SHIFT — see header comment
        // Since errors() always returns boolean in proxy context, error iteration
        // is done via $value->errors (array) from a form:create capture.
        // With no submission, errors = [].
        $this->latte('{s:form:create as: form, in: contact}[{foreach $form->errors as $err}{$err}{/foreach}]{/s:form:create}')
            ->assertSee('[]', false); // No errors → empty loop between the delimiters
    });
});

// ---------------------------------------------------------------------------
// form:success — returns session string or null
// ---------------------------------------------------------------------------

describe('form:success', function () {
    test('pair body is skipped with no session success message', function () {
        // CLASSIFY: OK
        // success() returns null from session; proxy D2 check: null → body skipped.
        $this->latte('{s:form:success in: contact}SUCCESS{/s:form:success}')
            ->assertDontSee('SUCCESS');
    });

    test('self-closing outputs nothing with no success message', function () {
        // CLASSIFY: OK
        // null → stringifyResult → '' .
        $this->latte('[{s:form:success in: contact /}]')
            ->assertSee('[]', false);
    });

    test('success boolean usable in {if} helper', function () {
        // CLASSIFY: OK
        // s() returns null; null is falsy; body skipped.
        $this->latte("{if s('form:success', ['in' => 'contact'])}YES{/if}")
            ->assertDontSee('YES');
    });
});

// ---------------------------------------------------------------------------
// form:fields — I6-limited (calls Antlers::parse on '' content)
// ---------------------------------------------------------------------------

describe('form:fields', function () {
    test('throws ErrorException — context[fields] not injected by proxy', function () {
        // CLASSIFY: I6-LIMITED
        // fields() does collect($this->context['fields']) immediately. In the Latte proxy
        // $this->context is an empty-ish collection — 'fields' key does not exist.
        // Collection::offsetGet('fields') throws ErrorException "Undefined array key 'fields'".
        // Recommended Latte idiom: use $form->fields from a form:create as: capture instead.
        expect(fn () => $this->latte('{s:form:fields in: contact}{$f->handle}{/s:form:fields}'))
            ->toThrow(ErrorException::class, 'Undefined array key');
    });
});

// ---------------------------------------------------------------------------
// form:set — I6-limited (parse() returns [] without parser/tagRenderer)
// ---------------------------------------------------------------------------

describe('form:set', function () {
    test('outputs nothing — parse() returns [] in proxy context', function () {
        // CLASSIFY: I6-LIMITED
        // set() injects context['form'] and calls $this->parse() which returns []
        // (no parser set). Empty list → nothing output. Use `in:` directly.
        $this->latte('[{s:form:set in: contact /}]')
            ->assertSee('[]', false);
    });
});

// ---------------------------------------------------------------------------
// Post-submission state — session seeded as a real submission would (no HTTP POST)
// ---------------------------------------------------------------------------

describe('form after a failed submission', function () {
    test('form:errors boolean gate renders the body when errors exist', function () {
        // CLASSIFY: OK — errors() returns true (content is '' in proxy) → body renders (D2).
        seedFormErrors(['name' => ['The name field is required.']]);

        $this->latte('{s:form:errors in: contact}HAS ERRORS{/s:form:errors}')
            ->assertSee('HAS ERRORS');
    });

    test('form:errors boolean is true in the {if} helper', function () {
        // CLASSIFY: OK
        seedFormErrors(['email' => ['Invalid email.']]);

        $this->latte("{if s('form:errors', ['in' => 'contact'])}OOPS{/if}")
            ->assertSee('OOPS');
    });

    test('individual error strings come from the form:create capture', function () {
        // CLASSIFY: OK — RECOMMENDED IDIOM for iterating errors in Latte.
        seedFormErrors(['name' => ['The name field is required.']]);

        $this->latte('{s:form:create as: form, in: contact}[{foreach $form->errors as $e}{$e}{sep}|{/sep}{/foreach}]{/s:form:create}')
            ->assertSee('[The name field is required.]', false);
    });

    test('per-field first error is available via $form->error', function () {
        // CLASSIFY: OK — getFormSession exposes `error` keyed by field handle.
        seedFormErrors(['name' => ['The name field is required.', 'Too short.']]);

        $this->latte('{s:form:create as: form, in: contact}{$form->error->name}{/s:form:create}')
            ->assertSee('The name field is required.');
    });
});

describe('form after a successful submission', function () {
    test('form:success exposes the flashed message as $value', function () {
        // CLASSIFY: OK — success() returns the session string; pair body sees it as $value.
        session()->put('form.contact.success', 'Thanks for your submission!');

        $this->latte('{s:form:success in: contact}{$value}{/s:form:success}')
            ->assertSee('Thanks for your submission!');
    });

    test('form:success is truthy in the {if} helper after submission', function () {
        // CLASSIFY: OK
        session()->put('form.contact.success', 'Done.');

        $this->latte("{if s('form:success', ['in' => 'contact'])}SENT{/if}")
            ->assertSee('SENT');
    });
});
