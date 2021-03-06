<?php

namespace Kryn\CmsBundle\Admin\FieldTypes;

use Kryn\CmsBundle\Configuration\Configs;
use Kryn\CmsBundle\Configuration\Field;
use Kryn\CmsBundle\Configuration\Object;
use Kryn\CmsBundle\Exceptions\ModelBuildException;
use Kryn\CmsBundle\Exceptions\ObjectNotFoundException;
use Kryn\CmsBundle\Objects;
use Kryn\CmsBundle\ORM\ORMAbstract;
use Kryn\CmsBundle\Tools;

class TypeObject extends AbstractType
{
    protected $name = 'Object';

    /**
     * @var Objects
     */
    protected $objects;

    public function __construct(Objects $objects)
    {
        $this->objects = $objects;
    }

    public function getColumns()
    {
        if (ORMAbstract::MANY_TO_ONE == $this->getFieldDefinition()->getObjectRelation() ||
            ORMAbstract::ONE_TO_ONE == $this->getFieldDefinition()->getObjectRelation()
        ) {
            $foreignObjectDefinition = $this->objects->getDefinition($this->getFieldDefinition()->getObject());

            if (!$foreignObjectDefinition) {
                throw new ObjectNotFoundException(sprintf(
                    'ObjectKey `%s` not found in field `%s` of object `%s`',
                    $this->getFieldDefinition()->getObject(),
                    $this->getFieldDefinition()->getId(),
                    $this->getFieldDefinition()->getObjectDefinition()->getId()
                ));
            }

            /** @var $columns ColumnDefinition[] */
            $columns = [];

            foreach ($foreignObjectDefinition->getPrimaryKeys() as $pk) {
                $fieldColumns = $pk->getFieldType()->getColumns();
                $columns = array_merge($columns, $fieldColumns);
            }

            //rename columns to fieldId+column.id
            foreach ($columns as &$column) {
                $column = clone $column;
                $column->setName($this->getFieldDefinition()->getId() . ucfirst($column->getName()));
            }

            return $columns;
        }
    }

    /**
     * Returns the field names to select from the object model as array.
     *
     * @return string[]
     */
    public function getSelection()
    {
        $selection = [];
        if ($columns = $this->getColumns()) {
            foreach ($columns as $column) {
                $selection[] = $column->getName();
            }
        }

        return $selection;
    }

    public function bootBuildTime(Object $object, Configs $configs)
    {

    }

    public function bootRunTime(Object $object, Configs $configs)
    {
        $changed = false;
        $field = $this->getFieldDefinition();

        //check for n-to-n relation and create crossTable
        if (ORMAbstract::MANY_TO_MANY == $field->getObjectRelation()) {
            if ($this->defineCrossTable($object, $configs)) {
                $changed = true;
            }
        }

        //check for x-to-1 objectRelations and create cross object w/ relations
        if (ORMAbstract::MANY_TO_ONE == $field->getObjectRelation() ||
            ORMAbstract::ONE_TO_ONE == $field->getObjectRelation()
        ) {
            if ($this->defineRelation($object, $configs)) {
                $changed = true;
            }
        }

        //create virtual reference-field for many-to-one relations
        if ($this->getFieldDefinition()->getObjectRelation() == \Kryn\CmsBundle\ORM\ORMAbstract::MANY_TO_ONE) {
            if ($object = $configs->getObject($field->getObject())) {

                if (!$refName = $field->getObjectRefRelationName()) {
                    $refName = $field->getObjectDefinition()->getId();
                }

                $refName = lcfirst($refName);
                $virtualField = $object->getField($refName);

                if (!$virtualField) {

                    $virtualField = new Field(null, $object->getKrynCore());
                    $virtualField->setVirtual(true);
                    $virtualField->setId($refName);
                    $virtualField->setType('object');
                    $virtualField->setLabel('Auto Object Relation (' . $field->getObject() . ')');
                    $virtualField->setObject($field->getObjectDefinition()->getKey());
                    $virtualField->setObjectRelation(\Kryn\CmsBundle\ORM\ORMAbstract::ONE_TO_MANY);
                    $object->addField($virtualField);

                    $changed = true;
                }
            }
        }

        return $changed;
    }

