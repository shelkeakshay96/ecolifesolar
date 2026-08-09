<?php

declare(strict_types=1);

namespace EcoLife\Core\Model\ResourceModel;

use ArrayIterator;
use Countable;
use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\Db;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * A filtered, sorted, paginated set of models.
 *
 * Conditions AND together and there is no OR nesting. That is a deliberate
 * simplification: the admin grid has never needed it, and supporting it would
 * roughly double the size of the condition builder while making every generated
 * query harder to read in a slow-query log.
 *
 * Loading is lazy. Nothing touches the database until the collection is
 * iterated, counted, or asked for its size.
 *
 * @template T of AbstractModel
 * @implements IteratorAggregate<int, T>
 */
abstract class AbstractCollection implements IteratorAggregate, Countable
{
    public const SORT_ASC  = 'ASC';
    public const SORT_DESC = 'DESC';

    private const OPERATORS = [
        'eq'      => '= ?',
        'neq'     => '!= ?',
        'gt'      => '> ?',
        'gteq'    => '>= ?',
        'lt'      => '< ?',
        'lteq'    => '<= ?',
        'like'    => 'LIKE ?',
        'nlike'   => 'NOT LIKE ?',
        'null'    => 'IS NULL',
        'notnull' => 'IS NOT NULL',
    ];

    /** @var list<array{sql: string, bind: list<mixed>}> */
    private array $conditions = [];

    /** @var list<string> */
    private array $orders = [];

    private ?int $pageSize = null;
    private int $curPage = 1;

    /** @var list<T> */
    private array $items = [];

    private bool $isLoaded = false;
    private ?int $totalSize = null;

    abstract public function getResource(): AbstractResource;

    /** @return T */
    abstract protected function newModel(array $row): AbstractModel;

    // ---------------------------------------------------------------- filters

    /**
     * addFieldToFilter('status', 'new')
     * addFieldToFilter('created_at', ['gteq' => $from, 'lteq' => $to])
     * addFieldToFilter('name', ['like' => "%{$q}%"])
     * addFieldToFilter('city', ['in' => ['Satara', 'Karad']])
     * addFieldToFilter('email', ['notnull' => true])
     */
    public function addFieldToFilter(string $field, mixed $condition): static
    {
        $column = Db::quoteIdentifier($this->getResource()->assertField($field));

        if (!is_array($condition)) {
            $this->conditions[] = ['sql' => "{$column} = ?", 'bind' => [$condition]];
            return $this->reset();
        }

        foreach ($condition as $operator => $value) {
            $this->conditions[] = $this->buildCondition($column, (string) $operator, $value);
        }

        return $this->reset();
    }

    /** @return array{sql: string, bind: list<mixed>} */
    private function buildCondition(string $column, string $operator, mixed $value): array
    {
        if ($operator === 'in' || $operator === 'nin') {
            $values = array_values((array) $value);

            if ($values === []) {
                // IN () is a syntax error, and an empty set should match
                // nothing rather than quietly match everything.
                return ['sql' => $operator === 'in' ? '1 = 0' : '1 = 1', 'bind' => []];
            }

            $placeholders = implode(', ', array_fill(0, count($values), '?'));
            $keyword      = $operator === 'in' ? 'IN' : 'NOT IN';

            return ['sql' => "{$column} {$keyword} ({$placeholders})", 'bind' => $values];
        }

        if (!isset(self::OPERATORS[$operator])) {
            throw new InvalidArgumentException("Unsupported filter operator '{$operator}'");
        }

        $sql = $column . ' ' . self::OPERATORS[$operator];

        return [
            'sql'  => $sql,
            'bind' => str_contains($sql, '?') ? [$value] : [],
        ];
    }

    public function addOrder(string $field, string $direction = self::SORT_DESC): static
    {
        $column    = Db::quoteIdentifier($this->getResource()->assertField($field));
        $direction = strtoupper($direction) === self::SORT_ASC ? self::SORT_ASC : self::SORT_DESC;

        $this->orders[] = "{$column} {$direction}";

        return $this->reset();
    }

    public function setPageSize(?int $size): static
    {
        $this->pageSize = $size !== null ? max(1, $size) : null;
        return $this->reset();
    }

    public function setCurPage(int $page): static
    {
        $this->curPage = max(1, $page);
        return $this->reset();
    }

    public function getCurPage(): int
    {
        return $this->curPage;
    }

    public function getPageSize(): ?int
    {
        return $this->pageSize;
    }

    // ---------------------------------------------------------------- loading

    public function load(): static
    {
        if ($this->isLoaded) {
            return $this;
        }

        [$where, $bind] = $this->whereClause();

        $sql = 'SELECT * FROM ' . Db::quoteIdentifier($this->getResource()->getTable()) . $where;

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->pageSize !== null) {
            // Bound as integers rather than interpolated, even though both
            // values are already constrained to positive ints.
            $sql .= ' LIMIT ? OFFSET ?';
            $bind[] = $this->pageSize;
            $bind[] = ($this->curPage - 1) * $this->pageSize;
        }

        $statement = $this->getResource()->getConnection()->prepare($sql);

        foreach ($bind as $index => $value) {
            $statement->bindValue(
                $index + 1,
                $value,
                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
            );
        }

        $statement->execute();

        $this->items = [];
        foreach ($statement->fetchAll() as $row) {
            $this->items[] = $this->newModel($row)->setOrigData();
        }

        $this->isLoaded = true;

        return $this;
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function whereClause(): array
    {
        if ($this->conditions === []) {
            return ['', []];
        }

        $sql  = [];
        $bind = [];

        foreach ($this->conditions as $condition) {
            $sql[] = $condition['sql'];
            foreach ($condition['bind'] as $value) {
                $bind[] = $value;
            }
        }

        return [' WHERE ' . implode(' AND ', $sql), $bind];
    }

    /** Total matching rows, ignoring LIMIT -- what the pager needs. */
    public function getSize(): int
    {
        if ($this->totalSize !== null) {
            return $this->totalSize;
        }

        [$where, $bind] = $this->whereClause();

        $statement = $this->getResource()->getConnection()->prepare(
            'SELECT COUNT(*) FROM ' . Db::quoteIdentifier($this->getResource()->getTable()) . $where
        );
        $statement->execute($bind);

        return $this->totalSize = (int) $statement->fetchColumn();
    }

    public function getLastPageNumber(): int
    {
        if ($this->pageSize === null) {
            return 1;
        }
        return max(1, (int) ceil($this->getSize() / $this->pageSize));
    }

    /** @return list<T> */
    public function getItems(): array
    {
        return $this->load()->items;
    }

    /** @return T|null */
    public function getFirstItem(): ?AbstractModel
    {
        return $this->getItems()[0] ?? null;
    }

    /** @return list<int|string> */
    public function getAllIds(): array
    {
        return array_map(static fn(AbstractModel $item) => $item->getId(), $this->getItems());
    }

    /** @return array<int|string, mixed> id => column value, for select options */
    public function toOptionArray(string $labelField): array
    {
        $options = [];
        foreach ($this->getItems() as $item) {
            $options[$item->getId()] = $item->getData($labelField);
        }
        return $options;
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getItems());
    }

    public function count(): int
    {
        return count($this->getItems());
    }

    public function isEmpty(): bool
    {
        return $this->getItems() === [];
    }

    /** Any change to the query invalidates whatever was loaded. */
    private function reset(): static
    {
        $this->isLoaded  = false;
        $this->items     = [];
        $this->totalSize = null;
        return $this;
    }
}
