<?php

namespace App\Services\Finance;

use App\Models\TeacherSalaryComponent;
use App\Models\TeacherSalaryRecord;
use Core\Database;
use RuntimeException;

class TeacherSalaryService
{
    /**
     * @param array<string, mixed> $recordData
     * @param array<int, array<string, mixed>> $components
     */
    public static function saveRecord(array $recordData, array $components, ?int $recordId = null): int
    {
        $connection = Database::connection();

        try {
            $connection->beginTransaction();

            $now = date('Y-m-d H:i:s');
            $recordPayload = array_merge($recordData, [
                'updated_at' => $now,
            ]);

            if ($recordId === null) {
                $recordPayload['created_at'] = $now;
                $recordId = TeacherSalaryRecord::createAndReturnId($recordPayload);

                if ($recordId === null) {
                    throw new RuntimeException('Gagal menyimpan penggajian guru.');
                }
            } else {
                if (!TeacherSalaryRecord::updateById($recordId, $recordPayload)) {
                    throw new RuntimeException('Gagal memperbarui penggajian guru.');
                }
            }

            $existingComponents = TeacherSalaryComponent::byRecord($recordId);
            $existingIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $existingComponents);
            $keepIds = [];

            foreach ($components as $component) {
                $componentId = isset($component['id']) ? (int) $component['id'] : 0;
                $payload = [
                    'teacher_salary_record_id' => $recordId,
                    'type' => (string) ($component['type'] ?? 'adjustment'),
                    'code' => (string) ($component['code'] ?? ''),
                    'label' => (string) ($component['label'] ?? ''),
                    'quantity' => isset($component['quantity']) ? (float) $component['quantity'] : null,
                    'rate' => isset($component['rate']) ? (float) $component['rate'] : null,
                    'amount' => (float) ($component['amount'] ?? 0.0),
                    'metadata' => $component['metadata'] ?? null,
                    'updated_at' => $now,
                ];

                if ($componentId > 0) {
                    if (!TeacherSalaryComponent::updateById($componentId, $payload)) {
                        throw new RuntimeException('Gagal memperbarui komponen gaji guru.');
                    }
                    $keepIds[] = $componentId;
                } else {
                    $payload['created_at'] = $now;
                    if (!TeacherSalaryComponent::create($payload)) {
                        throw new RuntimeException('Gagal menyimpan komponen gaji guru.');
                    }
                }
            }

            foreach ($existingIds as $existingId) {
                if (!in_array($existingId, $keepIds, true)) {
                    TeacherSalaryComponent::deleteById($existingId);
                }
            }

            $connection->commit();

            return $recordId;
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}

