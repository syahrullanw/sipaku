<?php

namespace App\Models;

use Core\Model;
use PDO;

class TeacherHonor extends Model
{
    protected static ?string $table = 'honor_guru';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function pendingApproval(): array
    {
        $sql = <<<SQL
SELECT *
FROM honor_guru
WHERE status IN ('menunggu_verifikasi','menunggu_acc')
ORDER BY periode DESC, guru_id ASC
SQL;

        $statement = static::connection()->query($sql);

        return $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
