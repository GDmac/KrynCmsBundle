<?php

namespace Kryn\CmsBundle\Admin\FieldTypes;

class TypeCheckbox extends AbstractSingleColumnType
{
    protected $name = 'Checkbox';

    protected $phpDataType = 'boolean';

    protected $sqlDataType = 'boolean';
}