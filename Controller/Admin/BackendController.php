<?php

namespace Kryn\CmsBundle\Controller\Admin;

use FOS\RestBundle\Request\ParamFetcher;
use Kryn\CmsBundle\Admin\Utils;
use Kryn\CmsBundle\Core;
use Kryn\CmsBundle\Model\Base\GroupQuery;
use Kryn\CmsBundle\Model\LanguageQuery;
use Kryn\CmsBundle\Properties;
use Propel\Runtime\Map\TableMap;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Response;

class BackendController extends Controller
{
    /**
     * @ApiDoc(
     *  section="Backend",
     *  description="Clears the cache"
     * )
     *
     * @Rest\Delete("/admin/backend/cache")
     *
     * @return bool
     */
    public function clearCacheAction()
    {
        $utils = new Utils($this->getKrynCore());

        $utils->clearCache();

        return true;
    }

    /**
     * @return Core
     */
    protected function getKrynCore()
    {
        return $this->get('kryn_cms');
    }

    /**
     * @ApiDoc(
     *  section="Backend",
     *  description="Saves user settings"
     * )
     *
     * @Rest\RequestParam(name="settings", array=true)
     *
     * @Rest\Post("/admin/backend/user-settings")
     *
     * @param ParamFetcher $paramFetcher
     *
     * @return bool
     */
    public function saveUserSettingsAction(ParamFetcher $paramFetcher)
    {
        $settings = $paramFetcher->get('settings');

        $properties = new Properties($settings);

        if ($this->getKrynCore()->getAdminClient()->getUser()->getId() > 0) {
            $this->getKrynCore()->getAdminClient()->getUser()->setSettings($properties);
            return $this->getKrynCore()->getAdminClient()->getUser()->save();
        }

        return false;
    }

