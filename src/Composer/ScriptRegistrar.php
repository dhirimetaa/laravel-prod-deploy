<?php

declare(strict_types=1);

namespace Dhirimetaa\ProdDeploy\Composer;

final class ScriptRegistrar
{
    public const PACKAGE_NAME = 'dhirimetaa/laravel-prod-deploy';

    /** @return array<string, string|list<string>> */
    public static function scripts(): array
    {
        $timeout = 'Composer\\Config::disableProcessTimeout';

        return [
            'deploy:build' => [$timeout, 'prod-deploy build'],
            'deploy:push' => [$timeout, 'prod-deploy push'],
            'deploy:push:dry' => 'prod-deploy push --dry-run',
            'deploy:push:target' => [$timeout, 'prod-deploy push:target'],
            'deploy:push:target:dry' => 'prod-deploy push:target --dry-run',
            'deploy:vendor-push' => [$timeout, 'prod-deploy vendor:push'],
            'deploy:vendor-push:dry' => 'prod-deploy vendor:push --dry-run',
            'deploy:vendor-zip' => [$timeout, 'prod-deploy vendor:zip'],
            'deploy:vendor-zip-push' => [$timeout, 'prod-deploy vendor:zip-push'],
            'deploy:vendor-zip-push:dry' => 'prod-deploy vendor:zip-push --dry-run',
            'deploy:migrate' => [$timeout, 'prod-deploy migrate'],
            'deploy:optimize' => [$timeout, 'prod-deploy optimize'],
            'deploy:artisan' => [$timeout, 'prod-deploy artisan'],
            'deploy' => [$timeout, '@deploy:build', '@deploy:push'],
        ];
    }

    /**
     * @param  array<string, mixed>  $composerJson
     * @return array{merged: bool, added: list<string>, data: array<string, mixed>}
     */
    public static function merge(array $composerJson): array
    {
        /** @var array<string, string|list<string>> $scripts */
        $scripts = $composerJson['scripts'] ?? [];
        $added = [];

        foreach (self::scripts() as $name => $definition) {
            if (! array_key_exists($name, $scripts)) {
                $scripts[$name] = $definition;
                $added[] = $name;
            }
        }

        if ($added === []) {
            return ['merged' => false, 'added' => [], 'data' => $composerJson];
        }

        $composerJson['scripts'] = $scripts;

        return ['merged' => true, 'added' => $added, 'data' => $composerJson];
    }
}
