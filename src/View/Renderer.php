<?php

namespace Dr2g\ErpLibs\View;

use Dr2g\ErpLibs\Routing\Response;

/**
 * Renderizador de vistas PHP planas. No reemplaza a un motor de plantillas:
 * la vista es un archivo .php con acceso a la variable `$data`.
 */
class Renderer
{
    /** @var list<string> */
    private array $paths;

    /**
     * @param list<string> $paths rutas donde buscar `nombre.php`, en orden
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
    }

    public function path(): ?string
    {
        return $this->paths[0] ?? null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function render(string $view, array $data = [], int $status = 200, array $headers = []): Response
    {
        $file = $this->locate($view);

        if ($file === null) {
            throw new \RuntimeException("View not found: {$view}. Searched in " . implode(', ', $this->paths));
        }

        $content = self::evaluate($file, $data + [
            'render' => $this,
            'subview' => self::subviewCallback($this),
        ]);

        return new Response($content, $status, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    public function locate(string $view): ?string
    {
        $file = str_replace('.', '/', $view) . '.php';

        foreach ($this->paths as $path) {
            $candidate = rtrim($path, '/\\') . '/' . $file;

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Renderiza una vista de contenido dentro de un layout. La vista envuelve su
     * HTML con ob_start()/`$content = ob_get_clean()` y el layout usa `$content`.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function layout(string $view, string $layout, array $data = [], int $status = 200, array $headers = []): Response
    {
        $viewFile = $this->locate($view);
        $layoutFile = $this->locate($layout);

        if ($viewFile === null) {
            throw new \RuntimeException("View not found: {$view}. Searched in " . implode(', ', $this->paths));
        }

        if ($layoutFile === null) {
            throw new \RuntimeException("Layout not found: {$layout}. Searched in " . implode(', ', $this->paths));
        }

        $output = self::evaluate($layoutFile, $data + [
            'view' => $viewFile,
            'render' => $this,
            'subview' => self::subviewCallback($this),
            'content' => self::evaluate($viewFile, $data + ['render' => $this, 'subview' => self::subviewCallback($this)]),
        ]);

        return new Response($output, $status, ['Content-Type' => 'text/html; charset=utf-8'] + $headers);
    }

    /**
     * Devuelve el HTML de una subview ya resuelto.
     *
     * @param array<string, mixed> $data
     */
    public function subview(string $view, array $data = []): string
    {
        $file = $this->locate($view);

        if ($file === null) {
            throw new \RuntimeException("Subview not found: {$view}. Searched in " . implode(', ', $this->paths));
        }

        return self::evaluate($file, $data + [
            'render' => $this,
            'subview' => self::subviewCallback($this),
        ]);
    }

    /**
     * Callable `subview('partials.nav', [...])` inyectado en las vistas.
     */
    private static function subviewCallback(self $renderer): \Closure
    {
        return static function (string $view, array $data = []) use ($renderer): string {
            return $renderer->subview($view, $data);
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function evaluate(string $file, array $data): string
    {
        // Ejecuta la vista aislada: solo ve $data y las funciones que ella llame.
        $render = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);

            ob_start();

            try {
                require $__file;
            } catch (\Throwable $error) {
                ob_end_clean();

                throw $error;
            }

            return (string) ob_get_clean();
        };

        return $render($file, $data);
    }
}
