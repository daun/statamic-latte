<?php

namespace Daun\StatamicLatte\Extensions;

use Daun\StatamicLatte\Extensions\Nodes\StatamicNode;
use Latte\Engine;
use Latte\Essential\CoreExtension;
use Latte\Extension;
use Statamic\Statamic;

/**
 * Latte extension for using Antlers tags in Latte templates.
 */
class TagExtension extends Extension
{
    protected Engine $latte;

    protected CoreExtension $core;

    protected array $supported = [
        'asset',
        'assets',
        // 'cache',
        'can',
        'children',
        'collection',
        'cookie',
        'dd',
        'ddd',
        'dump',
        'get_content',
        'get_error',
        'get_errors',
        'get_files',
        'glide',
        // 'in',
        // 'increment',
        'installed',
        // 'is',
        'iterate',
        // 'foreach',
        'link',
        'locales',
        'markdown',
        'member',
        'mix',
        'mount_url',
        'nav',
        'not_found',
        // '404',
        'obfuscate',
        // 'parent',
        // 'partial',
        'path',
        'query',
        // 'range',
        // 'loop',
        'redirect',
        'relate',
        // 'rotate',
        // 'switch',
        'route',
        'scope',
        'set',
        // 'section',
        'session',
        'structure',
        'svg',
        'taxonomy',
        'theme',
        // 'trans',
        // 'trans_choice',
        'user_groups',
        'users',
        'user_roles',
        'vite',
        'widont',
        // 'yields',
        // 'yield',
        'form',
        'user',
        'protect',
        'oauth',
        'search',
        // 'nocache',
    ];

    public function __construct(Engine $latte)
    {
        $this->latte = $latte;
        $this->core = new CoreExtension();
    }

    public function getTags(): array
    {
        return app('statamic.tags')
            ->only($this->supported)
            ->except($this->getCoreTags())
            ->map(fn () => [StatamicNode::class, 'create'])
            ->tap(fn ($tags) => $tags->put('statamic', [StatamicNode::class, 'create']))
            ->all();
    }

    protected function getCoreTags(): array
    {
        return array_keys($this->core->getTags());
    }

    /**
     * Returns a list of functions used in templates.
     * @return array<string, callable>
     */
    public function getFunctions(): array
    {
        return [
            'statamic' => [$this, 'statamic'],
            's' => [$this, 'statamic'],
        ];
    }

    public function statamic(string $name, ...$args): mixed
    {
        $params = $args;

        // Allow passing in params as a single array argument
        foreach ($args as $key => $arg) {
            if (is_int($key) && is_array($arg) && ! array_is_list($arg)) {
                $params = array_merge($params, $arg);
                unset($params[$key]);
            }
        }

        return Statamic::tag($name)->params($params)->fetch();
    }
}
