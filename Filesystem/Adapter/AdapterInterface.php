<?php

namespace Kryn\CmsBundle\Filesystem\Adapter;

interface AdapterInterface {

    public function setMountPath($path);
    public function getMountPath();

    public function write($path, $content);
    public function read($path);
    public function has($path);
    public function delete($path);
    public function hash($path);

    public function getFiles($path);
    public function getFile($path);

    public function loadConfig();
}
