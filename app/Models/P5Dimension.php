<?php

namespace App\Models;

use Core\Model;
use PDO;

class P5Dimension extends Model
{
    protected static ?string $table = 'p5_dimensi';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function allWithElements(): array
    {
        $dimensions = static::all();
        $elements = P5Element::all();
        $grouped = [];

        foreach ($elements as $element) {
            $dimId = (int) ($element['dimensi_id'] ?? 0);
            if ($dimId <= 0) {
                continue;
            }
            $grouped[$dimId][] = $element;
        }

        foreach ($dimensions as $index => $dimension) {
            $id = (int) ($dimension['id'] ?? 0);
            $dimensions[$index]['elements'] = $grouped[$id] ?? [];
        }

        return $dimensions;
    }
}
