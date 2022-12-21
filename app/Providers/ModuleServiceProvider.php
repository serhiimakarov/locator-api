<?php

namespace App\Providers;

use App\Providers\Traits\HasEvents;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * @TODO Reorganise to smaller classes or traits
 * @property string $routePrefix
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    use HasEvents;

    /**
     * Determine whether plural prefix will be used in url
     *
     * @var bool
     */
    protected $pluralRoutePrefix = true;

    /**
     * Determine whether api routes will be loaded
     *
     * @var bool
     */
    protected $loadApiRoutes = true;

    /**
     * List of all available policies.
     *
     * @var array
     */
    protected $policies = [];

    /**
     * List of all view composers.
     *
     * @var array
     */
    protected $composers = [];

    /**
     * Components
     *
     * @var array
     */
    protected $components = [];

    /**
     * Commands
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Settings
     * @var array
     */
    protected $settings = [];

    /**
     * Observers
     * @var array
     */
    protected $observers = [];

    /**
     * List of module data charts
     * @var array
     */
    protected $dataCharts = [];

    /**
     * Subscribers.
     * @var array
     */
    protected $subscribers = [];

    /**
     * Middleware.
     * @var array
     */
    protected $middleware = [];

    /**
     * Should use web prefix in module
     * @var bool
     */
    protected $useWebPrefix = true;

    /**
     * @var bool
     */
    protected $publishAssets = false;

    /**
     * Register any user services.
     *
     * @return void
     * @throws \ReflectionException
     */
    public function boot(): void
    {
        $this->loadViews();
        $this->loadMigrations($this->getDir());
        $this->loadPolicies();
        $this->loadTranslations();

        if ($this->isChildClass()) {
            $this->loadMigrations($this->getParentDir());
            $this->loadRoutes($this->getParentDir());
        }

        $this->loadRoutes($this->getDir());
        $this->loadComposers();
        $this->loadConfigs();
        $this->registerListeners();
        $this->commands($this->commands);

        if ($this->publishAssets) {
            $this->providesAssets();
        }
    }

    /**
     * Provide module assets
     */
    public function providesAssets()
    {
        $dir = $this->isChildClass() ? $this->getParentDir() : $this->getDir();

        if (is_dir($dir . '/config')) {
            $this->publishes([
                $dir . '/config' => 'modules/' . $this->getPrefix() . '/config',
            ]);
        }

        if (is_dir($dir . '/resources')) {
            $this->publishes([
                $dir . '/resources' => 'modules/' . $this->getPrefix() . '/resources',
            ]);
        }
    }

    /**
     * Get class namespace
     * @return string
     * @throws \ReflectionException
     */
    protected function getNamespace()
    {
        return $this->getClass()->getNamespaceName();
    }

    /**
     * @throws \ReflectionException
     */
    protected function isChildClass()
    {
        $reflector = new ReflectionClass(get_class($this));

        return $reflector->getParentClass()->getName() !== ModuleServiceProvider::class;
    }

    /**
     * Get directory
     *
     * @return string
     * @throws \ReflectionException
     */
    protected function getDir(): string
    {
        $reflector = new ReflectionClass(get_class($this));

        return dirname(dirname($reflector->getFileName()));
    }

    /**
     * Get parent directory
     *
     * @return string
     * @throws \ReflectionException
     */
    protected function getParentDir(): string
    {
        $reflector = new ReflectionClass(get_class($this));

        return dirname(dirname($reflector->getParentClass()->getFileName()));
    }

    /**
     * Get Class
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    protected function getClass()
    {
        return new ReflectionClass(get_class($this));
    }

    /**
     * Get parent class
     * @return false|\ReflectionClass
     * @throws \ReflectionException
     */
    protected function getParentClass()
    {
        return $this->getClass()->getParentClass();
    }

    /**
     * Return plural prefix for url
     *
     * @return string
     */
    protected function getPluralPrefix(): string
    {
        return $this->pluralRoutePrefix ? Str::plural($this->getPrefix()) : $this->getPrefix();
    }

    /**
     * Get module prefix
     *
     * @return string
     */
    protected function getPrefix(): string
    {
        $str   = str_ireplace(['ServiceProvider'], '', get_class($this));
        $parts = explode('\\', $str);

        return $parts[count($parts) - 1];
    }

    /**
     * Load routes for the module.
     * @param string $dir
     */
    protected function loadRoutes(string $dir): void
    {
        $this->app->booted(function () use ($dir) {
            if ($this->app->routesAreCached()) {
                return;
            }

            $pluralPrefix = $this->getPluralPrefix();

            $this->loadWebRoutes($pluralPrefix, $dir);

            if ($this->loadApiRoutes) {
                $this->loadApiRoutes($pluralPrefix, $dir);
            }

            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        });
    }

    /**
     * Get route prefix.
     * @return string
     */
    protected function getRoutePrefix(): string
    {
        return property_exists($this, 'routePrefix') ? $this->routePrefix : $this->getPluralPrefix();
    }

    /**
     * Load web routes.
     *
     * @param string $pluralPrefix
     *
     * @param string $dir
     */
    protected function loadWebRoutes(string $pluralPrefix, string $dir): void
    {
        $path = $dir . '/routes/web.php';

        if (!file_exists($path)) {
            return;
        }

        $route = Route::middleware(['web', 'browser']);

        if ($this->useWebPrefix) {
            $route->prefix(Str::kebab($this->getRoutePrefix()))
                ->name(Str::kebab($pluralPrefix) . ".");
        }

        $route->group($path);
    }

    /**
     * @param string $pluralPrefix
     *
     * @param string $dir
     */
    protected function loadApiRoutes(string $pluralPrefix, string $dir): void
    {
        $path = $dir . '/routes/api.php';

        if (!file_exists($path)) {
            return;
        }

        Route::middleware(['api', 'browser'])
            ->prefix('api/' . Str::kebab($this->getRoutePrefix()))
            ->name("api." . Str::kebab($pluralPrefix) . ".")
            ->group($path);
    }

    /**
     * Load translations for the module
     *
     * @throws \ReflectionException
     */
    public function loadTranslations(): void
    {
        $path       = $this->getDir() . '/resources/lang';
        $parentPath = $this->getParentDir() . '/resources/lang';

        if ($this->isChildClass()) {
            if (file_exists($path)) {
                $this->loadTranslationsFrom($path, $this->getPrefix());
            } elseif (file_exists($parentPath)) {
                $this->loadTranslationsFrom($parentPath, $this->getPrefix());
            }
        } else {
            if (file_exists($path)) {
                $this->loadTranslationsFrom($path, $this->getPrefix());
            }
        }
    }

    /**
     * Load views for the module.
     *
     * @throws \ReflectionException
     */
    protected function loadViews(): void
    {
        if (is_dir($this->getDir() . '/resources/views')) {
            $this->loadViewsFrom($this->getDir() . '/resources/views', $this->getPrefix());
        }

        if (is_dir($this->getParentDir() . '/resources/views')) {
            $this->loadViewsFrom($this->getParentDir() . '/resources/views', $this->getPrefix());
        }
    }

    /**
     * Load migrations for the module.
     * @param string $dir
     */
    protected function loadMigrations(string $dir): void
    {
        $this->loadMigrationsFrom($dir . '/database/migrations');
    }

    /**
     * @TODO Refactor
     * Load configs for the modules.
     *
     * @throws \ReflectionException
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function loadConfigs(): void
    {
        $path       = $this->getDir() . DIRECTORY_SEPARATOR . 'config';
        $childPath  = $this->getDir() . DIRECTORY_SEPARATOR . 'config';
        $parentPath = $this->getParentDir() . DIRECTORY_SEPARATOR . 'config';

        // In case when there is no config folder in child module
        if ($this->isChildClass()) {
            $path = $parentPath;
        }

        if (!is_dir($path)) {
            return;
        }

        $files = scandir($path);

        unset($files[0], $files[1]);

        foreach ($files as $file) {
            // Todo temporary fix must be deleted after CORE19-958 is done.
            if (is_dir($path . DIRECTORY_SEPARATOR . $file)) {
                continue;
            }

            if ($this->isChildClass()) {
                if (file_exists($childPath . DIRECTORY_SEPARATOR . $file)) {
                    $this->mergeConfigFrom(
                        $childPath . DIRECTORY_SEPARATOR . $file,
                        str_ireplace('.php', '', $this->getPrefix() . '::' . $file)
                    );
                }

                $this->mergeConfigFrom(
                    $parentPath . DIRECTORY_SEPARATOR . $file,
                    str_ireplace('.php', '', $this->getPrefix() . '::' . $file)
                );
            } else {
                $basePath = dirname(dirname(dirname(dirname($childPath)))) . '/config';

                if ($this->getPrefix() === 'Core') {
                    if (file_exists($basePath . DIRECTORY_SEPARATOR . $file)) {
                        if ($file !== 'app.php') {
                            $this->mergeConfigFrom(
                                $basePath . DIRECTORY_SEPARATOR . $file,
                                str_ireplace('.php', '', $file)
                            );

                            $this->mergeConfigFrom(
                                $path . DIRECTORY_SEPARATOR . $file,
                                str_ireplace('.php', '', $file)
                            );
                        }
                    } else {
                        $this->mergeConfigFrom(
                            $path . DIRECTORY_SEPARATOR . $file,
                            str_ireplace('.php', '', $file)
                        );
                    }
                }


                $this->mergeConfigFrom(
                    $path . DIRECTORY_SEPARATOR . $file,
                    str_ireplace('.php', '', $this->getPrefix() . '::' . $file)
                );
            }
        }
    }


    /**
     * @return string
     * @throws \ReflectionException
     */
    protected function currentDir()
    {
        return $this->isChildClass() ? $this->getParentDir() : $this->getDir();
    }

    /**
     * Load config file.
     *
     * @param string $file
     * @param string $name
     *
     * @throws \ReflectionException
     */
    protected function loadConfigFile(string $file, string $name): void
    {
        $this->mergeConfigFrom($this->getDir() . '/../config/' . $file . '.php', $name);
    }

    /**
     * Register policies for the module.
     */
    protected function loadPolicies(): void
    {
        foreach ($this->policies as $name => $policy) {
            $methods = get_class_methods($policy);

            Gate::resource($name, $policy, array_combine($methods, $methods));
        }
    }

    /**
     * Load view composers for the module.
     */
    protected function loadComposers(): void
    {
        foreach ($this->composers as $composer) {
            view()->composer($composer::$views, $composer);
        }
    }


}