    /**
     * @ApiDoc(
     *  section="Backend",
     *  description="Prints the javascript file content of $bundle and $code."
     * )
     *
     * @Rest\QueryParam(name="bundle", requirements=".+", strict=true, description="The bundle name")
     * @Rest\QueryParam(name="code", requirements=".+", strict=true, description="Slash separated entry point path")
     * @Rest\QueryParam(name="onLoad", requirements=".+", strict=true, description="onLoad id")

     * @Rest\Get("/admin/backend/custom-js")
     *
     * @param ParamFetcher $paramFetcher
     *
     * @return string javascript
     */
    public function getCustomJsAction(ParamFetcher $paramFetcher)
    {
        $bundle = $paramFetcher->get('bundle');
        $code = $paramFetcher->get('code');
        $onLoad = $paramFetcher->get('onLoad');

        $module = preg_replace('[^a-zA-Z0-9_-]', '', $bundle);
        $code = preg_replace('[^a-zA-Z0-9_-]', '', $code);
        $onLoad = preg_replace('[^a-zA-Z0-9_-]', '', $onLoad);

        $bundle = $this->getKrynCore()->getBundle($module);

        $file = $bundle->getPath() . '/Resources/public/admin/js/' . $code . '.js';

        if (!file_exists($file)) {
            $content = "contentCantLoaded_" . $onLoad . "('$file');\n";
        } else {
            $content = file_get_contents($file);
            $content .= "\n";
            $content .= "contentLoaded_" . $onLoad . '();' . "\n";
        }

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/javascript');
        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Backend",
     *  description="Returns a array with settings for the administration interface"
     * )
     *
     * items:
     *  modules
     *  configs
     *  layouts
     *  contents
     *  navigations
     *  themes
     *  themeProperties
     *  user
     *  groups
     *  langs
     *
     *  Example: settings?keys[]=modules&keys[]=layouts
     *
     * @Rest\QueryParam(name="keys", array=true, requirements=".+", description="List of config keys to filter"))
     *
     * @Rest\Get("/admin/backend/settings")
     *
     * @param ParamFetcher $paramFetcher
     *
     * @return array
     */
    public function getSettingsAction(ParamFetcher $paramFetcher)
    {
        $keys = $paramFetcher->get('keys');

        $loadKeys = $keys;
        if (!$loadKeys) {
            $loadKeys = false;
        }

        $res = array();

        if ($loadKeys == false || in_array('modules', $loadKeys)) {
            foreach ($this->getKrynCore()->getConfigs() as $config) {
                $res['bundles'][] = $config->getBundleName();
            }
        }

        if ($loadKeys == false || in_array('configs', $loadKeys)) {
            $res['configs'] = $this->getKrynCore()->getConfigs()->toArray();
        }

        if (
            $loadKeys == false || in_array('themes', $loadKeys)
        ) {
            foreach ($this->getKrynCore()->getConfigs() as $key => $config) {
                if ($config->getThemes()) {
                    foreach ($config->getThemes() as $themeTitle => $theme) {
                        /** @var $theme \Kryn\CmsBundle\Configuration\Theme */
                        $res['themes'][$theme->getId()] = $theme->toArray();
                    }
                }
            }
        }

        if ($loadKeys == false || in_array('upload_max_filesize', $loadKeys)) {
            $v = ini_get('upload_max_filesize');
            $v2 = ini_get('post_max_size');
            $b = $this->toBytes(($v < $v2) ? $v : $v2);
            $res['upload_max_filesize'] = $b;
        }

        if ($loadKeys == false || in_array('groups', $loadKeys)) {
            $res['groups'] = GroupQuery::create()->find()->toArray(null, null, TableMap::TYPE_STUDLYPHPNAME);
        }

        if ($loadKeys == false || in_array('user', $loadKeys)) {
            if ($settings = $this->getKrynCore()->getAdminClient()->getUser()->getSettings()) {
                if ($settings instanceof Properties) {
                    $res['user'] = $settings->toArray();
                }
            }

            if (!isset($res['user'])) {
                $res['user'] = array();
            }
        }

        if ($loadKeys == false || in_array('system', $loadKeys)) {
            $system = clone $this->getKrynCore()->getSystemConfig();
            $system->setDatabase(null);
            $system->setPasswordHashKey('');
            $res['system'] = $system->toArray();
        }

        if ($loadKeys == false || in_array('domains', $loadKeys)) {
            $res['domains'] = $this->getKrynCore()->getObjects()->getList('KrynCmsBundle:Domain', null, array('permissionCheck' => true));
        }

        if ($loadKeys == false || in_array('langs', $loadKeys)) {
            $tlangs = LanguageQuery::create()->filterByVisible(true)->find()->toArray(
                null,
                null,
                TableMap::TYPE_STUDLYPHPNAME
            );

            $langs = [];
            foreach ($tlangs as $lang) {
                $langs[$lang['code']] = $lang;
            }
            #$langs = dbToKeyIndex($tlangs, 'code');
            $res['langs'] = $langs;
        }

        return $res;
    }

    protected function toBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * @ApiDoc(
     *  section="Backend",
     *  description="Prints compressed script map"
     * )
     *
     * @Rest\Get("/admin/backend/script-map")
     */
    public function loadJsMapAction()
    {
        $this->loadJsAction(true);
    }

    /**
     * @ApiDoc(
     *  section="Backend",
     *  description="Prints all CSS files combined"
     * )
     *
     * @Rest\Get("/admin/backend/css")
     *
     * @return string CCS
     */
    public function loadCssAction()
    {

        $oFile = $this->getKrynCore()->getKernel()->getRootDir(). '/../web/cache/admin.style-compiled.css';
        $md5String = '';

        $files = [];
        foreach ($this->getKrynCore()->getConfigs() as $bundleConfig) {
            foreach ($bundleConfig->getAdminAssetsPaths(false, '.*\.css', true, true) as $assetPath) {
                $path = $this->getKrynCore()->resolvePath($assetPath, 'Resources/public');
                if (file_exists($path)) {
                    $files[] = $assetPath;
                    $md5String .= filemtime($path);
                }
            }
        }

        $handle = @fopen($oFile, 'r');
        $fileUpToDate = false;
        $md5Line = '/* ' . md5($md5String) . "*/\n";

        if ($handle) {
            $line = fgets($handle);
            fclose($handle);
            if ($line == $md5Line) {
                $fileUpToDate = true;
            }
        }

        if (!$fileUpToDate) {
            $content = $this->getKrynCore()->getUtils()->compressCss($files, $this->getKrynCore()->getAdminPrefix() . 'admin/backend/');
            $content = $md5Line . $content;
            file_put_contents($oFile, $content);
        }


        $expires = 60 * 60 * 24 * 14;

        $response = new Response(file_get_contents($oFile));
        $response->headers->set('Content-Type', 'text/css');
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Cache-Control', 'max-age=' . $expires);
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
        return $response;
    }

    /**
     * @ApiDoc(
     *  section="Backend",
     *  description="Prints all JavaScript files combined"
     * )
     *
     * @Rest\QueryParam(name="printSourceMap", requirements=".+", description="If the sourceMap should printed")
     *
     * @Rest\Get("/admin/backend/script")
     *
     * @param boolean $printSourceMap
     *
     * @return string javascript
     */
    public function loadJsAction($printSourceMap = null)
    {
        $printSourceMap = filter_var($printSourceMap, FILTER_VALIDATE_BOOLEAN);
        $oFile = 'cache/admin.script-compiled.js';

        $files = array();
        $assets = array();
        $md5String = '';
        $newestMTime = 0;


        foreach ($this->getKrynCore()->getConfigs() as $bundleConfig) {
            foreach ($bundleConfig->getAdminAssetsPaths(false, '.*\.js', true, true) as $assetPath) {
                $path = $this->getKrynCore()->resolvePath($assetPath, 'Resources/public');
                if (file_exists($path)) {
                    $assets[] = $assetPath;
                    $files[] = '--js ' . escapeshellarg($this->getKrynCore()->resolvePublicPath($assetPath));
                    $mtime = filemtime($path);
                    $newestMTime = max($newestMTime, $mtime);
                    $md5String .= ">$path.$mtime<";
                }
            }
        }
//        chdir($web);

        $ifModifiedSince = $this->getKrynCore()->getRequest()->headers->get('If-Modified-Since');
        if (isset($ifModifiedSince) && (strtotime($ifModifiedSince) == $newestMTime)) {
            // Client's cache IS current, so we just respond '304 Not Modified'.

            $response = new Response();
            $response->setStatusCode(304);
            $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', $newestMTime).' GMT');
            return $response;
        }


        $expires = 60 * 60 * 24 * 14; //2 weeks
        $response = new Response();
        $response->headers->set('Content-Type', 'application/javascript');
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Cache-Control', 'max-age=' . $expires);
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');

        $sourceMap = $oFile . '.map';
        $cmdTest = 'java -version';
        $closure = 'vendor/google/closure-compiler/compiler.jar';
        $compiler = escapeshellarg(realpath('../' . $closure));
        $cmd = 'java -jar ' . $compiler . ' --js_output_file ' . escapeshellarg($oFile);
        $returnVal = 0;
        $debugMode = false;

        if ($printSourceMap) {
            $content = file_get_contents($sourceMap);
            $content = str_replace('"bundles/', '"../../../bundles/', $content);
            $content = str_replace('"cache/admin.script-compiled.js', '"kryn/admin/backend/script.js', $content);
            $response->setContent($content);
            return $response;
        }

        $handle = @fopen($oFile, 'r');
        $fileUpToDate = false;
        $md5Line = '//' . md5($md5String) . "\n";

        if ($handle) {
            $line = fgets($handle);
            fclose($handle);
            if ($line == $md5Line) {
                $fileUpToDate = true;
            }
        }

        if ($fileUpToDate) {
            $content = file_get_contents($oFile);
            $response->setContent(substr($content, 35));
            return $response;
        } else {
            if (!$debugMode) {
                system($cmdTest, $returnVal);
            }

            if (0 === $returnVal) {
                $cmd .= ' --create_source_map ' . escapeshellarg($sourceMap);
                $cmd .= ' --source_map_format=V3';

                $cmd .= ' ' . implode(' ', $files);
                $cmd .= ' 2>&1';
                $output = shell_exec($cmd);
                if (0 !== strpos($output, 'Unable to access jarfile')) {
                    if (false !== strpos($output, 'ERROR - Parse error')) {
                        $content = 'alert(\'Parse Error\;);';
                        $content .= $output;
                        $response->setContent($content);
                        return $response;
                    }
                    $content = file_get_contents($oFile);
                    $sourceMapUrl = '//@ sourceMappingURL=script-map';
                    $content = $md5Line . $content . $sourceMapUrl;
                    file_put_contents($oFile, $content);

                    $response->setContent(substr($content, 35));
                    return $response;
                }

            }


            $content = '';
            foreach ($assets as $assetPath) {
                $content .= "/* $assetPath */\n\n";
                $path = $this->getKrynCore()->resolvePath($assetPath, 'Resources/public');
                $content .= file_get_contents($path);
            }

            $response->setContent($content);
            return $response;
        }

    }

    /**
     * @ApiDoc(
     *  section="Backend",
     *  description="Returns all available menu/entryPoint items for the main navigation bar in the administration"
     * )
     *
     * @Rest\View()
     * @Rest\Get("/admin/backend/menus")
     *
     * @return array
     */
    public function getMenusAction()
    {
        $entryPoints = array();

        foreach ($this->getKrynCore()->getConfigs() as $bundleName => $bundleConfig) {
            foreach ($bundleConfig->getAllEntryPoints() as $subEntryPoint) {
                $path = $bundleConfig->getName() . '/' . $subEntryPoint->getFullPath(true);

                if (substr_count($path, '/') <= 3) {
                    if ($subEntryPoint->isLink()) {
                        if ($this->getKrynCore()->getACL()->check('kryncmsbundle:entryPoint', '/' . $path)) {
                            $entryPoints[$path] = array(
                                'label' => $subEntryPoint->getLabel(),
                                'icon' => $subEntryPoint->getIcon(),
                                'fullPath' => $path,
                                'path' => $subEntryPoint->getPath(),
                                'type' => $subEntryPoint->getType(),
                                'system' => $subEntryPoint->getSystem(),
                                'level' => substr_count($path, '/')
                            );
                        }
                    }
                }
            }
        }

        return $entryPoints;
    }

    /**
     * @param string $code
     * @param string $value
     * @return array
     */
    protected function getChildMenus($code, $value)
    {
        $links = array();
        foreach ($value['children'] as $key => $value2) {

            if ($value2['children']) {

                $childs = $this->getChildMenus($code . "/$key", $value2);
                if (count($childs) == 0) {
                    //if ($this->getKrynCore()->checkUrlAccess($code . "/$key")) {
                    unset($value2['children']);
                    $links[$key] = $value2;
                    //}
                } else {
                    $value2['children'] = $childs;
                    $links[$key] = $value2;
                }

            } else {
                //if ($this->getKrynCore()->checkUrlAccess($code . "/$key")) {
                $links[$key] = $value2;
                //}
            }
            if ((!$links[$key]['type'] && !$links[$key]['children']) || $links[$key]['isLink'] === false) {
                unset($links[$key][$key]);
            }

        }

        return $links;
    }

}
