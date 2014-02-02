<?php

namespace Kryn\CmsBundle\Admin\FieldTypes;

interface RelationDefinitionInterface
{

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getRefName();

    /**
     * The objectKey this relation points to.
     *
     * e.g. kryncms/node
     *
     * @return string
     */
    public function getForeignObjectKey();

    /**
     * One of
     *
     * \Kryn\CmsBundle\ORM\ORMAbstract::
     *       MANY_TO_ONE = 'nTo1',
     *       ONE_TO_MANY = '1ToN',
     *       ONE_TO_ONE = '1To1',
     *       MANY_TO_MANY = 'nToM';
     *
     * @return string
     */
    public function getType();

    /**
     * @return RelationReferenceDefinitionInterface[]
     */
    public function getReferences();

    /**
     * @return string cascade|setnull|restrict|none
     */
    public function getOnDelete();

    /**
     * @return string cascade|setnull|restrict|none
     */
    public function getOnUpdate();
}
