<?php

/**
 * @author Ilya Dashevsky <il.dashevsky@gmail.com>
 * @license The MIT License (MIT), http://opensource.org/licenses/MIT
 * @link https://github.com/edevelops/magic-spa-backend
 */
declare(strict_types = 1);

namespace MagicSpa\Models\Mappers;

use Spot\Mapper;
use Spot\EntityInterface;
use Exception;
use MagicSpa\Utils\EntityUtils;
use MagicSpa\Models\Entities\AbstractEntity;

abstract class AbstractMapper extends Mapper {

    public $_injected = false;

    public function getByIds(array $ids): array {
        return $ids ? iterator_to_array($this->where(['id' => $ids])) : [];
    }

    public function saveRecursive(AbstractEntity $entity) {
        return $this->save($entity, ['relations' => true]);
    }

    private function checkTransaction() {
        if (!$this->connection()->isTransactionActive()) {
            throw new Exception('Transation in not active while saving');
        }
    }

    public function insert($entity, array $options = []) {
        $this->checkTransaction();
        return parent::insert($entity, $options);
    }

    public function update(EntityInterface $entity, array $options = []) {
        $this->checkTransaction();
        return parent::update($entity, $options);
    }

    public function save(EntityInterface $entity, array $options = []) {
        $this->checkTransaction();
        $ret = parent::save($entity, $options);
        if ($ret === false || $entity->hasErrors()) {
            throw new Exception('Unable to save entity: ' . print_r($entity->errors(), true));
        }
        return $ret;
    }

    public function deleteByIdsWithoutEvents(array $ids) {
        $this->delete(['id' => $ids]);
    }

    public function deleteEntities($entities) {
        $this->checkTransaction();

        $beforeEvent = 'beforeDelete';
        $afterEvent = 'afterDelete';

        $prevented = false;
        foreach ($entities as $entity) {
            if ($this->eventEmitter()->emit($beforeEvent, [$entity, $this]) === false) {
                $prevented = true;
                break;
            }
        }
        if (!$prevented) {
            $this->deleteByIdsWithoutEvents(EntityUtils::entitiesToIds($entities, $this->entity()));

            foreach ($entities as $entity) {
                $this->eventEmitter()->emit($afterEvent, [$entity, $this, null]);
            }
        }
        return $prevented ? false : null;
    }

}
