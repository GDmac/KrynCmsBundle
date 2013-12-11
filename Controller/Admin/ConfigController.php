<?php

namespace Kryn\CmsBundle\Controller\Admin;

use Kryn\CmsBundle\Controller;
use Kryn\CmsBundle\Model\LanguageQuery;
use Propel\Runtime\Map\TableMap;

use FOS\RestBundle\Controller\Annotations as Rest;

class ConfigController extends Controller
{
    /**
     * @Rest\View()
     *
     * @Rest\Get("/system/config/labels")
     *
     * @return array['langs' => array[], 'timeozone' => string[]]
     */
    public function getLabels()
    {
        $res['langs'] = LanguageQuery::create()
            ->orderByTitle()
            ->find()
            ->toArray(null, null, TableMap::TYPE_STUDLYPHPNAME);

        $res['timezones'] = timezone_identifiers_list();

        return $res;
    }

    /**
     * @Rest\View()
     *
     * @Rest\Get("/system/config")
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->getKrynCore()->getSystemConfig()->toArray(true);
    }

    /**
     * @Rest\View()
     *
     * @Rest\Put("/system/config")
     *
     * @return boolean
     */
    public static function saveConfig()
    {
        //todo;
//        $cfg = include 'Config.php';
//
//        $blacklist[] = 'passwd_hash_key';
//
//        if (!getArgv('sessiontime')) {
//            $_REQUEST['sessiontime'] = 3600;
//        }
//
//        foreach ($_POST as $key => $value) {
//            if (!in_array($key, $blacklist)) {
//                $cfg[$key] = getArgv($key);
//            }
//        }
//
//        SystemFile::setContent('config.php', "<?php return " . var_export($cfg, true) . "\n? >");
//
//        dbUpdate('system_langs', array('visible' => 1), array('visible' => 0));
//        $langs = getArgv('languages');
//        foreach ($langs as $l) {
//            dbUpdate('system_langs', array('code' => $l), array('visible' => 1));
//        }

        return true;
    }


}