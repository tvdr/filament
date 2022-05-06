<?php

namespace Filament\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePageCommand extends Command
{
    use Concerns\CanManipulateFiles;
    use Concerns\CanValidateInput;

    protected $description = 'Creates a Filament page class and view.';

    protected $signature = 'make:filament-page {name?} {--R|resource=} {--F|force}';

    public function handle(): int
    {
        $page = (string) Str::of($this->argument('name') ?? $this->askRequired('Name (e.g. `Settings`)', 'name'))
            ->trim('/')
            ->trim('\\')
            ->trim(' ')
            ->replace('/', '\\');
        $pageClass = (string) Str::of($page)->afterLast('\\');
        $pageNamespace = Str::of($page)->contains('\\') ?
            (string) Str::of($page)->beforeLast('\\') :
            '';

        $resource = null;
        $resourceClass = null;
        $resourcePage = null;

        $resourceInput = $this->option('resource') ?? $this->ask('(Optional) Resource (e.g. `UserResource`)');

        if ($resourceInput !== null) {
            $resource = (string) Str::of($resourceInput)
                ->studly()
                ->trim('/')
                ->trim('\\')
                ->trim(' ')
                ->replace('/', '\\');

            if (! Str::of($resource)->endsWith('Resource')) {
                $resource .= 'Resource';
            }

            $resourceClass = (string) Str::of($resource)
                ->afterLast('\\');

            $resourcePage = $this->choice(
                'Which page type would you like to create?',
                [
                    'Custom Page',
                    'ListRecords',
                    'ManageRecords',
                    'CreateRecord',
                    'EditRecord',
                    'ViewRecord',
                ],
                '0'
            );
            $resourcePage = $resourcePage === 'Custom Page' ? 'Page' : $resourcePage;
        }

        $view = Str::of($page)
            ->prepend($resource === null ? 'filament\\pages\\' : "filament\\resources\\{$resource}\\pages\\")
            ->explode('\\')
            ->map(fn ($segment) => Str::kebab($segment))
            ->implode('.');

        $path = app_path(
            (string) Str::of($page)
                ->prepend($resource === null ? 'Filament\\Pages\\' : "Filament\\Resources\\{$resource}\\Pages\\")
                ->replace('\\', '/')
                ->append('.php'),
        );
        $viewPath = resource_path(
            (string) Str::of($view)
                ->replace('.', '/')
                ->prepend('views/')
                ->append('.blade.php'),
        );

        $files = array_merge(
            [$path],
            $resourcePage === 'Page' ? [$viewPath] : []
        );

        if (!$this->option('force') && $this->checkForCollision($files)) {
            return static::INVALID;
        }

        if ($resource === null) {
            $this->copyStubToApp('Page', $path, [
                'class' => $pageClass,
                'namespace' => 'App\\Filament\\Pages' . ($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'view' => $view,
            ]);
        } else {
            $resourcePageStub = $resourcePage === 'Page' ? 'CustomResourcePage' : 'ResourcePage';
            $this->copyStubToApp($resourcePageStub, $path, [
                'baseResourcePage' => 'Filament\\Resources\\Pages\\' . $resourcePage,
                'baseResourcePageClass' => $resourcePage,
                'namespace' => "App\\Filament\\Resources\\{$resource}\\Pages" . ($pageNamespace !== '' ? "\\{$pageNamespace}" : ''),
                'resource' => $resource,
                'resourceClass' => $resourceClass,
                'resourcePageClass' => $pageClass,
                'view' => $view,
            ]);
        }

        if ($resource === null || $resourcePage === 'Page') {
            $this->copyStubToApp('PageView', $viewPath);
        }

        $this->info("Successfully created {$page}!");

        if ($resource !== null) {
            $this->info("Make sure to register the page in `{$resourceClass}::getPages()`.");
        }

        return static::SUCCESS;
    }
}
