<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

abstract class BaseModel
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $hasSoftDelete = false;

    public function find(int|string $id): ?array
    {
        $pdo = Database::getConnection();
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        if ($this->hasSoftDelete) {
            $sql .= " AND `deleted_at` IS NULL";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function paginate(
        int $companyId,
        int $page = 1,
        int $perPage = 15,
        string $search = '',
        array $searchableColumns = [],
        array $filters = [],
        string $sortBy = 'id',
        string $sortDir = 'DESC'
    ): array {
        $pdo = Database::getConnection();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = ["`company_id` = :cid"];
        $params = [':cid' => $companyId];

        if ($this->hasSoftDelete) {
            $where[] = "`deleted_at` IS NULL";
        }

        // Apply filters
        foreach ($filters as $col => $val) {
            if ($val !== null && $val !== '') {
                $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
                $paramKey = ":flt_" . $safeCol;
                $where[] = "`$safeCol` = $paramKey";
                $params[$paramKey] = $val;
            }
        }

        // Apply search
        if (!empty($search) && !empty($searchableColumns)) {
            $searchClauses = [];
            foreach ($searchableColumns as $i => $col) {
                $safeCol = preg_replace('/[^a-zA-Z0-9_.]/', '', $col);
                $paramKey = ":srch_" . $i;
                $searchClauses[] = "$safeCol LIKE $paramKey";
                $params[$paramKey] = "%$search%";
            }
            $where[] = '(' . implode(' OR ', $searchClauses) . ')';
        }

        $whereSql = implode(' AND ', $where);

        // Count total
        $countSql = "SELECT COUNT(*) FROM `{$this->table}` WHERE $whereSql";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        // Safe sort
        $safeSortBy = preg_replace('/[^a-zA-Z0-9_.]/', '', $sortBy);
        $safeSortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $dataSql = "SELECT * FROM `{$this->table}` WHERE $whereSql ORDER BY $safeSortBy $safeSortDir LIMIT $perPage OFFSET $offset";
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);
        $items = $dataStmt->fetchAll();

        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int)ceil($total / $perPage),
            ]
        ];
    }

    public function create(array $data): int|string
    {
        $pdo = Database::getConnection();
        $columns = array_keys($data);
        $escapedColumns = array_map(fn($col) => "`" . preg_replace('/[^a-zA-Z0-9_]/', '', $col) . "`", $columns);
        $placeholders = array_map(fn($col) => ":" . preg_replace('/[^a-zA-Z0-9_]/', '', $col), $columns);

        $sql = "INSERT INTO `{$this->table}` (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);

        $params = [];
        foreach ($data as $col => $val) {
            $params[':' . preg_replace('/[^a-zA-Z0-9_]/', '', $col)] = $val;
        }

        $stmt->execute($params);
        return $pdo->lastInsertId();
    }

    public function update(int|string $id, array $data): bool
    {
        $pdo = Database::getConnection();
        $sets = [];
        $params = [':pk' => $id];

        foreach ($data as $col => $val) {
            $safeCol = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
            $sets[] = "`$safeCol` = :val_$safeCol";
            $params[":val_$safeCol"] = $val;
        }

        if (empty($sets)) {
            return false;
        }

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $sets) . " WHERE `{$this->primaryKey}` = :pk";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int|string $id): bool
    {
        $pdo = Database::getConnection();
        if ($this->hasSoftDelete) {
            $sql = "UPDATE `{$this->table}` SET `deleted_at` = NOW() WHERE `{$this->primaryKey}` = :id";
        } else {
            $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        }
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
