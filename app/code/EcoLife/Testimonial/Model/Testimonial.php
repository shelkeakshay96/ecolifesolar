<?php

declare(strict_types=1);

namespace EcoLife\Testimonial\Model;

use EcoLife\Core\Model\AbstractModel;
use EcoLife\Core\Model\ResourceModel\AbstractResource;
use EcoLife\Testimonial\Model\ResourceModel\Testimonial as TestimonialResource;

final class Testimonial extends AbstractModel
{
    /**
     * One source for the ENUM whitelist and the admin select options, so the
     * two cannot drift -- same reason Gallery\Model\Item::CATEGORIES exists.
     */
    public const LANGUAGES = [
        'en' => 'English',
        'mr' => 'Marathi',
    ];

    protected string $idFieldName = 'testimonial_id';

    public function getResource(): AbstractResource
    {
        return $this->resource ??= new TestimonialResource();
    }

    public function getCustomerName(): string
    {
        return (string) $this->getData('customer_name');
    }

    public function getLocation(): string
    {
        return (string) $this->getData('location', '');
    }

    public function getBusiness(): string
    {
        return (string) $this->getData('business', '');
    }

    public function getPhotoPath(): string
    {
        return (string) $this->getData('photo_path', '');
    }

    /**
     * A stored path is not a guarantee of a file. Media lives outside the
     * repository, so a database restored onto a fresh server has rows whose
     * images never came with it -- and a broken avatar beside a real quote
     * reads as a broken site. The monogram is always available instead.
     */
    public function photoExists(): bool
    {
        return $this->getPhotoPath() !== ''
            && is_file(BP . '/pub/media/' . ltrim($this->getPhotoPath(), '/'));
    }

    public function getQuote(): string
    {
        return (string) $this->getData('quote');
    }

    public function getLanguage(): string
    {
        $language = (string) $this->getData('language');
        return array_key_exists($language, self::LANGUAGES) ? $language : 'en';
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
     * "Karad &middot; 5 kW" for the card byline. Either part may be absent, so this
     * joins whatever is there rather than assuming both.
     */
    public function getMetaLabel(): string
    {
        return implode(" \u{00B7} ", array_filter([$this->getLocation(), $this->getSizeLabel()]));
    }

    /**
     * The first letter of the customer's name, for the avatar circle. We have
     * no customer photographs and asking for them would be intrusive, so a
     * monogram is the honest placeholder.
     */
    public function getInitial(): string
    {
        $name = trim($this->getCustomerName());
        return $name === '' ? '?' : mb_strtoupper(mb_substr($name, 0, 1));
    }
}
