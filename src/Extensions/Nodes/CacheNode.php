<?php

namespace Daun\StatamicLatte\Extensions\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

/**
 * {cache} ... {/cache}
 * <div n:cache></div>
 */
final class CacheNode extends StatementNode
{
    public ArrayNode $args;

    public AreaNode $content;

    /** @return \Generator<int, AreaNode|null> */
    public static function create(Tag $tag): \Generator
    {
        $node = $tag->node = new self;
        $node->args = $tag->parser->parseArguments();
        [$node->content] = yield;

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format(
            <<<'XX'
				$ʟ_params = %node;
				$ʟ_main = $node[0] ?? null;
				$ʟ_if = $ʟ_params['if'] ?? (is_bool($ʟ_main) ? $ʟ_main : true);
				$ʟ_enabled = $ʟ_if !== false
					&& config('statamic.system.cache_tags_enabled', true)
					&& request()->method() === 'GET';

				if ($ʟ_enabled) {
					$ʟ_key = $ʟ_params['key'] ?? (is_string($ʟ_main) ? $ʟ_main : null);
					$ʟ_tags = $ʟ_params['tags'] ?? (is_array($ʟ_main) ? $ʟ_main : null);
					$ʟ_scope = $ʟ_params['scope'] ?? ['site', 'auth'];
					$ʟ_for = $ʟ_params['for'] ?? (is_integer($ʟ_main) ? $ʟ_main : null);
					$ʟ_expires = $ʟ_for ? now()->add("+{$ʟ_for}") : null;

					$ʟ_store = \Illuminate\Support\Facades\Cache::store();
					if (is_array($ʟ_tags) && count($ʟ_tags)) {
						$ʟ_store = $ʟ_store->tags($ʟ_tags);
					}

					$auth = auth(config('statamic.users.guards.cp', 'web'));

					$ʟ_hash = [
						'content' => $ʟ_key ?: %dump,
						'params' => $ʟ_params,
						'auth' => $auth->check(),
						'site' => \Statamic\Facades\Site::current()->handle(),
						'scope' => collect($ʟ_scope)->flip()->map(function ($_, $scope) {
							return match($scope) {
								'page' => \Statamic\Facades\URL::makeAbsolute(\Statamic\Facades\URL::getCurrent()),
								'user' => ($user = $auth->user()) ? $user->id : 'guest',
								default => null,
							};
						})->all()
					];
					$ʟ_key = 'latte.statamic.cache.'.md5(json_encode($ʟ_hash));

					if ($ʟ_output = $ʟ_store->get($ʟ_key)) %line {
						echo $ʟ_output;
					} else {
						ob_start(fn() => '');
						%node
						$ʟ_output = ob_get_clean();
						$ʟ_store->put($ʟ_key, $ʟ_output, $ʟ_expires);
						echo $ʟ_output;
					}
				} else {
					%node
				}
				XX,
            $this->args,
            md5($this->content->print($context)),
            $this->position,
            $this->content,
            $this->content,
        );
    }

    public function &getIterator(): \Generator
    {
        yield $this->content;
    }
}
