<?php

declare(strict_types=1);

namespace EcoLife\Gallery\Model;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Gallery\Model\ResourceModel\Item as ItemResource;

final class Item extends AbstractModel
{
    public const CATEGORIES = [
        'residential' => 'Home',
        'society'     => 'Housing society',
        'commercial'  => 'Commercial',
        'other'       => 'Other',
    ];

    protected string $idFieldName = 'item_id';

    public function getResource(): AbstractResource
    {
        return $this->resource ??= new ItemResource();
    }

    public function getTitle(): string
    {
        return (string) $this->getData('title');
    }

    public function getImagePath(): string
    {
        return (string) $this->getData('image_path');
    }

    public function getLocation(): string
    {
        return (string) $this->getData('location', '');
    }

    public function getCategoryLabel(): string
    {
        return self::CATEGORIES[(string) $this->getData('category')] ?? '';
    }

    public function isActive(): bool
    {
        return (int) $this->getData('is_active') === 1;
    }

    public function getSizeLabel(): string
    {
        $kw = $this->getData('system_size_kw');
        return $kw === null ? '' : rtrim(rtrim(number_format((float) $kw, 2, '.', ''), '0'), '.') . ' kW';
    }

    /**
     * The stored path is always relative to pub/media/, never absolute and
     * never a URL, so the media root can move without a data migration.
     */
    public function getAbsolutePath(): string
    {
        return BP . '/pub/media/' . ltrim($this->getImagePath(), '/');
    }

    public function imageExists(): bool
    {
        return $this->getImagePath() !== '' && is_file($this->getAbsolutePath());
    }
}
