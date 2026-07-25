<?php

/*
 * The Latte proxy never hands a tag-pair body to the form tags, which shifts
 * some behaviour vs Antlers:
 *  - form:create returns a data array (never <form> HTML); recommended idiom
 *    is `as: form`, then build the <form> and loop $form->fields yourself.
 *  - form:errors sees empty content and returns a boolean instead of looping;
 *    iterate individual errors via $form->errors from a form:create capture.
 *  - form:fields and form:set depend on Antlers parsing and are unusable.
 */

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Statamic\Facades\Blink;
use Statamic\Facades\Form;

/** Seed the session like a failed Statamic submission would (see GetsFormSession). */
function seedFormErrors(array $errors): void
{
    $bag = new ViewErrorBag;
    $bag->put('form.contact', new MessageBag($errors));
    session()->put('errors', $bag);
}

beforeEach(function () {
    config(['statamic.forms.forms' => fixtures_path('forms')]);
    Blink::store()->flush();
});

describe('form:create', function () {
    test('self-closing outputs nothing — returns data array, not HTML', function () {
        $this->latte('{s:form:create in: contact /}')
            ->assertDontSee('<form', false)
            ->assertDontSee('</form>', false);
    });

    test('pair body receives $value as Content with form data', function () {
        $this->latte('{s:form:create in: contact}{$value->honeypot}{/s:form:create}')
            ->assertSee('honeypot');
    });

    test('fields list is accessible from $value->fields', function () {
        $this->latte(<<<'LATTE'
            {s:form:create in: contact}
                {foreach $value->fields as $f}{$f->handle} {/foreach}
            {/s:form:create}
        LATTE)
            ->assertSee('name')
            ->assertSee('email');
    });

    test('attrs array carries method and action', function () {
        $this->latte('{s:form:create in: contact}{$value->attrs->method}{/s:form:create}')
            ->assertSee('POST');
    });

    test('recommended Latte idiom: capture with as: and build form manually', function () {
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

describe('form:errors', function () {
    test('pair body is skipped when there are no session errors', function () {
        $this->latte('{s:form:errors in: contact}HAS ERRORS{/s:form:errors}')
            ->assertDontSee('HAS ERRORS');
    });

    test('self-closing returns false (stringified to empty) with no errors', function () {
        $this->latte('[{s:form:errors in: contact /}]')
            ->assertSee('[]', false);
    });

    test('errors boolean usable in {if} helper', function () {
        $this->latte("{if s('form:errors', ['in' => 'contact'])}HAS ERRORS{/if}")
            ->assertDontSee('HAS ERRORS');
    });

    test('individual errors must be read from form:create data, not form:errors pair', function () {
        $this->latte('{s:form:create as: form, in: contact}[{foreach $form->errors as $err}{$err}{/foreach}]{/s:form:create}')
            ->assertSee('[]', false);
    });
});

describe('form:success', function () {
    test('pair body is skipped with no session success message', function () {
        $this->latte('{s:form:success in: contact}SUCCESS{/s:form:success}')
            ->assertDontSee('SUCCESS');
    });

    test('self-closing outputs nothing with no success message', function () {
        $this->latte('[{s:form:success in: contact /}]')
            ->assertSee('[]', false);
    });

    test('success boolean usable in {if} helper', function () {
        $this->latte("{if s('form:success', ['in' => 'contact'])}YES{/if}")
            ->assertDontSee('YES');
    });
});

describe('form:fields', function () {
    test('throws ErrorException — context[fields] not injected by proxy', function () {
        expect(fn () => $this->latte('{s:form:fields in: contact}{$f->handle}{/s:form:fields}'))
            ->toThrow(ErrorException::class, 'Undefined array key');
    });
});

describe('form:set', function () {
    test('outputs nothing — parse() returns [] in proxy context', function () {
        $this->latte('[{s:form:set in: contact /}]')
            ->assertSee('[]', false);
    });
});

describe('form after a failed submission', function () {
    test('form:errors boolean gate renders the body when errors exist', function () {
        seedFormErrors(['name' => ['The name field is required.']]);

        $this->latte('{s:form:errors in: contact}HAS ERRORS{/s:form:errors}')
            ->assertSee('HAS ERRORS');
    });

    test('form:errors boolean is true in the {if} helper', function () {
        seedFormErrors(['email' => ['Invalid email.']]);

        $this->latte("{if s('form:errors', ['in' => 'contact'])}OOPS{/if}")
            ->assertSee('OOPS');
    });

    test('individual error strings come from the form:create capture', function () {
        seedFormErrors(['name' => ['The name field is required.']]);

        $this->latte('{s:form:create as: form, in: contact}[{foreach $form->errors as $e}{$e}{sep}|{/sep}{/foreach}]{/s:form:create}')
            ->assertSee('[The name field is required.]', false);
    });

    test('per-field first error is available via $form->error', function () {
        seedFormErrors(['name' => ['The name field is required.', 'Too short.']]);

        $this->latte('{s:form:create as: form, in: contact}{$form->error->name}{/s:form:create}')
            ->assertSee('The name field is required.');
    });
});

describe('form after a successful submission', function () {
    test('form:success exposes the flashed message as $value', function () {
        session()->put('form.contact.success', 'Thanks for your submission!');

        $this->latte('{s:form:success in: contact}{$value}{/s:form:success}')
            ->assertSee('Thanks for your submission!');
    });

    test('form:success is truthy in the {if} helper after submission', function () {
        session()->put('form.contact.success', 'Done.');

        $this->latte("{if s('form:success', ['in' => 'contact'])}SENT{/if}")
            ->assertSee('SENT');
    });
});

describe('form:submission', function () {
    test('exposes the submission data after a successful submit', function () {
        $submission = Form::find('contact')->makeSubmission()->data(['name' => 'Grace']);
        session()->put('form.contact.success', 'Thanks!');
        session()->put('submission', $submission);

        $this->latte('{s:form:submission in: contact}{$value->name}{/s:form:submission}')
            ->assertSee('Grace');
    });

    test('renders nothing without a successful submission', function () {
        $this->latte('[{s:form:submission in: contact}{$value->name}{/s:form:submission}]')
            ->assertSee('[]', false);
    });
});

describe('form:submissions', function () {
    test('renders nothing when a form has no submissions', function () {
        $this->latte('[{s:form:submissions in: contact}{$value->name}{/s:form:submissions}]')
            ->assertSee('[]', false);
    });

    test('iterates stored submissions exposing their data', function () {
        $submission = Form::find('contact')->makeSubmission()->data(['name' => 'Ada', 'email' => 'ada@example.com']);
        $submission->save();

        try {
            $this->latte('{s:form:submissions in: contact}{$value->name}{sep}, {/sep}{/s:form:submissions}')
                ->assertSee('Ada');
        } finally {
            $submission->delete();
        }
    });
});
