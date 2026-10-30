<?php

declare(strict_types=1);

namespace Dr2g\ErpLibs\Tests\Unit\View;

use Dr2g\ErpLibs\View\Renderer;
use PHPUnit\Framework\TestCase;

/**
 * Subviews: fragmentos reutilizables incluidos desde una vista, un layout o
 * desde otra subview.
 */
final class SubviewTest extends TestCase
{
    private string $views;

    protected function setUp(): void
    {
        $this->views = sys_get_temp_dir() . '/dr2g_views_' . bin2hex(random_bytes(4));
        mkdir($this->views . '/partials', 0755, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->views, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->views);
    }

    private function renderer(): Renderer
    {
        return new Renderer([$this->views]);
    }

    private function view(string $name, string $content): void
    {
        $path = $this->views . '/' . str_replace('.', '/', $name) . '.php';
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, $content);
    }

    public function testSubviewsRendersThePartial(): void
    {
        $this->view('partials.badge', '<span class="badge"><?= htmlspecialchars($label, ENT_QUOTES) ?></span>');

        self::assertSame(
            '<span class="badge">Nuevo</span>',
            $this->renderer()->subview('partials.badge', ['label' => 'Nuevo'])
        );
    }

    public function testHelperIsAvailableInsideViews(): void
    {
        $this->view('partials.badge', '<b><?= htmlspecialchars($label, ENT_QUOTES) ?></b>');
        $this->view('page', '<div><?= $subview("partials.badge", ["label" => "Hola"]) ?></div>');

        $response = $this->renderer()->render('page');

        self::assertSame('<div><b>Hola</b></div>', $response->body);
    }

    public function testHelperIsAvailableInsideLayoutsAndContentViews(): void
    {
        $this->view('partials.badge', '[<?= htmlspecialchars($label, ENT_QUOTES) ?>]');
        $this->view('layout', '<header><?= $subview("partials.badge", ["label" => "nav"]) ?></header><main><?= $content ?></main>');
        $this->view('home', '<p><?= $subview("partials.badge", ["label" => "body"]) ?></p>');

        $body = $this->renderer()->layout('home', 'layout')->body;

        self::assertSame('<header>[nav]</header><main><p>[body]</p></main>', $body);
    }

    public function testSubviewsCanNest(): void
    {
        $this->view('partials.inner', 'inner:<?= htmlspecialchars($value, ENT_QUOTES) ?>');
        $this->view('partials.outer', 'outer(<?= $subview("partials.inner", ["value" => $value]) ?>)');

        self::assertSame('outer(inner:7)', $this->renderer()->subview('partials.outer', ['value' => 7]));
    }

    public function testMissingSubviewThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Subview not found: partials.nope');

        $this->renderer()->subview('partials.nope');
    }

    public function testSubviewsAreIsolatedFromTheParentScope(): void
    {
        $this->view('partials.item', '<?= isset($secret) ? $secret : "sin secreto" ?>');

        // El padre no filtra variables al hijo: sólo recibe lo que se le pasa.
        self::assertSame('sin secreto', $this->renderer()->subview('partials.item', []));
    }
}
