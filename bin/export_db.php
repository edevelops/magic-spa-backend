#!/usr/local/bin/php
<?php
/**
 * @author Ilya Dashevsky <il.dashevsky@gmail.com>
 * @license The MIT License (MIT), http://opensource.org/licenses/MIT
 * @link https://github.com/edevelops/magic-spa-backend
 */

declare(strict_types = 1);

$deployer = include(__DIR__ . '/db_init.inc.php');

$deployer->export();
