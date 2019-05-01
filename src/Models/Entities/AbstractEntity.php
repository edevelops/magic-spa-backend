<?php

/**
 * @author Ilya Dashevsky <il.dashevsky@gmail.com>
 * @license The MIT License (MIT), http://opensource.org/licenses/MIT
 * @link https://github.com/edevelops/magic-spa-backend
 */
declare(strict_types = 1);

namespace MagicSpa\Models\Entities;

use MagicSpa\Models\Mappers\DefaultMapper;
use Spot\Entity;

class AbstractEntity extends Entity {

    protected static $mapper = DefaultMapper::class;

    public function equals(AbstractEntity $other) {
        return $this->id === $other->id;
    }

}
