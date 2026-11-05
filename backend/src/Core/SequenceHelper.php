<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

class SequenceHelper
{
    public static function getNextNumber(int $companyId, string $documentType, ?string $defaultPrefix = null): string
    {
        $pdo = Database::getConnection();
        $year = (int)date('Y');
        $prefix = $defaultPrefix ?? $documentType;

        // Check if row exists with FOR UPDATE lock
        $stmt = $pdo->prepare("SELECT `id`, `last_number`, `prefix` 
                               FROM `document_sequences` 
                               WHERE `company_id` = :cid AND `document_type` = :type AND `year` = :yr 
                               FOR UPDATE");
        $stmt->execute([':cid' => $companyId, ':type' => $documentType, ':yr' => $year]);
        $row = $stmt->fetch();

        if ($row) {
            $nextNum = (int)$row['last_number'] + 1;
            $prefix = $row['prefix'] ?: $prefix;
            $upd = $pdo->prepare("UPDATE `document_sequences` SET `last_number` = :num, `updated_at` = NOW() WHERE `id` = :id");
            $upd->execute([':num' => $nextNum, ':id' => $row['id']]);
        } else {
            $nextNum = 1;
            $ins = $pdo->prepare("INSERT INTO `document_sequences` (`company_id`, `document_type`, `year`, `last_number`, `prefix`) 
                                  VALUES (:cid, :type, :yr, :num, :prefix)");
            $ins->execute([
                ':cid' => $companyId,
                ':type' => $documentType,
                ':yr' => $year,
                ':num' => $nextNum,
                ':prefix' => $prefix,
            ]);
        }

        return sprintf('%s-%d-%05d', $prefix, $year, $nextNum);
    }
}
