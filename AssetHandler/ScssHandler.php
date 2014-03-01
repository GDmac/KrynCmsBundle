<?php

namespace Kryn\CmsBundle\AssetHandler;

use Kryn\CmsBundle\Tools;

class ScssHandler extends AbstractHandler implements CompileHandlerInterface
{
    public function compileFile($assetPath)
    {
        $localPath = $this->getAssetPath($assetPath);
        if (!file_exists($localPath)){
            return null;
        }

        $publicPath = $this->getPublicAssetPath($assetPath);

        $targetPath = 'cache/scss/' . substr($publicPath, 0, strrpos($publicPath, '.'));
        if ('.css' !== substr($targetPath, -4)) {
            $targetPath .= '.css';
        }

        $needsCompilation = true;
        $sourceMTime = filemtime($localPath);

        if (file_exists('web/' . $targetPath)) {
            $fh = fopen('web/' . $targetPath, 'r+');
            if ($fh) {
                $firstLine = fgets($fh);
                $lastSourceMTime = (int) substr($firstLine, strlen('/* compiled at '), -3);

                $needsCompilation = $lastSourceMTime !== $sourceMTime;
            }
        }

        if ($needsCompilation) {
            $options = [
            ];
            $parser = new \SassParser($options);
            $compiled = $parser->toCss($localPath);

            $compiled = $this->replaceRelativePaths($publicPath, $targetPath, $compiled);
            $compiled = "/* compiled at $sourceMTime */\n".$compiled;
            $this->getKrynCore()->getWebFileSystem()->write($targetPath, $compiled);
        }

        $assetInfo = new AssetInfo();
        $assetInfo->setFile($targetPath);
        $assetInfo->setContentType('text/css');
        return $assetInfo;
    }

    /**
     * @param string $from scss path
     * @param string $to css path
     * @param string $content
     * @return string
     */
    protected function replaceRelativePaths($from, $to, $content)
    {
        $relative = Tools::getRelativePath(dirname($from), dirname($to)) . '/';

        $content = preg_replace('/@import \'([^\/].*)\'/', '@import \'' . $relative . '$1\'', $content);
        $content = preg_replace('/@import "([^\/].*)"/', '@import "' . $relative . '$1"', $content);
        $content = preg_replace('/url\(\'([^\/][^\)]*)\'\)/', 'url(\'' . $relative . '$1\')', $content);
        $content = preg_replace('/url\(\"([^\/][^\)]*)\"\)/', 'url(\"' . $relative . '$1\")', $content);
        $content = preg_replace('/url\((?!data:image)([^\/\'].*)\)/', 'url(' . $relative . '$1)', $content);

        return $content;
    }
}