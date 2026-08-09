<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

use EcoLife\Core\Model\Db;
use EcoLife\Core\View\TemplateResolver;
use PDO;

/**
 * The service bag handed to every controller and every block.
 *
 * This is what stands in for a DI container. It is not a service locator in
 * disguise: the set of services is fixed, declared here, and typed, so the
 * dependencies of any controller are exactly this object -- no runtime string
 * lookups, no configuration, nothing to register.
 *
 * Built once per request in Http::run() and never mutated afterwards.
 */
final class Context
{
    private ?Session $session = null;
    private ?FormKey $formKey = null;
    private ?Messages $messages = null;
    private ?Url $url = null;
    private ?TemplateResolver $templateResolver = null;

    public function __construct(
        private readonly Request $request,
        private readonly Response $response,
        private readonly Area $area,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    public function getArea(): Area
    {
        return $this->area;
    }

    public function getDb(): PDO
    {
        return Db::instance();
    }

    public function getSession(): Session
    {
        return $this->session ??= new Session($this->area);
    }

    public function getFormKey(): FormKey
    {
        return $this->formKey ??= new FormKey($this->getSession());
    }

    public function getMessages(): Messages
    {
        return $this->messages ??= new Messages($this->getSession());
    }

    public function getUrl(): Url
    {
        return $this->url ??= new Url($this->request, $this->area);
    }

    public function getTemplateResolver(): TemplateResolver
    {
        return $this->templateResolver ??= new TemplateResolver($this->area);
    }
}
