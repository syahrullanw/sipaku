<?php

namespace App\Models;

use Core\Model;
use PDO;

class TeacherLoanInstallment extends Model
{
    protected static ?string $table = 'kasbon_cicilan';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function upcomingForLoan(int $loanId): array
    {
        if ($loanId <= 0) {
            return [];
        }

        $statement = static::connection()->prepare(
            "SELECT * FROM kasbon_cicilan WHERE kasbon_id = :loan AND status IN ('menunggu','tertunggak') ORDER BY jatuh_tempo ASC"
        );

        if ($statement === false) {
            return [];
        }

        $statement->bindValue(':loan', $loanId, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows === false ? [] : $rows;
    }
}
