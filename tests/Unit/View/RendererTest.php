<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\View;

use Dr2g\ErpLibs\Routing\Response;
use Dr2g\ErpLibs\View\Renderer;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    private string $views;

    protected function setUp(): void
    {
        $this->views = sys_get_temp_dir() . '/dr2g_views_' . bin2hex(random_bytes(4));
        mkdir($this->views . '/home', 0755, true);
        mkdir($this->views . '/errors', 0755, true);

        file_put_contents($this->views . '/home/index.php', '<h1><?= htmlspecialchars($title) ?></h1>');
        file_put_contents($this->views . '/layout.php', '<html><body><?= $content ?></body></html>');
        file_put_contents($this->views . '/errors/404.php', '<p>404: <?= htmlspecialchars($message) ?></p>');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->views . '/*/*.php') ?: []);
        array_map('unlink', glob($this->views . '/*.php') ?: []);
        @rmdir($this->views . '/home');
        @rmdir($this->views . '/errors');
        @rmdir($this->views);
    }

    public function testRenderReturnsHtmlResponse(): void
    {
        $response = (new Renderer([$this->views]))->render('home.index', ['title' => 'Hola']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('<h1>Hola</h1>', $response->body);
        self::assertSame('text/html; charset=utf-8', $response->headers['Content-Type']);
    }

    public function testViewDataIsNotLeakingToTheRendererScope(): void
    {
        $response = (new Renderer([$this->views]))->render('home.index', ['title' => 'A & B']);

        self::assertSame('<h1>A &amp; B</h1>', $response->body);
    }

    public function testLayoutWrapsTheView(): void
    {
        $response = (new Renderer([$this->views]))->layout('home.index', 'layout', ['title' => 'Hola']);

        self::assertSame('<html><body><h1>Hola</h1></body></html>', $response->body);
    }

    public function testMissingViewFailsLoudly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('View not found');

        (new Renderer([$this->views]))->render('nope.nope');
    }

    public function testLocateChecksEveryPath(): void
    {
        $renderer = new Renderer(['/tmp/no-existe', $this->views]);

        self::assertNotNull($renderer->locate('home.index'));
        self::assertNull($renderer->locate('home.missing'));
    }

    public function testErrorViewStatusIsPreserved(): void
    {
        $response = (new Renderer([$this->views]))->render('errors.404', ['message' => 'Nada'], 404);

        self::assertSame(404, $response->status);
        self::assertSame('<p>404: Nada</p>', $response->body);
    }
}
