<?php

namespace Basis\Provider;

use Basis\Config;
use Basis\Filesystem;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Tarantool\Client\Client as TarantoolClient;
use Tarantool\Client\Connection\Connection;
use Tarantool\Client\Connection\StreamConnection;
use Tarantool\Client\Packer\Packer;
use Tarantool\Client\Packer\PurePacker;
use Tarantool\Mapper\Bootstrap;
use Tarantool\Mapper\Client;
use Tarantool\Mapper\Entity;
use Tarantool\Mapper\Mapper;
use Tarantool\Mapper\Plugin;
use Tarantool\Mapper\Plugin\Annotation;
use Tarantool\Mapper\Plugin\Sequence;
use Tarantool\Mapper\Plugin\Spy;
use Tarantool\Mapper\Plugin\Temporal;
use Tarantool\Mapper\Schema;

class TarantoolProvider extends AbstractServiceProvider
{
    protected $provides = [
        Bootstrap::class,
        Client::class,
        Connection::class,
        Mapper::class,
        Packer::class,
        Pool::class,
        Schema::class,
        Spy::class,
        StreamConnection::class,
        TarantoolClient::class,
        Temporal::class,
    ];

    public function register()
    {
        $this->container->share(Bootstrap::class, function () {
            return $this->container->get(Mapper::class)->getBootstrap();
        });

        $this->getContainer()->share(Client::class, function () {
            return new Client(
                $this->getContainer()->get(Connection::class),
                $this->getContainer()->get(Packer::class)
            );
        });

        $this->getContainer()->share(Connection::class, function () {
            return $this->getContainer()->get(StreamConnection::class);
        });

        $this->getContainer()->share(Mapper::class, function () {
            $mapper = new Mapper($this->getContainer()->get(Client::class));
            $filesystem = $this->getContainer()->get(Filesystem::class);

            $mapperCache = $filesystem->getPath('.cache/mapper-meta.php');
            if (file_exists($mapperCache)) {
                $meta = include $mapperCache;
                $mapper->setMeta($meta);
            }

            $annotation = $mapper->getPlugin(Annotation::class);

            foreach ($filesystem->listClasses('Entity') as $class) {
                $annotation->register($class);
            }
            foreach ($filesystem->listClasses('Repository') as $class) {
                $annotation->register($class);
            }

            $mapper->getPlugin(Sequence::class);
            $mapper->getPlugin(Spy::class);

            $mapper->getPlugin(Temporal::class)
                ->getAggregator()
                ->setReferenceAggregation(false);

            $mapper->application = $this->getContainer();

            $mapper->getPlugin(new class($mapper) extends Plugin {
                public function afterInstantiate(Entity $entity)
                {
                    $entity->app = $this->mapper->application;
                }
            });

            return $mapper;
        });

        $this->getContainer()->share(Packer::class, function () {
            return new PurePacker();
        });

        $this->getContainer()->share(Schema::class, function () {
            return $this->getContainer()->get(Mapper::class)->getSchema();
        });

        $this->getContainer()->share(Spy::class, function () {
            return $this->getContainer()->get(Mapper::class)->getPlugin(Spy::class);
        });

        $this->getContainer()->share(StreamConnection::class, function () {
            $config = $this->getContainer()->get(Config::class);
            return new StreamConnection($config['tarantool.connection'], $config['tarantool.params']);
        });

        $this->getContainer()->share(TarantoolClient::class, function () {
            return $this->getContainer()->get(Client::class);
        });

        $this->getContainer()->share(Temporal::class, function () {
            return $this->getContainer()->get(Mapper::class)->getPlugin(Temporal::class);
        });
    }
}