    /**
     * @param \Kryn\CmsBundle\Configuration\Object $objectDefinition
     * @param Configs $configs
     * @return bool
     */
    protected function defineCrossTable(Object $objectDefinition, Configs $configs)
    {
        $changed = false;

        $bundle = $objectDefinition->getBundle();
        $foreignObjectDefinition = $configs->getObject($this->getFieldDefinition()->getObject());

        $possibleObjectName =
            ucfirst($objectDefinition->getId()) .
            ucfirst($foreignObjectDefinition->getId());
        $possibleObjectKey = $bundle->getName() . '/' . $possibleObjectName;


        if (!$crossObjectKey = $this->getFieldDefinition()->getObjectRelationCrossObjectKey()) {
            $crossObjectKey = $possibleObjectKey;
        }

        $crossObject = $configs->getObject($crossObjectKey);

        if (!$crossObject) {
            if (!$crossObject = $configs->getObject($possibleObjectKey)) {
                $crossObject = new Object(null, $objectDefinition->getKrynCore());
                $crossObject->setId($possibleObjectName);
                $crossObject->setTable($objectDefinition->getTable() . '_' . Tools::camelcase2Underscore($foreignObjectDefinition->getId()));
                $crossObject->setExcludeFromREST(true);
                $changed = true;
            }
        }

        if (!$crossObject->isCrossRef()) {
            $crossObject->setCrossRef(true);
            $changed = true;
        }

        $leftFieldName = $this->getFieldDefinition()->getObjectRefRelationName() ?: $objectDefinition->getId();
        if (!$crossObject->getField($leftFieldName)) {
            $leftObjectField = new Field(null, $objectDefinition->getKrynCore());
            $leftObjectField->setId($leftFieldName);
            $leftObjectField->setType('object');
            $leftObjectField->setObject($objectDefinition->getKey());
            $leftObjectField->setObjectRelation(ORMAbstract::ONE_TO_ONE);
            $leftObjectField->setPrimaryKey(true);

            $crossObject->addField($leftObjectField);
            $changed = true;
        }

        if (!$crossObject->getField($this->getFieldDefinition()->getId())) {
            $rightObjectField = new Field(null, $objectDefinition->getKrynCore());
            $rightObjectField->setId($this->getFieldDefinition()->getId());
            $rightObjectField->setType('object');
            $rightObjectField->setObject($foreignObjectDefinition->getKey());
            $rightObjectField->setObjectRelation(ORMAbstract::ONE_TO_ONE);
            $rightObjectField->setPrimaryKey(true);

            $crossObject->addField($rightObjectField);
            $changed = true;
        }

        if (!$crossObject->getBundle()) {
            //we created a new object
            $bundle->addObject($crossObject);
        }

        return $changed;
    }

    protected function defineRelation(Object $objectDefinition, Configs $configs)
    {
        $relation = $this->getRelation();
        if ($relation && !$objectDefinition->hasRelation($relation->getName())) {
            $objectDefinition->addRelation($relation);

            return true;
        }
    }

    /**
     * @return RelationDefinition|null
     * @throws \Kryn\CmsBundle\Exceptions\ModelBuildException
     */
    protected function getRelation()
    {
        $field = $this->getFieldDefinition();
        $columns = [];
        $foreignObjectDefinition = $this->objects->getDefinition($field->getObject());

        if (!$foreignObjectDefinition) {
            throw new ModelBuildException(sprintf(
                'ObjectKey `%s` not found for field `%s` in object `%s`',
                $field->getObject(),
                $field->getId(),
                $field->getObjectDefinition()->getId()
            ));
        }

        $relation = new RelationDefinition();
        $relation->setName($field->getId());
        $relation->setType(ORMAbstract::MANY_TO_ONE);
        $relation->setForeignObjectKey($field->getObject());

        if ($refName = $field->getObjectRefRelationName()) {
            $relation->setRefName($refName);
        }

        foreach ($foreignObjectDefinition->getPrimaryKeys() as $pk) {
            $fieldColumns = $pk->getFieldType()->getColumns();
            $columns = array_merge($columns, $fieldColumns);
        }

        if (!$columns) {
            return null;
        }

        $references = [];

        foreach ($columns as $column) {
            $reference = new RelationReferenceDefinition();

            $localColumn = clone $column;
            $localColumn->setName($field->getId() . ucfirst($column->getName()));
            $reference->setLocalColumn($localColumn);

            $reference->setForeignColumn($column);
            $references[] = $reference;
        }

        $relation->setReferences($references);

        return $relation;
    }

}