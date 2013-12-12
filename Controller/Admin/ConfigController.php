<?php

namespace Kryn\CmsBundle\Controller\Admin;

use Kryn\CmsBundle\Controller;
use Kryn\CmsBundle\Model\LanguageQuery;
use Propel\Runtime\Map\TableMap;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

class ConfigController extends Controller
{
    /**
     * @ApiDoc(
     *  section="System configuration",
     *  description="Returns labels for the settings window"
     * )
     *
     * @Rest\Get("/admin/system/config/labels")
     *
     * @return array['langs' => array[], 'timeozone' => string[]]
     */
    public function getLabelsAction()
    {
        $res['langs'] = LanguageQuery::create()
            ->orderByTitle()
            ->find()
            ->toArray(null, null, TableMap::TYPE_STUDLYPHPNAME);

        $res['timezones'] = timezone_identifiers_list();

        return $res;
    }

    /**
     * @ApiDoc(
     *  section="System configuration",
     *  description="Returns the system configuration"
     * )
     *
     *
     * @Rest\Get("/admin/system/config")
     *
     * @return array
     */
    public function getConfigAction()
    {
        return $this->getKrynCore()->getSystemConfig()->toArray(true);
    }

    /**
     * @ApiDoc(
     *  section="System configuration",
     *  description="Saves the system configuration"
     * )
     *
     *
     * @Rest\Put("/admin/system/config")
     *
     * @return boolean
     */
    public static function saveConfigAction()
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
