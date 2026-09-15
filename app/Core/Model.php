<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Model
{
    protected PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    protected function one(string $sql, array $parameters = []): ?array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    protected function all(string $sql, array $parameters = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    protected function execute(string $sql, array $parameters = []): int
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->rowCount();
    }
}
