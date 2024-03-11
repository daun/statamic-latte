<?php

namespace Tests\Concerns;

use Illuminate\Http\Request as LaravelRequest;
use Statamic\Http\Controllers\FrontendController;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

trait MocksFrontendRequests
{
    protected function createRequest(
        $uri = '/',
        $method = 'GET',
        $parameters = [],
        $cookies = [],
        $files = [],
        $server = ['CONTENT_TYPE' => 'text/html'],
        $content = null,
    ) {
        return (new LaravelRequest)->createFromBase(
            SymfonyRequest::create(
                $uri,
                $method,
                $parameters,
                $cookies,
                $files,
                $server,
                $content
            )
        );
    }

    protected function getFrontendResponse(...$params)
    {
        $request = $this->createRequest(...$params);

        return app(FrontendController::class)
            ->index($request)
            ->toResponse($request);

    }
}
