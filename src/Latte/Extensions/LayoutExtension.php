<?php

namespace Daun\StatamicLatte\Latte\Extensions;

use Latte\Extension;
use Latte\Runtime\Template;

/**
 * Latte extension for auto-loading the current layout file from Statamic entries.
 */
class LayoutExtension extends Extension
{
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
        $params = $template->getParameters();

        // Abort if this was included as a temporary view from the {nocache} tag
        if ($params['__layout_parent'] ?? null) {
            return null;
        }

        // Find Latte layout parent from Statamic's layout param
        return $params['current_layout'] ?? null;
    }
}
