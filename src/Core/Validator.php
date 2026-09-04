<?php

namespace CureRx\Core;

class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            $this->errors[$field][] = "{$field} is required";
        }
        return $this;
    }

    public function integer(string $field, ?int $min = null, ?int $max = null): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return $this;
        }
        if (filter_var($this->data[$field], FILTER_VALIDATE_INT) === false) {
            $this->errors[$field][] = "{$field} must be an integer";
            return $this;
        }
        $val = (int)$this->data[$field];
        if ($min !== null && $val < $min) {
            $this->errors[$field][] = "{$field} must be at least {$min}";
        }
        if ($max !== null && $val > $max) {
            $this->errors[$field][] = "{$field} must be at most {$max}";
        }
        return $this;
    }

    public function decimal(string $field, ?float $min = null): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return $this;
        }
        if (!is_numeric($this->data[$field])) {
            $this->errors[$field][] = "{$field} must be a number";
            return $this;
        }
        if ($min !== null && (float)$this->data[$field] < $min) {
            $this->errors[$field][] = "{$field} must be at least {$min}";
        }
        return $this;
    }

    public function date(string $field): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return $this;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $this->data[$field]);
        if (!$d || $d->format('Y-m-d') !== $this->data[$field]) {
            $this->errors[$field][] = "{$field} must be a valid date (YYYY-MM-DD)";
        }
        return $this;
    }

    public function enum(string $field, array $allowed): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return $this;
        }
        if (!in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field][] = "{$field} must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
