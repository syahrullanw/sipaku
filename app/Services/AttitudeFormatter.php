<?php

namespace App\Services;

class AttitudeFormatter
{
    /**
     * @param string|null $name
     * @param string|null $description
     * @return string|null
     */
    public static function formatEntry(?string $name, ?string $description): ?string
    {
        $trimmedName = trim((string) ($name ?? ''));
        if ($trimmedName === '') {
            return null;
        }

        $trimmedDescription = trim((string) ($description ?? ''));

        if ($trimmedDescription === '') {
            return $trimmedName;
        }

        return sprintf('%s (%s)', $trimmedName, $trimmedDescription);
    }
}
