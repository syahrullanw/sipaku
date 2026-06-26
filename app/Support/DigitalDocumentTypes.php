<?php

namespace App\Support;

final class DigitalDocumentTypes
{
    /**
     * List of document types that are handled through the letter approval flow.
     *
     * @return array<int, string>
     */
    public static function letterTypes(): array
    {
        return [
            'assignment_letter',
            'outgoing_letter',
        ];
    }

    public static function isLetter(string $documentType): bool
    {
        return in_array($documentType, self::letterTypes(), true);
    }
}
