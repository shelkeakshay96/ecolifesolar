<?php

declare(strict_types=1);

namespace EcoLife\Core\App;

use EcoLife\Core\Controller\Result\Raw;
use EcoLife\Core\Controller\ResultInterface;
use EcoLife\Core\Exception\NotFoundException;
use EcoLife\Core\Model\Config;
use EcoLife\Core\View\Result\Page;
use Throwable;

/**
 * Orchestrates exactly one HTTP request, and is the only place that catches
 * Throwable during dispatch.
 *
 * Keeping the catch here rather than in controllers means every failure path
 * produces the same thing: a log entry with a request id, and a page that tells
 * the visitor nothing except that id.
 */
final class Http
{
    public function run(): void
    {
        $request  = Request::fromGlobals();
        $response = new Response();
        $area     = Area::detect($request);
        $context  = new Context($request, $response, $area);

        try {
            $context->getSession()->start();

            $action = (new FrontController())->match($context);
            $result = $action->dispatch();
        } catch (NotFoundException $e) {
            Logger::info('404: ' . $e->getMessage());
            $result = $this->notFound($context);
        } catch (Throwable $e) {
            Logger::exception($e);
            $result = $this->serverError($context, $e);
        }

        $result->renderResult($response);
        $response->send();
    }

    /**
     * Rendered through the normal page pipeline, so /admin/nonsense gets admin
     * chrome and /nonsense gets the public header and footer, with no special
     * casing anywhere.
     */
    private function notFound(Context $context): ResultInterface
    {
        try {
            return (new Page($context))
                ->setHttpResponseCode(404)
                ->setTitle('Page not found | EcoLifeSolar')
                ->setBodyClass('page-noroute')
                ->setContentTemplate('EcoLife_Theme::html/noroute.phtml');
        } catch (Throwable $e) {
            // The theme itself is broken. Say so plainly rather than recursing.
            Logger::exception($e);
            return (new Raw())->setHttpResponseCode(404)->setContents("404 Not Found\n");
        }
    }

    private function serverError(Context $context, Throwable $e): ResultInterface
    {
        $body = Config::isDeveloperMode()
            ? sprintf(
                "500 Internal Server Error\n\n%s: %s\nat %s:%d\n\n%s\n\nRequest: %s\n",
                $e::class,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString(),
                Logger::requestId()
            )
            : "Something went wrong.\n\nReference: " . Logger::requestId() . "\n";

        return (new Raw())->setHttpResponseCode(500)->setContents($body);
    }
}
