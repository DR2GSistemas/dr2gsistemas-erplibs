<?php

namespace Dr2g\ErpLibs\CLI\Scaffold;

/**
 * Integración Vue 3 + TypeScript para las aplicaciones web generadas.
 */
final class VueScaffold
{
    private const STATE_FILE = '.dr2g/features.json';

    /**
     * @return list<string> archivos creados o actualizados
     */
    public function enable(ProjectContext $project, string $feature): array
    {
        if (!in_array($feature, ['vue', 'material', 'primevue'], true)) {
            throw new \InvalidArgumentException(
                "Feature desconocida: {$feature}. Disponibles: vue, material, primevue."
            );
        }

        $state = $this->loadState($project);

        if ($feature === 'vue') {
            return $this->enableVue($project, $state);
        }

        return $this->enableUi($project, $state, $feature);
    }

    /**
     * @param array<string, string> $state
     * @return list<string>
     */
    private function enableVue(ProjectContext $project, array $state): array
    {
        if (isset($state['vue'])) {
            return [];
        }

        $layoutPath = $project->path('src/views/layout.php');

        if (!is_file($layoutPath)) {
            throw new \RuntimeException('Vue sólo se puede habilitar en una aplicación web con src/views/layout.php.');
        }

        $files = $this->vueFiles();
        $layout = (string) file_get_contents($layoutPath);
        $files['src/views/layout.php'] = $this->enableVueLayout($layout);
        $files['package.json'] = $this->mergePackageJson($project, 'vue');
        $ignorePath = $project->path('.gitignore');
        $ignoreContents = is_file($ignorePath) ? file_get_contents($ignorePath) : '';

        if ($ignoreContents === false) {
            throw new \RuntimeException("No se pudo leer {$ignorePath}.");
        }

        $files['.gitignore'] = $this->addIgnoreRules(
            $ignoreContents,
            ['node_modules/', 'public/build/']
        );
        $replaceable = ['package.json', 'src/views/layout.php', '.gitignore'];
        $this->preflight($project, $files, $replaceable);

        foreach ($files as $relative => $content) {
            $this->write($project->path($relative), $content, in_array($relative, $replaceable, true));
        }

        $state['vue'] = 'enabled';
        $this->saveState($project, $state);

        return [...array_keys($files), self::STATE_FILE];
    }

    /**
     * @param array<string, string> $state
     * @return list<string>
     */
    private function enableUi(ProjectContext $project, array $state, string $feature): array
    {
        if (!isset($state['vue'])) {
            throw new \RuntimeException('Habilita Vue primero: feature:enable vue.');
        }

        if (($state['ui'] ?? null) === $feature) {
            return [];
        }

        if (isset($state['ui'])) {
            throw new \RuntimeException(
                "La interfaz '{$state['ui']}' ya está habilitada. Material y PrimeVue son excluyentes."
            );
        }

        $files = [];

        if ($feature === 'material') {
            $cssPath = $project->path('resources/css/app.css');

            if (!is_file($cssPath)) {
                throw new \RuntimeException('Falta resources/css/app.css. Revisa la instalación de Vue.');
            }

            $css = (string) file_get_contents($cssPath);
            $files['resources/css/material.css'] = self::materialCss();
            $files['resources/css/app.css'] = "@import './material.css';\n" . $css;
        } else {
            $files['resources/js/plugins/primevue.ts'] = self::primeVuePlugin();
            $files['package.json'] = $this->mergePackageJson($project, 'primevue');
        }

        $replaceable = $feature === 'material'
            ? ['resources/css/app.css']
            : ['package.json'];
        $this->preflight($project, $files, $replaceable);

        foreach ($files as $relative => $content) {
            $this->write($project->path($relative), $content, in_array($relative, $replaceable, true));
        }

        $state['ui'] = $feature;
        $this->saveState($project, $state);

        return [...array_keys($files), self::STATE_FILE];
    }

    /**
     * @param array<string, string> $files
     * @param list<string> $replaceable
     */
    private function preflight(ProjectContext $project, array $files, array $replaceable): void
    {
        foreach ($files as $relative => $content) {
            $path = $project->path($relative);

            if (is_file($path) && !in_array($relative, $replaceable, true)
                && file_get_contents($path) !== $content) {
                throw new \RuntimeException("Ya existe {$path}; no se sobrescribió.");
            }
        }
    }

