<?php

namespace Kryn\CmsBundle\Admin;

interface ObjectCrudInterface {

    public function getCount();
    public function getParent($pk);
    public function getParents($pk);
    public function moveItem($sourceUrl, $targetUrl, $position = 'first', $targetObjectKey = '', $overwrite = false);
    public function getRoots();
    public function getRoot($scope = null);
    public function add($data = null, $pk = null, $position = null, $targetObjectKey = null);
    public function remove($pk);
    public function update($pk);
    public function patch($pk);
    public function getBranchChildrenCount($pk = null, $scope = null, $filter = null);
    public function getBranchItems(
        $pk = null,
        $filter = null,
        $fields = null,
        $scope = null,
        $depth = 1,
        $limit = null,
        $offset = null
    );

    public function getItems($filter = null, $limit = null, $offset = null, $query = '', $fields = null, $orderBy = []);
    public function getItem($pk, $fields = null, $withAcl = false);
}