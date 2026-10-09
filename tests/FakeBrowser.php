<?php

declare(strict_types=1);

namespace Azepo\RgaaCheckBehat\Tests;

use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Response;

/**
 * Navigateur de test : renvoie toujours la même page, sans réseau.
 *
 * @extends AbstractBrowser<object, Response>
 */
final class FakeBrowser extends AbstractBrowser
{
    public function __construct(private readonly string $html)
    {
        parent::__construct();
    }

    protected function doRequest(object $request): Response
    {
        return new Response($this->html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