    /**
     * @param list<string> $rules
     */
    private function addIgnoreRules(string $contents, array $rules): string
    {
        $lines = preg_split('/\R/', $contents) ?: [];

        foreach ($rules as $rule) {
            if (!in_array($rule, $lines, true)) {
                $lines[] = $rule;
            }
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /**
     * @return array<string, string>
     */
    private function vueFiles(): array
    {
        return [
            'vite.config.ts' => self::viteConfig(),
            'tsconfig.json' => self::typescriptConfig(),
            'resources/js/app.ts' => self::entryPoint(),
            'resources/js/App.vue' => self::appComponent(),
            'resources/css/app.css' => self::baseCss(),
            'src/views/partials/vite-head.php' => self::viteHeadPartial(),
            'src/views/partials/vite-scripts.php' => self::viteScriptsPartial(),
        ];
    }

    private function enableVueLayout(string $layout): string
    {
        $rootReady = str_contains($layout, '<main id="app">');

        if (!$rootReady) {
            $updated = str_replace('<main>', '<main id="app">', $layout, $count);

            if ($count !== 1) {
                throw new \RuntimeException('No se pudo localizar <main> en src/views/layout.php.');
            }

            $layout = $updated;
        }

        if (!str_contains($layout, 'partials/vite-head.php')) {
            $updated = str_replace(
                '</head>',
                "<?php require __DIR__ . '/partials/vite-head.php'; ?>\n</head>",
                $layout,
                $count
            );

            if ($count !== 1) {
                throw new \RuntimeException('No se pudo localizar </head> en src/views/layout.php.');
            }

            $layout = $updated;
        }

        if (!str_contains($layout, 'partials/vite-scripts.php')) {
            $updated = str_replace(
                '</body>',
                "<?php require __DIR__ . '/partials/vite-scripts.php'; ?>\n</body>",
                $layout,
                $count
            );

            if ($count !== 1) {
                throw new \RuntimeException('No se pudo localizar </body> en src/views/layout.php.');
            }

            $layout = $updated;
        }

        return $layout;
    }

    private function mergePackageJson(ProjectContext $project, string $feature): string
    {
        $path = $project->path('package.json');
        $manifest = ['name' => self::packageName(basename($project->root))];

        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);

            if (!is_array($decoded)) {
                throw new \RuntimeException('package.json existe pero no contiene JSON válido.');
            }

            $manifest = $decoded;
        }

        $manifest['scripts'] ??= [];
        $manifest['dependencies'] ??= [];
        $manifest['devDependencies'] ??= [];

        if (!is_array($manifest['scripts']) || !is_array($manifest['dependencies'])
            || !is_array($manifest['devDependencies'])) {
            throw new \RuntimeException('package.json debe tener objetos scripts, dependencies y devDependencies.');
        }

        $this->assertDependencyMajor($manifest['dependencies'], 'vue', 3);

        $manifest['scripts'] += [
            'dr2g:dev' => 'vite --host 127.0.0.1',
            'dr2g:typecheck' => 'vue-tsc --noEmit',
            'dr2g:build' => 'vue-tsc --noEmit && vite build',
        ];
        $manifest['dependencies'] += ['vue' => '^3.5.13'];
        $manifest['devDependencies'] += [
            '@vitejs/plugin-vue' => '^5.2.1',
            'typescript' => '^5.7.2',
            'vite' => '^6.0.5',
            'vue-tsc' => '^2.2.0',
        ];

        if ($feature === 'primevue') {
            $this->assertDependencyMajor($manifest['dependencies'], 'primevue', 4);
            $this->assertDependencyMajor($manifest['dependencies'], '@primeuix/themes', 1);
            $manifest['dependencies'] += [
                '@primeuix/themes' => '^1.2.3',
                'primevue' => '^4.2.5',
            ];
        }

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @param array<string, mixed> $dependencies
     */
    private function assertDependencyMajor(array $dependencies, string $package, int $major): void
    {
        if (!isset($dependencies[$package])) {
            return;
        }

        $range = $dependencies[$package];

        if (is_string($range)
            && (in_array($range, ['*', 'latest'], true)
                || preg_match('/(?:^|[\s|])(?:\^|~|>=|>|=)?\s*' . $major . '(?:\.|$)/', $range) === 1)) {
            return;
        }

        throw new \RuntimeException(
            "La dependencia {$package} de package.json debe ser compatible con la versión {$major}."
        );
    }

    private static function packageName(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $name), '-'));

        return $slug === '' ? 'erp-app' : $slug;
    }

    /**
     * @return array<string, string>
     */
    private function loadState(ProjectContext $project): array
    {
        $path = $project->path(self::STATE_FILE);

        if (!is_file($path)) {
            return [];
        }

        $state = json_decode((string) file_get_contents($path), true);

        if (!is_array($state)) {
            throw new \RuntimeException('El estado de features .dr2g/features.json no contiene JSON válido.');
        }

        foreach ($state as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                throw new \RuntimeException('El estado de features .dr2g/features.json tiene un formato inválido.');
            }
        }

        return $state;
    }

    /**
     * @param array<string, string> $state
     */
    private function saveState(ProjectContext $project, array $state): void
    {
        $this->write(
            $project->path(self::STATE_FILE),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
            true
        );
    }

    private function write(string $path, string $content, bool $replace = false): void
    {
        if (is_file($path)) {
            $existing = file_get_contents($path);

            if ($existing === $content) {
                return;
            }

            if (!$replace) {
                throw new \RuntimeException("Ya existe {$path}; no se sobrescribió.");
            }
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("No se pudo crear el directorio {$directory}.");
        }

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException("No se pudo escribir {$path}.");
        }
    }

    private static function viteConfig(): string
    {
        return <<<'TS'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/build/',
  server: {
    host: '127.0.0.1',
    port: 5173,
    strictPort: true,
  },
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: {
      input: 'resources/js/app.ts',
    },
  },
})
TS;
    }

    private static function typescriptConfig(): string
    {
        return <<<'JSON'
{
  "compilerOptions": {
    "target": "ES2022",
    "useDefineForClassFields": true,
    "module": "ESNext",
    "moduleResolution": "Bundler",
    "strict": true,
    "jsx": "preserve",
    "resolveJsonModule": true,
    "isolatedModules": true,
    "esModuleInterop": true,
    "lib": ["ES2022", "DOM", "DOM.Iterable"],
    "types": ["vite/client"],
    "skipLibCheck": true,
    "noEmit": true
  },
  "include": ["resources/**/*.ts", "resources/**/*.vue", "vite.config.ts"]
}
JSON;
    }

    private static function entryPoint(): string
    {
        return <<<'TS'
import { createApp, type Plugin } from 'vue'
import App from './App.vue'
import '../css/app.css'

const app = createApp(App)
const plugins = import.meta.glob<{ default: Plugin }>('./plugins/*.ts', {
  eager: true,
})

for (const plugin of Object.values(plugins)) {
  app.use(plugin.default)
}

app.mount('#app')
TS;
    }

    private static function appComponent(): string
    {
        return <<<'VUE'
<script setup lang="ts">
import { computed, ref } from 'vue'

type Order = {
  id: string
  customer: string
  status: 'En proceso' | 'Pendiente' | 'Completada'
}

const orders = ref<Order[]>([
  { id: 'ORD-1048', customer: 'Distribuidora Norte', status: 'En proceso' },
  { id: 'ORD-1047', customer: 'Comercial San Pedro', status: 'Pendiente' },
  { id: 'ORD-1046', customer: 'Suministros del Sur', status: 'Completada' },
])

const pendingOrders = computed(() => orders.value.filter((order) => order.status === 'Pendiente').length)

function createOrder(): void {
  const nextId = 1048 + orders.value.length - 2
  orders.value.unshift({
    id: `ORD-${nextId}`,
    customer: 'Nuevo cliente',
    status: 'Pendiente',
  })
}
</script>

<template>
  <div class="erp-app">
    <header class="erp-app__heading">
      <div>
        <p class="erp-app__eyebrow">Operaciones</p>
        <h1>Centro de operaciones</h1>
        <p class="erp-app__summary">Pedidos y actividad reciente de tu negocio.</p>
      </div>
      <button class="erp-app__action" type="button" @click="createOrder">
        Nueva orden
      </button>
    </header>

    <section class="erp-app__overview" aria-label="Resumen">
      <p><strong>{{ orders.length }}</strong><span>Órdenes recientes</span></p>
      <p><strong>{{ pendingOrders }}</strong><span>Pendientes de atender</span></p>
    </section>

    <section class="erp-app__orders" aria-labelledby="orders-title">
      <div class="erp-app__section-heading">
        <h2 id="orders-title">Actividad reciente</h2>
        <span>{{ orders.length }} órdenes</span>
      </div>
      <div class="erp-app__table-wrap">
        <table>
          <thead>
            <tr><th scope="col">Orden</th><th scope="col">Cliente</th><th scope="col">Estado</th></tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id">
              <td>{{ order.id }}</td>
              <td>{{ order.customer }}</td>
              <td><span class="erp-app__status" :class="`is-${order.status === 'En proceso' ? 'progress' : order.status === 'Pendiente' ? 'pending' : 'complete'}`">{{ order.status }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
VUE;
    }

    private static function baseCss(): string
    {
        return <<<'CSS'
:root {
  font-family: system-ui, sans-serif;
  color: #17243b;
  background: #f3f5f8;
  font-synthesis: none;
  text-rendering: optimizeLegibility;
}

* { box-sizing: border-box; }
body { margin: 0; min-width: 320px; }
button { font: inherit; }
#app { max-width: none; padding: 0; }

.erp-app { max-width: 76rem; margin: 0 auto; padding: 2rem clamp(1rem, 4vw, 3.5rem); }
.erp-app__heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 1.5rem; }
.erp-app__eyebrow { margin: 0 0 .45rem; color: #52647e; font-size: .82rem; }
.erp-app__heading h1 { margin: 0; font-size: clamp(1.7rem, 4vw, 2.4rem); letter-spacing: -.035em; }
.erp-app__summary { margin: .55rem 0 0; color: #52647e; }
.erp-app__action { min-height: 2.75rem; padding: .65rem 1rem; border: 0; border-radius: .4rem; color: white; background: #2458a6; cursor: pointer; }
.erp-app__overview { display: flex; gap: clamp(2rem, 8vw, 7rem); margin: 2.25rem 0; padding: 1.25rem 0; border-block: 1px solid #d5dce6; }
.erp-app__overview p { display: grid; gap: .25rem; margin: 0; }
.erp-app__overview strong { font-size: 1.7rem; font-variant-numeric: tabular-nums; }
.erp-app__overview span, .erp-app__section-heading > span { color: #52647e; font-size: .9rem; }
.erp-app__section-heading { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; margin-bottom: .75rem; }
.erp-app__section-heading h2 { margin: 0; font-size: 1.15rem; }
.erp-app__table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; text-align: left; }
th, td { padding: .9rem .75rem; border-bottom: 1px solid #d5dce6; }
th { color: #52647e; font-size: .82rem; font-weight: 600; }
td:first-child { font-variant-numeric: tabular-nums; font-weight: 600; }
.erp-app__status { display: inline-flex; align-items: center; min-height: 1.7rem; padding: .15rem .55rem; border-radius: 999px; font-size: .8rem; }
.is-progress { color: #254f8f; background: #e4edfb; }
.is-pending { color: #76500b; background: #fff1cd; }
.is-complete { color: #256246; background: #e1f3e8; }
:focus-visible { outline: 3px solid #1769d2; outline-offset: 3px; }

@media (max-width: 36rem) {
  .erp-app__heading { align-items: flex-start; flex-direction: column; }
  .erp-app__overview { justify-content: space-between; gap: 1rem; }
  th, td { padding-inline: .5rem; }
}
CSS;
    }

    private static function materialCss(): string
    {
        return <<<'CSS'
:root {
  color-scheme: light;
  --md-primary: #164f9c;
  --md-on-primary: #fff;
  --md-surface: #fff;
  --md-page: #edf2f7;
  --md-ink: #17243b;
  --md-muted: #52647e;
  --md-outline: #c9d3e1;
  --md-secondary: #9a5c00;
  --md-radius: 1rem;
  color: var(--md-ink);
  background: var(--md-page);
}

body { color: var(--md-ink); background: var(--md-page); }
body > header {
  min-height: 4.25rem;
  display: flex;
  align-items: center;
  padding-inline: clamp(1rem, 4vw, 3.5rem);
  color: var(--md-on-primary);
  background: #17365f;
  border: 0;
  font-size: .95rem;
}
#app { max-width: none; min-height: calc(100vh - 4.25rem); padding: 0; background: var(--md-page); }
.erp-app { max-width: 80rem; padding: clamp(1.5rem, 4vw, 3.5rem); }
.erp-app__heading { align-items: center; }
.erp-app__eyebrow { color: var(--md-primary); font-weight: 700; }
.erp-app__heading h1 { letter-spacing: -.04em; }
.erp-app__action {
  min-height: 3rem;
  padding-inline: 1.4rem;
  border-radius: 999px;
  color: var(--md-on-primary);
  background: var(--md-primary);
  font-weight: 650;
  box-shadow: 0 2px 4px #17243b2e;
  transition: box-shadow 140ms ease, background-color 140ms ease;
}
.erp-app__action:hover { background: #0e3c79; box-shadow: 0 4px 8px #17243b38; }
.erp-app__overview {
  gap: clamp(2.5rem, 8vw, 8rem);
  margin: 2rem 0;
  padding: 1.5rem 1.75rem;
  border: 1px solid var(--md-outline);
  border-radius: var(--md-radius);
  background: var(--md-surface);
}
.erp-app__overview strong { color: var(--md-primary); font-size: 2rem; }
.erp-app__orders {
  padding: 1.5rem 1.75rem .5rem;
  border: 1px solid var(--md-outline);
  border-radius: var(--md-radius);
  background: var(--md-surface);
}
.erp-app__section-heading { margin-bottom: 1rem; }
.erp-app__section-heading h2 { font-size: 1.25rem; }
th, td { border-color: var(--md-outline); }
th { color: var(--md-muted); }
.is-pending { color: #754700; background: #fff0cf; }
:focus-visible { outline-color: #145db5; }

@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
}
CSS;
    }

    private static function primeVuePlugin(): string
    {
        return <<<'TS'
import Aura from '@primeuix/themes/aura'
import PrimeVue from 'primevue/config'
import type { App, Plugin } from 'vue'

const primeVuePlugin: Plugin = {
  install(app: App) {
    app.use(PrimeVue, {
      theme: {
        preset: Aura,
        options: {
          darkModeSelector: '.dark-mode',
        },
      },
    })
  },
}

export default primeVuePlugin
TS;
    }

    private static function viteHeadPartial(): string
    {
        return <<<'PHP'
<?php
$devServer = rtrim(getenv('VITE_DEV_SERVER') ?: 'http://127.0.0.1:5173', '/');
$viteDev = (getenv('APP_ENV') ?: 'local') === 'local';
$viteEntry = null;

if ($viteDev): ?>
    <script type="module" src="<?= htmlspecialchars($devServer . '/@vite/client', ENT_QUOTES) ?>"></script>
<?php else:
    $manifestPath = dirname(__DIR__, 3) . '/public/build/manifest.json';

    if (!is_file($manifestPath)) {
        throw new RuntimeException('No existe public/build/manifest.json. Ejecuta npm run dr2g:build.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    $entry = is_array($manifest) ? ($manifest['resources/js/app.ts'] ?? null) : null;

    if (!is_array($entry) || !isset($entry['file']) || !is_string($entry['file'])) {
        throw new RuntimeException('El manifest de Vite no contiene resources/js/app.ts.');
    }

    $viteEntry = $entry;

    foreach (($entry['css'] ?? []) as $stylesheet): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars('/build/' . $stylesheet, ENT_QUOTES) ?>">
    <?php endforeach; ?>
    <script type="module" src="<?= htmlspecialchars('/build/' . $entry['file'], ENT_QUOTES) ?>"></script>
<?php endif; ?>
PHP;
    }

    private static function viteScriptsPartial(): string
    {
        return <<<'PHP'
<?php if ($viteDev): ?>
    <script type="module" src="<?= htmlspecialchars($devServer . '/resources/js/app.ts', ENT_QUOTES) ?>"></script>
<?php else: ?>
    <script type="module" src="<?= htmlspecialchars('/build/' . $viteEntry['file'], ENT_QUOTES) ?>"></script>
<?php endif; ?>
PHP;
    }
}
