<?php

namespace Kryn\CmsBundle\Filesystem\Adapter;

use Kryn\CmsBundle\Core;
use Symfony\Component\DependencyInjection\ContainerAware;

/**
 * Abstract class for the FAL (File abstraction layer).
 *
 * Please note: All methods $path arguments are relative to your mountPath!
 *
 */
abstract class AbstractAdapter extends ContainerAware implements AdapterInterface
{
    /**
     * Current name of the mount point. (in fact, the folder name in media/<folder>)
     *
     * @var string
     */
    protected $mountPath = '';

    /**
     * Current params as array.
     *
     * @var array
     */
    protected $params = array();

    /**
     * Constructor
     *
     * @param string $mountPath The mount name for this layer. (in fact, the folder name in media/<folder>)
     * @param array $params
     */
    public function __construct($mountPath, $params = null)
    {
        $this->setMountPath($mountPath);

        if ($params) {
            $this->params = $params;
        }
    }

    /**
     * @return Core
     */
    public function getKrynCore()
    {
        return $this->container->get('kryn_cms');
    }

    /**
     * Gets a value of the params.
     *
     * @param  string $key
     *
     * @return mixed
     */
    public function getParam($key)
    {
        return isset($this->params[$key]) ? $this->params[$key] : null;
    }

    /**
     * Sets a value for a param.
     *
     * @param string $key
     * @param mixed $value
     */
    public function setParam($key, $value)
    {
        $this->params[$key] = $value;
    }

    /**
     * Sets the name of mount point.
     *
     * @param [type] $pEntryPoint [description]
     */
    public function setMountPath($mountPath)
    {
        $this->mountPath = $mountPath;
    }

    /**
     * Returns current name of the mount point.
     *
     * @return string
     */
    public function getMountPath()
    {
        return $this->mountPath;
    }

    /**
     * Returns the content hash (max 64 byte).
     *
     * @param $path
     *
     * @return string
     */
    public function getHash($path)
    {
        return md5($this->getContent($path));
    }
}
