<?php

namespace Basis\Provider;

use Basis\Application;
use Basis\Cache;
use Basis\Config;
use Basis\Event;
use Basis\Filesystem;
use Basis\Service;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Tarantool\Mapper\Pool;

class ServiceProvider extends AbstractServiceProvider
{
    protected $provides = [
        Event::class,
        Service::class,
    ];

    public function register()
    {
        $this->getContainer()->share(Event::class, function () {
            $app = $this->getContainer()->get(Application::class);
            $service = $this->getContainer()->get(Service::class);
            $pool = $this->getContainer()->get(Pool::class);
            $filesystem = $this->getContainer()->get(Filesystem::class);
            return new Event($app, $service, $pool, $filesystem);
        });

        $this->getContainer()->share(Service::class, function () {
            $config = $this->getContainer()->get(Config::class);
            $app = $this->getContainer()->get(Application::class);
            $cache = $this->getContainer()->get(Cache::class);
            return new Service($config['service'], $app, $cache);
        });
    }
}
