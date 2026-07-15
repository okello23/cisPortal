<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $_ENV['APP_CONFIG_CACHE'] = sys_get_temp_dir().'/cisportal-testing-config.php';
        $_SERVER['APP_CONFIG_CACHE'] = $_ENV['APP_CONFIG_CACHE'];
        putenv('APP_CONFIG_CACHE='.$_ENV['APP_CONFIG_CACHE']);
        $_ENV['APP_ROUTES_CACHE'] = sys_get_temp_dir().'/cisportal-testing-routes.php';
        $_SERVER['APP_ROUTES_CACHE'] = $_ENV['APP_ROUTES_CACHE'];
        putenv('APP_ROUTES_CACHE='.$_ENV['APP_ROUTES_CACHE']);
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');

        $app = require Application::inferBasePath().'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('mail.default', 'array');

        return $app;
    }
}
