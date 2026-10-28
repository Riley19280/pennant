<?php

namespace Laravel\Pennant\Commands;

use Illuminate\Console\Command;
use Laravel\Pennant\Contracts\CanListStoredFeatures;
use Laravel\Pennant\Drivers\Decorator;
use Laravel\Pennant\FeatureManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pennant:purge', aliases: ['pennant:clear'])]
class PurgeCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'pennant:purge
                            {features?* : The features to purge}
                            {--except=* : The features that should be excluded from purging}
                            {--except-registered : Purge all features except those registered}
                            {--store= : The store to purge the features from}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Pennant features from storage';

    /**
     * The console command name aliases.
     *
     * @var string[]
     */
    protected $aliases = ['pennant:clear'];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(FeatureManager $manager)
    {
        $store = $manager->store($this->option('store'));

        $features = $this->argument('features') ?: null;

        if ($this->option('except-registered')) {
            $this->registerFeatureClasses($store, $features);
        }

        $except = collect($this->option('except'))
            ->when($this->option('except-registered'), fn ($except) => $except->merge($store->defined()))
            ->unique()
            ->all();

        if ($except) {
            $features = collect($features ?: $store->stored())
                ->flip()
                ->forget($except)
                ->flip()
                ->values()
                ->all();
        }

        $store->purge($features);

        if ($features) {
            $this->components->info(implode(', ', $features).' successfully purged from storage.');
        } elseif ($except) {
            $this->components->info('No features to purge from storage.');
        } else {
            $this->components->info('All features successfully purged from storage.');
        }

        return self::SUCCESS;
    }

    /**
     * Register any class based features that have not been resolved yet.
     *
     * @param  Decorator  $store
     * @param  array<string>|null  $features
     * @return void
     */
    protected function registerFeatureClasses($store, $features)
    {
        if ($features === null && ! $store->getDriver() instanceof CanListStoredFeatures) {
            return;
        }

        collect($features ?? $store->stored())->each(fn ($feature) => $store->name($feature));
    }
}
