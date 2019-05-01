<?php

/**
 * @author Ilya Dashevsky <il.dashevsky@gmail.com>
 * @license The MIT License (MIT), http://opensource.org/licenses/MIT
 * @link https://github.com/edevelops/magic-spa-backend
 */

declare(strict_types = 1);

namespace MagicSpa\Services;

use Spot\Config;
use Spot\Locator;
use OpenCore\Db\SqlLoggerDebug;
use Closure;
use Throwable;
use OpenCore\Services\Injector;
use OpenCore\Db\InjectLocator;
use MagicSpa\Models\Mappers\AbstractMapper;
use Monolog\Logger;

class DbLocator {

    private $locator;

    public function __construct(array $configData, Injector $injector, Logger $sqlLogger = null) {
        $cfg = new Config();
        $cfg->addConnection('main', $configData);

        $this->locator = new InjectLocator($cfg, $injector);

        if ($sqlLogger) {
            $this->locator->config()->connection()->getConfiguration()->setSQLLogger(new SqlLoggerDebug($sqlLogger));
        }
    }

    public function transaction(Closure $work) {
        $connection = $this->locator->config()->connection();
        if($connection->isTransactionActive()){
            $result = $work();
        }else{
            try {
                $connection->beginTransaction();
                $result = $work();
                $connection->commit();
            } catch (Throwable $e) {
                $connection->rollback();
                throw $e;
            }
        }
        return $result;
    }

    /**
     * @return AbstractMapper
     */
    public function getMapper($className) {
        return $this->locator->mapper($className);
    }

}
