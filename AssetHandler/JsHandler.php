<?php

namespace Kryn\CmsBundle\AssetHandler;

class JsHandler extends AbstractHandler implements LoaderHandlerInterface
{
    protected function getTag(AssetInfo $assetInfo)
    {
        if ($assetInfo->getFile()) {
            return sprintf(
                '<script type="text/javascript" src="%s"></script>',
                $this->getPublicAssetPath($assetInfo->getFile())
            );
        } else {
            return sprintf(
                <<<EOF
<script type="text/javascript">
%s
</script>
EOF
                ,
                $assetInfo->getContent()
            );
        }
    }

    /**
     * @param AssetInfo[] $assetsInfo
     * @param bool $concatenation
     * @return string
     */
    public function getTags(array $assetsInfo = array(), $concatenation = false)
    {
        $tags = [];

        if ($concatenation) {
            $filesToCompress = [];

            foreach ($assetsInfo as $asset) {
                if ($asset->getFile()) {
                    // load javascript files, that are not accessible (means those point to a controller)
                    // because those can't be compressed
                    $localPath = $this->getAssetPath($asset->getFile());
                    if (!file_exists($localPath)) {
                        $tags[] = $this->getTag($asset);
                        continue;
                    }
                }

                if ($asset->getContent()) {
                    // load inline assets because we can't compress those
                    $tags[] = $this->getTag($asset);
                    continue;
                }

                if (!$asset->isCompressionAllowed()) {
                    $tags[] = $this->getTag($asset);
                    continue;
                }

                $filesToCompress[] = $asset->getFile();
            }

            if ($filesToCompress) {
                $tags[] = $this->getTag($this->compressFiles($filesToCompress));
            }
        } else {
            foreach ($assetsInfo as $asset) {
                $tags[] = $this->getTag($asset);
            }
        }

        return implode("\n", $tags);
    }

    /**
     * @param array $files
     *
     * @return AssetInfo
     */
    public function compressFiles(array $files)
    {
        $md5String = '';

        foreach ($files as $file) {
            $path = $this->getAssetPath($file);
            $md5String .= '.' . filemtime($path);
        }

        $fileUpToDate = false;
        $md5Line = '/* ' . md5($md5String) . " */\n";

        $oFile = 'cache/compressed-js/' . md5($md5String) . '.js';
        $handle = @fopen($this->getKrynCore()->getKernel()->getRootDir() . '/../web/' . $oFile, 'r');
        if ($handle) {
            $line = fgets($handle);
            fclose($handle);
            if ($line == $md5Line) {
                $fileUpToDate = true;
            }
        }

        if (true || !$fileUpToDate) {
            $result = $this->getKrynCore()->getUtils()->compressJs(
                $files,
                $oFile
            );
            $content = $md5Line . $this->getKrynCore()->getWebFileSystem()->read($oFile);
            $this->getKrynCore()->getWebFileSystem()->write($oFile, $content);
        }

        $assetInfo = new AssetInfo();
        $assetInfo->setFile($oFile);

        return $assetInfo;
    }
}