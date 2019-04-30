<?php

declare(strict_types = 1);

use Psr\Container\ContainerInterface;
use DI\ContainerBuilder;
use FastRoute\RouteCollector;
use Relay\Relay;
use Zend\Diactoros\Response;
use Zend\Diactoros\Response\SapiEmitter;
use Zend\Diactoros\ServerRequestFactory;
use function DI\create;
use function DI\get;
use function DI\autowire;
use function FastRoute\simpleDispatcher;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\FirePHPHandler;
use OpenCore\CtrlContainer\CtrlContainerBuilder;
use Middlewares\FastRoute;
use Middlewares\RequestHandler;
use OpenCore\Middlewares\CsrfProtection;
use OpenCore\Middlewares\AuthMiddleware;
use OpenCore\Middlewares\LocaleMiddleware;
use MagicSpa\Services\DbLocator;
use MagicSpa\Services\AuthManager;
use OpenCore\Services\Injector;
use OpenCore\Rest\RestError;

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/vendor/autoload.php';

function main() {

    set_error_handler(function($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    //$firephp = new FirePHPHandler();
    $mainLogger = new Logger('main');

    $mainLogger->pushHandler(new StreamHandler(APP_ROOT . '/logs/main.log', Logger::DEBUG));
    //$mainLogger->pushHandler($firephp);

    set_exception_handler(function(Throwable $ex)use($mainLogger) {
        http_response_code(RestError::HTTP_INTERNAL_SERVER_ERROR);
        $mainLogger->critical($ex);
    });

    $containerBuilder = new ContainerBuilder();
    $containerBuilder->useAnnotations(false);
    $containerBuilder->addDefinitions([
        ContainerInterface::class => function()use(&$container) {
            return $container;
        },
        DbLocator::class => create()->constructor(function() {
                    return include APP_ROOT . '/config/db-config.php';
                }, get(Injector::class), function() {
                    return null;
//            $dbLogger = new Logger('sql');
//            $dbLogger->pushHandler(new StreamHandler(APP_ROOT.'/logs/sql.log', Logger::DEBUG));
//            return $dbLogger;
                }),
        Logger::class => $mainLogger,
        'response' => function() {
            return new Response();
        },
    ]);

    /** @noinspection PhpUnhandledExceptionInspection */
    $container = $containerBuilder->build();


    $writableMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];


    $ctrlContainerBuilder = new CtrlContainerBuilder();
    $ctrlContainerBuilder->useNamespace('MagicSpa\\Controllers');
    $ctrlContainerBuilder->useServicesContainer($container);
    $ctrlContainerBuilder->useLogger($mainLogger);
    $ctrlContainerBuilder->useDbTransations($writableMethods, function($work)use($container) {
        return $container->get(DbLocator::class)->transaction($work);
    });
    $ctrlContainer = $ctrlContainerBuilder->build();


    $handlerPermissionsMap = [];
    $routesTab = [];


    $routesConfig = json_decode(file_get_contents(APP_ROOT . '/config/rest-routes.json'));
    foreach ($routesConfig as $routeConfig) {
        $ctrlName = $routeConfig->controller;
        foreach ($routeConfig->routes as $route => $methods) {
            foreach ($methods as $httpMethod => $handlerProps) {
                $handlerName = $ctrlName . '.' . $handlerProps->method;
                $handlerPermissionsMap[$handlerName] = $handlerProps->permission;
                $routesTab[$httpMethod][$route] = $handlerName;
            }
        }
    }

    $routes = simpleDispatcher(function (RouteCollector $r)use($routesTab) {
        foreach ($routesTab as $httpMethod => $routeHandlers) {
            foreach ($routeHandlers as $route => $handlerName) {
                $r->addRoute($httpMethod, '/api/' . $route, $handlerName);
            }
        }
    });

    $middlewareQueue[] = new CsrfProtection($writableMethods, function()use($container) {
        return $container->get(AuthManager::class)->getCsrfToken();
    }, $mainLogger);
    $middlewareQueue[] = new FastRoute($routes);
    $middlewareQueue[] = new AuthMiddleware($handlerPermissionsMap, function($permission)use($container) {
        return $container->get(AuthManager::class)->hasPrivilege($permission);
    }, $mainLogger);
    $middlewareQueue[] = new LocaleMiddleware(function() {
        return ['langs' => ['en', 'es', 'ru', 'cn'], 'defaultLang' => 'en', 'defaultLocale' => 'en-US'];
    });
    $middlewareQueue[] = new RequestHandler($ctrlContainer);

    /** @noinspection PhpUnhandledExceptionInspection */
    $requestHandler = new Relay($middlewareQueue);
    $response = $requestHandler->handle(ServerRequestFactory::fromGlobals());

    $emitter = new SapiEmitter();
    /** @noinspection PhpVoidFunctionResultUsedInspection */
    return $emitter->emit($response);
}

return main();
