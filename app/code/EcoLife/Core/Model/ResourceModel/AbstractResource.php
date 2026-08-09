<?php

declare(strict_types=1);

namespace EcoLife\Core\Model\ResourceModel;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\Db;
use InvalidArgumentException;
use PDO;

/**
 * PDO CRUD for one table.
 *
 * The security-critical part of this class is $fields. Values are always bound
 * as parameters, but column and table names can never be bound -- SQL does not
 * allow it -- so every identifier that reaches a query is checked against this
 * explicit whitelist first.
 *
 * $fields is declared by hand rather than read from information_schema on
 * purpose. A whitelist that discovers its own contents at runtime is not a
 * whitelist; it is a description of whatever the database happens to contain,
 * including a column an attacker managed to add.
 */
abstract class AbstractResource
{
    protected string $table = '';
    protected string $idFieldName = 'entity_id';

    /** @var list<string> every column this resource may read or write */
    protected array $fields = [];

    public function getTable(): string
    {
        return $this->table;
    }

    public function getIdFieldName(): string
    {
        return $this->idFieldName;
    }

    /** @return list<string> */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getConnection(): PDO
    {
        return Db::instance();
    }

    /**
     * Whitelist check. Throws rather than returning false, because every caller
     * would otherwise have to remember to check, and one that forgets is an
     * injection.
     */
    public function assertField(string $field): string
    {
        if (!in_array($field, $this->fields, true) && $field !== $this->idFieldName) {
            throw new InvalidArgumentException(
                sprintf('%s is not a known column of %s', $field, $this->table)
            );
        }
        return $field;
    }

    protected function quotedTable(): string
    {
        // `lead` is a reserved word in MySQL 8. Quoting unconditionally means
        // the reserved-word problem is solved once, here, rather than being
        // remembered at every call site.
        return Db::quoteIdentifier($this->table);
    }

    // ------------------------------------------------------------------- load

    public function load(AbstractModel $object, int|string $value, ?string $field = null): void
    {
        $field = $this->assertField($field ?? $this->idFieldName);

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ? LIMIT 1',
            $this->quotedTable(),
            Db::quoteIdentifier($field)
        );

        $statement = $this->getConnection()->prepare($sql);
        $statement->execute([$value]);
        $row = $statement->fetch();

        $object->unsetData();

        if ($row !== false) {
            $object->addData($row)->setOrigData();
        }
    }

    // ------------------------------------------------------------------- save

    public function save(AbstractModel $object): void
    {
        if ($object->isObjectNew()) {
            $this->insert($object);
            return;
        }
        $this->update($object);
    }

    private function insert(AbstractModel $object): void
    {
        $data = $this->writableData($object->getData());

        if ($data === []) {
            throw new InvalidArgumentException('Nothing to insert into ' . $this->table);
        }

        $columns = array_keys($data);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quotedTable(),
            implode(', ', array_map(static fn($c) => Db::quoteIdentifier($c), $columns)),
            implode(', ', array_fill(0, count($columns), '?'))
        );

        $connection = $this->getConnection();
        $statement  = $connection->prepare($sql);
        $statement->execute(array_values($data));

        $object->setId((int) $connection->lastInsertId());
        $object->setOrigData();
    }

    private function update(AbstractModel $object): void
    {
        // Only what changed. On a 35-column table this is the difference
        // between rewriting a row and touching one field.
        $data = $this->writableData($object->getDirtyData());

        if ($data === []) {
            return;
        }

        $assignments = implode(', ', array_map(
            static fn($column) => Db::quoteIdentifier($column) . ' = ?',
            array_keys($data)
        ));

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $this->quotedTable(),
            $assignments,
            Db::quoteIdentifier($this->idFieldName)
        );

        $statement = $this->getConnection()->prepare($sql);
        $statement->execute([...array_values($data), $object->getId()]);

        $object->setOrigData();
    }

    /**
     * Drop anything that is not a declared column, and never write the primary
     * key or the timestamp columns the database maintains itself.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function writableData(array $data): array
    {
        $writable = [];

        foreach ($data as $column => $value) {
            if (!in_array($column, $this->fields, true)) {
                continue;
            }
            if ($column === $this->idFieldName || $column === 'created_at' || $column === 'updated_at') {
                continue;
            }
            $writable[$column] = is_bool($value) ? (int) $value : $value;
        }

        return $writable;
    }

    // ----------------------------------------------------------------- delete

    public function delete(AbstractModel $object): void
    {
        $id = $object->getId();
        if ($id === null) {
            return;
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s = ?',
            $this->quotedTable(),
            Db::quoteIdentifier($this->idFieldName)
        );

        $this->getConnection()->prepare($sql)->execute([$id]);
        $object->unsetData();
    }
}
