<?php

declare(strict_types=1);

/**
 * This file is part of Scout Extended.
 *
 * (c) Algolia Team <contact@algolia.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Algolia\ScoutExtended\Helpers;

use Error;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use function in_array;
use Laravel\Scout\Searchable;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
final class SearchableFinder
{
    /**
     * @var array
     */
    private static $declaredClasses;

    /**
     * @var \Illuminate\Contracts\Foundation\Application
     */
    private $app;

    /**
     * SearchableModelsFinder constructor.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @return void
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Get a list of searchable models from the given command.
     *
     * @param \Illuminate\Console\Command $command
     *
     * @return array
     */
    public function fromCommand(Command $command): array
    {
        $searchables = (array) $command->argument('searchable');

        if (empty($searchables) && empty($searchables = $this->find($command))) {
            throw new InvalidArgumentException('No searchable classes found.');
        }

        return $searchables;
    }

    /**
     * Get a list of searchable models.
     *
     * @return string[]
     */
    public function find(Command $command): array
    {
        $appNamespace = $this->app->getNamespace();

        return array_values(array_filter($this->getProjectClasses($command), function (string $class) use ($appNamespace) {
            return Str::startsWith($class, $appNamespace) && $this->isSearchableModel($class);
        }));
    }

    /**
     * @param  string $class
     *
     * @return bool
     */
    private function isSearchableModel($class): bool
    {
        return in_array(Searchable::class, class_uses_recursive($class), true);
    }

    /**
     * @return array
     */
    private function getProjectClasses(Command $command): array
    {
        if (self::$declaredClasses === null) {
            $configFiles = Finder::create()->files()->name('*.php')->in($this->app->path());

            foreach ($configFiles->files() as $file) {
                try {
                    require_once $file;
                } catch (Error $e) {
                    // log a warning to the user and continue
                    $command->info("{$file} could not be inspected due to an error being thrown while loading it.");
                }
            }

            self::$declaredClasses = get_declared_classes();
        }

        return self::$declaredClasses;
    }
}
