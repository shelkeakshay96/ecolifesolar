<?php

declare(strict_types=1);

namespace EcoLife\Seo\Controller\Sitemap;

use EcoLife\Core\Controller\AbstractAction;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\View\TemplateResolver;
use EcoLife\Seo\Model\UrlList;
use XMLWriter;

/**
 * /sitemap.xml
 *
 * Generated per request rather than written to disk. The site has a dozen URLs
 * and this costs a filesystem stat each; a cron job to rebuild a static file
 * would be more machinery than the thing it maintains.
 *
 * Built with XMLWriter rather than string concatenation, because a sitemap with
 * one unescaped ampersand in it is rejected whole, and the failure surfaces
 * days later in Search Console rather than here.
 */
final class Index extends AbstractAction
{
    protected function execute(): ResultInterface
    {
        $base = rtrim($this->context->getUrl()->getBaseUrl(), '/');
        $urls = (new UrlList(new TemplateResolver($this->context->getArea())))->all($base);

        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('    ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($urls as $url) {
            $xml->startElement('url');
            $xml->writeElement('loc', $url['loc']);

            if ($url['lastmod'] !== null) {
                $xml->writeElement('lastmod', $url['lastmod']);
            }

            $xml->writeElement('priority', $url['priority']);
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $this->resultRaw()
            ->setContentType('application/xml; charset=UTF-8')
            ->setContents($xml->outputMemory());
    }
}
