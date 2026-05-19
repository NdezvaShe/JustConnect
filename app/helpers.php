<?php

if (!function_exists('catBadge')) {
    /**
     * Return a CSS badge class name based on document type string.
     */
    function catBadge(?string $type): string
    {
        if (!$type) return 'other';
        $t = strtolower($type);
        if (str_contains($t, 'contract') || str_contains($t, 'agreement') || str_contains($t, 'lease')) return 'contract';
        if (str_contains($t, 'judgment') || str_contains($t, 'court')     || str_contains($t, 'ruling')) return 'judgment';
        if (str_contains($t, 'act')      || str_contains($t, 'regulation')|| str_contains($t, 'statute')) return 'act';
        return 'other';
    }
}
