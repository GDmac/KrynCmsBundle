<?php

namespace Kryn\CmsBundle\Controller\Windows;


use Kryn\CmsBundle\Controller\WindowController;

class GroupCrudController extends WindowController
{
    public $fields = array(
        '__General__' =>
            array(
                'label' => 'General',
                'type' => 'tab',
                'children' =>
                    array(
                        'name',
                        'description'
                    ),
            ),
    );

    public $columns = array(
        'name',
        'description'
    );

    public $itemLayout = '{name}
<div style="color: silver">{if description.length > 20}{description.substr(0,20)}...{else}{description}{/if}</div>';

    public $itemsPerPage = 10;

    public $order = array(
        'name' => 'asc',
    );

    public $addIcon = '#icon-plus-5';

    public $add = true;

    public $editIcon = '#icon-pencil-8';

    public $edit = true;

    public $removeIcon = '#icon-minus-5';

    public $remove = true;

    public $export = false;

    public $object = 'kryncms/group';

    public $preview = false;

    public $titleField = 'name';

    public $workspace = false;

    public $multiLanguage = false;

    public $multiDomain = false;

    public $versioning = false;

}