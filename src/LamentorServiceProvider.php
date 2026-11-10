<?php

namespace Lamentor;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Lamentor\Console\InstallAssetsCommand;
use Lamentor\Console\RenderCommand;
use Lamentor\Css\Breakpoints;
use Lamentor\Dynamic\DynamicTags;
use Lamentor\Dynamic\LoopSources;
use Lamentor\Css\StyleGenerator;
use Lamentor\Kit\Kit;
use Lamentor\Library\DirectorySource;
use Lamentor\Library\Library;
use Lamentor\Library\LocalSource;
use Lamentor\Rendering\Renderer;
use Lamentor\Support\FrontendConfig;
use Lamentor\Schema\SchemaRepository;
use Lamentor\Widgets\Accordion;
use Lamentor\Widgets\Alert;
use Lamentor\Widgets\Audio;
use Lamentor\Widgets\Column;
use Lamentor\Widgets\Counter;
use Lamentor\Widgets\Divider;
use Lamentor\Widgets\Form;
use Lamentor\Widgets\GoogleMaps;
use Lamentor\Widgets\IconList;
use Lamentor\Widgets\LoopGrid;
use Lamentor\Widgets\NavMenu;
use Lamentor\Widgets\ImageBox;
use Lamentor\Widgets\ImageCarousel;
use Lamentor\Widgets\ImageGallery;
use Lamentor\Widgets\MenuAnchor;
use Lamentor\Widgets\Progress;
use Lamentor\Widgets\Rating;
use Lamentor\Widgets\Section;
use Lamentor\Widgets\SocialIcons;
use Lamentor\Widgets\StarRating;
use Lamentor\Widgets\Tabs;
use Lamentor\Widgets\Testimonial;
use Lamentor\Widgets\Toggle;
use Lamentor\Widgets\Video;
use Lamentor\Widgets\Button;
use Lamentor\Widgets\Container;
use Lamentor\Widgets\Heading;
use Lamentor\Widgets\Icon;
use Lamentor\Widgets\IconBox;
use Lamentor\Widgets\Image;
use Lamentor\Widgets\RawHtml;
use Lamentor\Widgets\Spacer;
use Lamentor\Widgets\TextEditor;
use Lamentor\Widgets\WidgetRegistry;

class LamentorServiceProvider extends ServiceProvider
{
    private const WIDGETS = [
        Heading::class, TextEditor::class, Spacer::class, RawHtml::class, Button::class, Image::class,
        Icon::class, IconBox::class, Alert::class, Counter::class, MenuAnchor::class, Progress::class,
        IconList::class, ImageBox::class, Testimonial::class, SocialIcons::class, StarRating::class,
        Rating::class, Tabs::class, Accordion::class, Toggle::class, Divider::class, GoogleMaps::class,
        Video::class, Audio::class, ImageCarousel::class, ImageGallery::class, Form::class, LoopGrid::class, NavMenu::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/lamentor.php', 'lamentor');

        $this->app->singleton(DynamicTags::class);
        $this->app->singleton(LoopSources::class);
        $this->app->singleton(SchemaRepository::class, fn () => new SchemaRepository());

        $this->app->singleton(WidgetRegistry::class, function () {
            $registry = new WidgetRegistry();
            foreach (self::WIDGETS as $widget) {
                $registry->register($widget);
            }
            foreach ([Container::class, Section::class, Column::class] as $element) {
                $registry->registerElement($element);
            }

            return $registry;
        });

        $this->app->singleton(Library::class, function ($app) {
            $library = new Library();
            $config = (array) config('lamentor.library', []);
            if ($config['local'] ?? true) {
                $library->register(new LocalSource(array_values((array) ($config['local_types'] ?? ['section', 'container']))));
            }
            if (! empty($config['directory'])) {
                $library->register(new DirectorySource((string) $config['directory'], 'blocks', (string) ($config['directory_label'] ?? 'Block library')));
            }
            foreach ((array) ($config['sources'] ?? []) as $class) {
                $library->register($app->make($class));
            }

            return $library;
        });

        $this->app->singleton(Breakpoints::class, fn () => new Breakpoints(config('lamentor.breakpoints')));
        $this->app->singleton(Kit::class, fn () => new Kit((array) config('lamentor.kit', []), config('lamentor.kit_id', 1)));
        $this->app->singleton(FrontendConfig::class, fn ($app) => new FrontendConfig(
            $app->make(Breakpoints::class),
            $app->make(Kit::class),
            rtrim((string) config('lamentor.assets_url', '/vendor/lamentor/elementor'), '/') . '/assets',
        ));
        $this->app->singleton(StyleGenerator::class, fn ($app) => new StyleGenerator($app->make(Breakpoints::class), $app->make(Kit::class)));

        $this->app->singleton(Renderer::class, fn ($app) => new Renderer(
            $app->make(WidgetRegistry::class),
            $app->make(StyleGenerator::class),
            $app->make(Kit::class),
            $app->make(Breakpoints::class),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'lamentor');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        Blade::componentNamespace('Lamentor\\View\\Components', 'lamentor');

        if (config('lamentor.routes', true)) {
            Route::middleware((array) config('lamentor.middleware', ['web', 'auth']))
                ->prefix((string) config('lamentor.route_prefix', 'lamentor'))
                ->group(__DIR__ . '/../routes/web.php');
        }

        // Public form submissions: separate group, so editor auth never blocks visitors.
        if (config('lamentor.routes', true)) {
            Route::middleware((array) config('lamentor.forms_middleware', ['web', 'throttle:10,1']))
                ->prefix((string) config('lamentor.route_prefix', 'lamentor'))
                ->group(__DIR__ . '/../routes/forms.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallAssetsCommand::class, RenderCommand::class]);
        }


        $this->publishes([
            __DIR__ . '/../config/lamentor.php' => config_path('lamentor.php'),
        ], 'lamentor-config');

        // php artisan vendor:publish --tag=lamentor-assets
        $this->publishes([
            __DIR__ . '/../resources/css' => public_path('vendor/lamentor/elementor/assets/css'),
        ], 'lamentor-assets');

        $this->publishes([
            __DIR__ . '/../resources/js' => public_path('vendor/lamentor/elementor/assets/js'),
        ], 'lamentor-assets');
    }
}
