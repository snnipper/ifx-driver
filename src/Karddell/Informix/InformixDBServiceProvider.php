<?php

namespace Karddell\Informix;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;
use Karddell\Informix\Connectors\IfxConnector as Connector;
/**
 * Class InformixDBServiceProvider.
 */
class InformixDBServiceProvider extends ServiceProvider
{

    /**
     * Boot.
     */
    public function boot()
    {
        $this->publishes(
            [
                __DIR__.'/../../config/informix.php' => config_path('informix.php'),
            ]
        );
    }

    /**
     * Register the service provider.
     *
     * @returns \Karddell\Informix\IfxConnection
     */
    public function register()
    {
        if (file_exists(config_path('informix.php'))) {

            $this->mergeConfigFrom(config_path('informix.php'), 'database.connections');
            $config = $this->app['config']->get('informix', []);
            $connection_keys = array_keys($config);

            foreach ($connection_keys as $key) {
                Connection::resolverFor($key, function ($connection, $database, $prefix, $config){
                    $connector = new Connector();
                    $connection = $connector->connect($config);
                    $db = new IfxConnection($connection, $database, $prefix, $config);
                    return $db; 
                });
            }

        }
    }
}
