<?php

namespace App\Models;

use Core\Model;
use PDO;

class WhatsappGatewaySetting extends Model
{
    protected static ?string $table = 'whatsapp_gateway_settings';

    /**
     * @return array<string, mixed>|null
     */
    public static function first(): ?array
    {
        $statement = static::connection()->query(
            'SELECT * FROM whatsapp_gateway_settings ORDER BY id ASC LIMIT 1'
        );

        if ($statement === false) {
            return null;
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result === false ? null : $result;
    }
}

