<?php

/**
 * @author Ilya Dashevsky <il.dashevsky@gmail.com>
 * @license The MIT License (MIT), http://opensource.org/licenses/MIT
 * @link https://github.com/edevelops/magic-spa-backend
 */

declare(strict_types = 1);


namespace MagicSpa\Controllers;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use OpenCore\Rest\RestError;
use ErrorException;
use MagicSpa\Services\CronHandler;
use OpenCore\Utils\TextUtils;
use Monolog\Logger;

class CronController {

    private $container;
    private $logger;

    public function __construct(ContainerInterface $container, Logger $logger) {
        $this->container = $container;
        $this->logger = $logger;
    }

    public function runCron(ServerRequestInterface $request, string $task) {
        $query = $request->getQueryParams();
        if (!isset($query['key']) || $query['key'] !== getenv('CRON_KEY')) {
            throw new RestError('Invalid key', RestError::HTTP_UNAUTHORIZED);
        } else {
            $handlerName = 'cronHandler.' . TextUtils::toCamelCase($task);
            if (!$this->container->has($handlerName)) {
                throw new RestError('Can not find cron handler', RestError::HTTP_NOT_FOUND);
            }
            $handler = $this->container->get($handlerName);
            if (!($handler instanceof CronHandler)) {
                throw new ErrorException('Invalid cron handler "' . $handlerName . '"');
            }
            unset($query['key']);
            $handler->executeTask($query);
        }
        return null;
    }

}
