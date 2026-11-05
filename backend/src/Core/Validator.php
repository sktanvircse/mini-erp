<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function validate(array $data, array $rules): array
    {
        $validator = new self($data, $rules);
        if (!$validator->passes()) {
            Response::validationError($validator->getErrors());
        }
        return $validator->validated();
    }

    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $rulesList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
            $value = $this->data[$field] ?? null;

            foreach ($rulesList as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                } else {
                    $ruleName = $rule;
                }

                $this->checkRule($field, $value, $ruleName, $params);
            }
        }

        return empty($this->errors);
    }

    private function checkRule(string $field, mixed $value, string $rule, array $params): void
    {
        if ($rule === 'required') {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                $this->addError($field, "The $field field is required.");
            }
            return;
        }

        // Other rules only run if value is present
        if ($value === null || $value === '') {
            return;
        }

        switch ($rule) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The $field must be a valid email address.");
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, "The $field must be a number.");
                }
                break;

            case 'integer':
                if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== 0 && $value !== '0') {
                    $this->addError($field, "The $field must be an integer.");
                }
                break;

            case 'min':
                $min = (float)($params[0] ?? 0);
                if (is_numeric($value)) {
                    if ((float)$value < $min) {
                        $this->addError($field, "The $field must be at least $min.");
                    }
                } elseif (is_string($value)) {
                    if (mb_strlen($value) < $min) {
                        $this->addError($field, "The $field must be at least $min characters.");
                    }
                }
                break;

            case 'max':
                $max = (float)($params[0] ?? 0);
                if (is_numeric($value)) {
                    if ((float)$value > $max) {
                        $this->addError($field, "The $field may not be greater than $max.");
                    }
                } elseif (is_string($value)) {
                    if (mb_strlen($value) > $max) {
                        $this->addError($field, "The $field may not be greater than $max characters.");
                    }
                }
                break;

            case 'in':
                if (!in_array((string)$value, $params, true)) {
                    $this->addError($field, "The selected $field is invalid. Allowed: " . implode(', ', $params));
                }
                break;

            case 'unique':
                $table = $params[0] ?? '';
                $column = $params[1] ?? $field;
                $ignoreId = $params[2] ?? null;
                $idColumn = $params[3] ?? 'id';

                if ($table) {
                    $pdo = Database::getConnection();
                    $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = :val";
                    $binds = [':val' => $value];

                    if ($ignoreId !== null && $ignoreId !== '') {
                        $sql .= " AND `$idColumn` != :ignoreId";
                        $binds[':ignoreId'] = $ignoreId;
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($binds);
                    if ((int)$stmt->fetchColumn() > 0) {
                        $this->addError($field, "The $field has already been taken.");
                    }
                }
                break;

            case 'exists':
                $table = $params[0] ?? '';
                $column = $params[1] ?? 'id';
                if ($table) {
                    $pdo = Database::getConnection();
                    $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = :val";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':val' => $value]);
                    if ((int)$stmt->fetchColumn() === 0) {
                        $this->addError($field, "The selected $field is invalid.");
                    }
                }
                break;
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        $keys = array_keys($this->rules);
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->data)) {
                $result[$key] = $this->data[$key];
            }
        }
        return $result;
    }
}
