<?php

declare(strict_types=1);

namespace EcoLife\Core\Model;

use BadMethodCallException;
use EcoLife\Core\Model\ResourceModel\AbstractResource;

/**
 * Entity base: a data bag with dirty tracking.
 *
 * origData is what makes save() write only the columns that actually changed.
 * On a 35-column table like `lead`, an admin toggling one status field
 * otherwise rewrites every column and stamps updated_at on rows nothing
 * touched.
 *
 * __call provides getFoo()/setFoo() for generic code, but every concrete model
 * also declares explicit typed accessors. The magic is a convenience, not a
 * licence to leave the model untyped -- an IDE cannot autocomplete __call, and
 * neither can a reader.
 */
abstract class AbstractModel
{
    protected string $idFieldName = 'entity_id';

    /** @var array<string, mixed> */
    protected array $data = [];

    /** @var array<string, mixed> as loaded from the database */
    protected array $origData = [];

    protected ?AbstractResource $resource = null;

    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    abstract public function getResource(): AbstractResource;

    public function getIdFieldName(): string
    {
        return $this->idFieldName;
    }

    // ------------------------------------------------------------------- data

    public function getData(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->data;
        }
        return $this->data[$key] ?? $default;
    }

    public function setData(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    /** @param array<string, mixed> $data */
    public function addData(array $data): static
    {
        foreach ($data as $key => $value) {
            $this->data[$key] = $value;
        }
        return $this;
    }

    public function unsetData(?string $key = null): static
    {
        if ($key === null) {
            $this->data = [];
        } else {
            unset($this->data[$key]);
        }
        return $this;
    }

    public function hasData(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /** @return array<string, mixed> */
    public function toArray(array $keys = []): array
    {
        if ($keys === []) {
            return $this->data;
        }
        return array_intersect_key($this->data, array_flip($keys));
    }

    public function getId(): int|string|null
    {
        return $this->data[$this->idFieldName] ?? null;
    }

    public function setId(int|string|null $id): static
    {
        return $this->setData($this->idFieldName, $id);
    }

    // --------------------------------------------------------- dirty tracking

    /** Called by the resource model after load and after save. */
    public function setOrigData(?array $data = null): static
    {
        $this->origData = $data ?? $this->data;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getOrigData(): array
    {
        return $this->origData;
    }

    public function hasDataChangedFor(string $key): bool
    {
        if (!array_key_exists($key, $this->origData)) {
            return array_key_exists($key, $this->data);
        }
        return ($this->data[$key] ?? null) !== $this->origData[$key];
    }

    /**
     * Columns whose value differs from what was loaded.
     *
     * @return array<string, mixed>
     */
    public function getDirtyData(): array
    {
        $dirty = [];
        foreach ($this->data as $key => $value) {
            if ($this->hasDataChangedFor($key)) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function isObjectNew(): bool
    {
        return $this->getId() === null || $this->origData === [];
    }

    // ------------------------------------------------------------ persistence

    public function load(int|string $value, ?string $field = null): static
    {
        $this->getResource()->load($this, $value, $field);
        return $this;
    }

    public function save(): static
    {
        $this->beforeSave();
        $this->getResource()->save($this);
        $this->afterSave();
        return $this;
    }

    public function delete(): static
    {
        $this->getResource()->delete($this);
        return $this;
    }

    protected function beforeSave(): void
    {
    }

    protected function afterSave(): void
    {
    }

    // ------------------------------------------------------------------ magic

    public function __call(string $method, array $arguments): mixed
    {
        $prefix = substr($method, 0, 3);
        $key    = self::snake(substr($method, 3));

        return match ($prefix) {
            'get'   => $this->getData($key, $arguments[0] ?? null),
            'set'   => $this->setData($key, $arguments[0] ?? null),
            'uns'   => $this->unsetData(self::snake(substr($method, 5))),
            default => match (substr($method, 0, 3) === 'has' ? 'has' : '') {
                'has'   => $this->hasData($key),
                default => throw new BadMethodCallException(
                    static::class . '::' . $method . '() does not exist'
                ),
            },
        };
    }

    private static function snake(string $studly): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $studly));
    }
}
