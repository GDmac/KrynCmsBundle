<?php

namespace Kryn\CmsBundle\Configuration;

use Kryn\CmsBundle\Core;
use Kryn\CmsBundle\Exceptions\BundleNotFoundException;
use Kryn\CmsBundle\Objects;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class Configs implements \IteratorAggregate
{
    /**
     * @var Bundle[]
     */
    private $configElements = array();

    /**
     * @var Core
     */
    protected $core;

    protected $triggeredReboot = [];

    /**
     * @param Core $core
     * @param array $bundles
     */
    public function __construct(Core $core, array $bundles = null)
    {
        $this->setCore($core);
        if ($bundles) {
            $this->loadBundles($bundles);
        }
    }

    public function loadBundles(array $bundles)
    {
        foreach ($bundles as $bundleName) {
            $bundle = $this->getCore()->getBundle($bundleName);
            $configs = $this->getXmlConfigsForBundle($bundle);
            $this->configElements = array_merge($this->configElements, $configs);
        }

        $this->configElements = $this->parseConfig($this->configElements);
    }

    /**
     * @param \Kryn\CmsBundle\Core $core
     */
    public function setCore($core)
    {
        $this->core = $core;
    }

    /**
     * @return \Kryn\CmsBundle\Core
     */
    public function getCore()
    {
        return $this->core;
    }

    /**
     * Returns a md5 hash of all kryn config files (Resources/config/kryn.*.xml).
     *
     * @param string $bundleName
     * @return string
     */
    public function getConfigHash($bundleName)
    {
        $hash = [];
        $bundle = $this->getCore()->getBundle($bundleName);
        foreach ($this->getConfigFiles($bundle) as $file) {
            $hash[] = filemtime($file);
        }

        return md5(implode('.', $hash));
    }

    /**
     * @param BundleInterface $bundle
     * @return string[]
     */
    public function getConfigFiles(BundleInterface $bundle)
    {
        $configDir = $bundle->getPath() . '/Resources/config/';
        $baseFile = $configDir . 'kryn.xml';

        $files = [];
        if (file_exists($baseFile)) {
            $files = [$configDir . 'kryn.xml'];
        }

        if (file_exists($configDir)) {
            $files = array_merge($files, glob($configDir . 'kryn.*.xml'));
        }

        return $files;
    }

    /**
     * Returns a array with following structure:
     *
     *   array[$bundleName][$priority][$file] = $bundle
     *
     * @param BundleInterface $bundle
     * @return array
     */
    public function getXmlConfigsForBundle(BundleInterface $bundle)
    {
        $configs = array();

        foreach ($this->getConfigFiles($bundle) as $file) {
            if (file_exists($file) && file_get_contents($file)) {
                $doc = new \DOMDocument();
                $doc->load($file);

                $bundles = $doc->getElementsByTagName('bundle');
                $bundleToImport = $bundle;
                foreach ($bundles as $bundleXml) {
                    if ($bundleXml->attributes->getNamedItem('name')) {
                        $bundleName = $bundleXml->attributes->getNamedItem('name')->nodeValue;
                        if (!$bundleToImport = $this->getCore()->getBundle($bundleName)) {
                            continue;
                        }
                    }
                    $priority = 0;
                    if ($bundleXml->attributes->getNamedItem('priority')) {
                        $priority = (int)$bundleXml->attributes->getNamedItem('priority')->nodeValue;
                    }

                    $configs[get_class($bundleToImport)][$priority][$file] = $bundleXml;
                }
            }
        }

        return $configs;
    }

    /**
     * @return bool
     */
    public function boot()
    {
        $changed = false;
        foreach ($this->configElements as $key => $config) {
            if ($boots = $config->boot($this)) {
                $changed = true;
                $count = (isset($this->triggeredReboot[$key]) ? $this->triggeredReboot[$key]['count'] : 0) + 1;
                $this->triggeredReboot[$key] = [
                    'count' => $count,
                    'triggeredReboots' => $boots
                ];
            }
        }
        return $changed;
    }

    /**
     * @return array
     */
    public function getTriggeredReboots()
    {
        return $this->triggeredReboot;
    }

    /**
     * $configs = $configs[$bundleName][$priority][] = $bundleDomElement;
     *
     * Parses and merges(imports) bundle configurations.
     *
     * @param array $configs
     *
     * @return \Kryn\CmsBundle\Configuration\Bundle[]
     */
    public function parseConfig(array $configs)
    {
        $bundleConfigs = array();
        foreach ($configs as $bundleName => $priorities) {
            ksort($priorities); //sort by priority

            foreach ($priorities as $configs) {
                foreach ($configs as $file => $bundleElement) {

                    $bundle = $this->getCore()->getBundle($bundleName);
                    $indexName = $this->normalizeBundleName($bundle->getName());
                    if (!isset($bundleConfigs[$indexName])) {
                        $bundleConfigs[$indexName] = new Bundle($bundle, null, $this->getCore());
                    }

                    $bundleConfigs[$indexName]->import($bundleElement, $file);
                }
            }

        }

        return $bundleConfigs;
    }

    /**
     * @param string $bundleName
     *
     * @return \Kryn\CmsBundle\Configuration\Bundle
     */
    public function getConfig($bundleName)
    {
        $bundleName = $this->normalizeBundleName($bundleName);
        return isset($this->configElements[$bundleName]) ? $this->configElements[$bundleName] : null;
    }

    /**
     * @param string $bundleName short version, long bundle name of full php class name
     * @return string short lowercased bundle name
     */
    public function normalizeBundleName($bundleName)
    {
        $bundleName = preg_replace('/bundle$/', '', strtolower($bundleName));
        if (false !== $pos = strrpos($bundleName, '\\')) {
            //it's a php class name
            $bundleName = substr($bundleName, $pos + 1);
        }
        return $bundleName;
    }

    /**
     * @param Bundle $bundle
     */
    public function addConfig(Bundle $bundle)
    {
        $this->configElements[$this->normalizeBundleName($bundle->getBundleName())] = $bundle;
    }

    /**
     * @param string $objectKey
     * @return \Kryn\CmsBundle\Configuration\Object
     */
    public function getObject($objectKey)
    {
        $objectKey = Objects::normalizeObjectKey($objectKey);
        if (false === strpos($objectKey, '/')) {
            return null;
        }
        list($bundleName, $objectName) = explode('/', $objectKey);

        if ('bundle' !== substr($bundleName, -6)) {
            $bundleName .= 'bundle';
        }

        if (!$config = $this->getConfig($bundleName)) {
            throw new BundleNotFoundException(sprintf('Bundle `%s` not found. [%s]', $bundleName, $objectKey));
        }

        return $config->getObject($objectName);
    }

    /**
     * @param string $themeId
     * @return Theme|null
     */
    public function getTheme($themeId)
    {
        foreach ($this->configElements as $config) {
            if ($theme = $config->getTheme($themeId)) {
                return $theme;
            }
        }

        return null;
    }

    /**
     * @return Bundle[]
     */
    public function getConfigs()
    {
        return $this->configElements;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $result = array();
        foreach ($this->configElements as $config) {
            $value = $config->toArray();
            $value['composer'] = $config->getComposer() ? : [];
            $result[$config->getBundleName()] = $value;
        }

        return $result;
    }

    /**
     * @return \Kryn\CmsBundle\Configuration\Bundle[]
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->configElements);
    }

    public function __sleep()
    {
        return ['configElements'];
    }

}
