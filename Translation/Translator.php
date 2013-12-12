<?php

namespace Kryn\CmsBundle\Translation;

use Kryn\CmsBundle\Core;

class Translator implements TranslationInterface
{
    protected $messages = [];

    /**
     * @var Core
     */
    protected $krynCore;

    function __construct(Core $krynCore)
    {
        $this->krynCore = $krynCore;
        $this->loadMessages();
    }


    /**
     * Check whether specified pLang is a valid language
     *
     * @param string $lang
     *
     * @return bool
     * @internal
     */
    public function isValidLanguage($lang)
    {
        if (!$this->krynCore->getSystemConfig()->getLanguages() && $lang == 'en') {
            return true;
        } //default

        if ($this->krynCore->getSystemConfig()->getLanguages()) {
            $languages = explode(',', preg_replace('/[^a-zA-Z0-9]/', '', $this->krynCore->getSystemConfig()->getLanguages()));
            return array_search($lang, $languages) !== true;
        } else {
            return $lang == 'en';
        }
    }

    public function loadMessages($lang = 'en', $force = false)
    {
        if (!$this->isValidLanguage($lang)) {
            $lang = 'en';
        }

        if ($this->messages && isset($this->messages['__lang']) && $this->messages['__lang'] == $lang && $force == false) {
            return;
        }

        if (!$lang) {
            return;
        }

        $code = 'core/lang/' . $lang;
        $this->messages =& $this->krynCore->getFastCache()->get($code);

        $md5 = '';
        $bundles = array();
        foreach ($this->krynCore->getKernel()->getBundles() as $bundleName => $bundle) {
            $path = $bundle->getPath();
            if ($path) {
                $path .= "Resources/translations/$lang.po";
                $md5 .= @filemtime($path);
                $bundles[] = $bundleName;
            }
        }

        $md5 = md5($md5);

        if ((!$this->messages || count($this->messages) == 0) || !isset($this->messages['__md5']) || $this->messages['__md5'] != $md5) {

            $this->messages = array('__md5' => $md5, '__plural' => $this->getPluralForm($lang), '__lang' => $lang);

            foreach ($bundles as $key) {
                $file = $this->krynCore->resolvePath("@$key/$lang.po", 'Resources/translations');
                $po = $this->getLanguage($file);
                $this->messages = array_merge($this->messages, $po['translations']);
            }
            $this->krynCore->getFastCache()->set($code, $this->messages);
        }

        include_once($this->getPluralPhpFunctionFile($lang));

        return $this->messages;
    }


    public function getPluralPhpFunctionFile($lang)
    {
        $fs = $this->krynCore->getCacheFileSystem();

        $file = 'core_gettext_plural_fn_' . $lang . '.php';
        if (!$fs->has($file)) {
            $pluralForm = $this->getPluralForm($lang, true);

            $code = "<?php

if (!function_exists('gettext_plural_fn_$lang')) {
    function gettext_plural_fn_$lang(\$n){
        return " . str_replace('n', '$n', $pluralForm) . ";
    }
}
";
            $fs->write($file, $code);
        }

        return $fs->getAdapter()->getRoot() . '/' . $file;
    }


    /**
     * @param $lang
     *
     * @return string Returns the public accessible file path
     */
    public function getPluralJsFunctionFile($lang)
    {
        $fs = $this->krynCore->getWebFileSystem();

        $file = 'cache/core_gettext_plural_fn_' . $lang . '.js';
        if (!$fs->has($file)) {
            $pluralForm = $this->getPluralForm($lang, true);

            $code = "function gettext_plural_fn_$lang(n){\n";
            $code .= "    return " . $pluralForm . ";\n";
            $code .= "}";
            $fs->write($file, $code);
        }

        return 'cache/core_gettext_plural_fn_' . $lang . '.js';
    }

    public function getLanguage($file)
    {
        return $this->getUtils()->parsePo($file);
    }

    public function getPluralForm($lang, $onlyAlgorithm = false)
    {
        return $this->getUtils()->getPluralForm($lang, $onlyAlgorithm);
    }

    /**
     * @return Utils
     */
    public function getUtils()
    {
        $utils = new Utils();
        $utils->setContainer($this->krynCore->getKernel()->getContainer());
        return $utils;
    }

    public function t($id, $plural = null, $count = 0, $context = null)
    {
        $oriId = $id;
        $id = ($context == '') ? $id : $context . "\004" . $id;

        if (isset($this->messages[$id])) {
            if (is_array($this->messages[$id])) {

                if ($count) {
                    $plural = intval(@call_user_func('gettext_plural_fn_' . $this->messages['__lang'], $count));
                    if ($count && $this->messages[$id][$plural]) {
                        return str_replace('%d', $count, $this->messages[$id][$plural]);
                    } else {
                        return (($count === null || $count === false || $count === 1) ? $id : $plural);
                    }
                } else {
                    return $this->messages[$id][0];
                }
            } else {
                return $this->messages[$id];
            }
        } else {
            return $context ? $oriId : $id;
        }
    }

    public function tc($context, $id, $plural = null, $count = 0)
    {
        return $this->t($id, $plural, $count, $context);
    }

}