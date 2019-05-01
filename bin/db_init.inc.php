<?php

/**
 * @author Ilya Dashevsky <il.dashevsky@gmail.com>
 * @license The MIT License (MIT), http://opensource.org/licenses/MIT
 * @link https://github.com/edevelops/magic-spa-backend
 */

declare(strict_types = 1);

use MagicSpa\DbDeployer;

$dbConfg = include __DIR__ . '/../config/db-config.php';
require_once __DIR__ . '/include/db.inc.php';

return new DbDeployer($dbConfg['host'], $dbConfg['user'], $dbConfg['password'], $dbConfg['dbname'], 'mspa_', __DIR__ . '/../dump_sql');
