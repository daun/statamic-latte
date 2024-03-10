<?php

namespace Daun\StatamicLatte\Extensions;

use Latte\Extension;
use Latte\Runtime\Template;

/**
 * Latte extension for auto-loading the current layout file from Statamic entries.
 */
class LayoutExtension extends Extension
{
    protected string $defaultLayout = 'layout';

    public function getProviders(): array
    {
        return [
            'coreParentFinder' => function (Template $template) {
                // ignore includes/embeds
                if (! $template->getReferenceType()) {
                    return $this->resolveLayout($template);
                }
            },
        ];
    }

    /**
     * Determine the currently required layout file.
     */
    protected function resolveLayout(Template $template): ?string
    {
        $globalDefaultLayout = $template->global->defaultLayout ?? null;
        $params = $template->getParameters();

        // Abort if this was included as a temporary view from the {nocache} tag
        if ($params['__layout_parent'] ?? null) {
            return null;
        }

        // Find Latte layout parent from Statamic's layout param
        $layout = $params['current_layout'] ?? null;
        if ((! $layout || $layout === $this->defaultLayout) && $globalDefaultLayout) {
            $layout = $globalDefaultLayout($template);
        }

        return $layout;
    }
}
