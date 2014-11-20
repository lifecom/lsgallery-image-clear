#!/usr/bin/env php
<?php
/* ---------------------------------------------------------------------------
 * @Plugin Name: LsGallery
 * @Plugin Id: lsGallery
 * @Plugin URI:
 * @Description:
 * @Author: stfalcon-studio
 * @Author URI: http://stfalcon.com
 * @LiveStreet Version: 1.0.1
 * @License: GNU GPL v2, http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * ----------------------------------------------------------------------------
 */
define('SYS_HACKER_CONSOLE', false);

$sDirRoot = dirname(realpath((dirname(__FILE__)) . "/../../../"));
set_include_path(get_include_path() . PATH_SEPARATOR . $sDirRoot);
chdir($sDirRoot);
require_once($sDirRoot . "/config/loader.php");
require_once($sDirRoot . "/engine/classes/Cron.class.php");

class CreateImageClear extends Cron
{
    protected function scanDirectory($dir)
    {
        $directories = scandir($dir);
        $files = array();

        foreach($directories as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            $filePath = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($filePath)) {
                $files = array_merge($files, $this->scanDirectory($filePath));
            } else {
                $files[] = $filePath;
            }
        }

        return $files;
    }

    public function Client()
    {
        // Get active plugins
        $aActivePlugins = $this->oEngine->Plugin_GetActivePlugins();

        //  Checking plugin self status
        if (!in_array('lsgallery', $aActivePlugins)) {
            echo "lsgallery plugin doesn't enabled! Please enable its before running." . PHP_EOL;
            return;
        }

        $aDirectory  = Config::Get('path.root.server') . Config::Get('path.uploads.lsgallery_images');
        $aRealFiles = array();
        foreach ($this->scanDirectory($aDirectory) as $file) {
            if (preg_match('/\/[a-z0-9]+\.[\w]{2,4}$/Ui', $file)) {
                $aRealFiles[str_replace(Config::Get('path.root.server'), '', $file)] = true;
            }
        }

        $iPage = 1;
        $iPerPage = 100;
        while(1) {
            $oImages = $this->oEngine->PluginLsgallery_Image_GetImagesByFilter(array(), $iPage, $iPerPage, array());
            if (!$oImages['collection']) {
                break;
            }

            foreach ($oImages['collection'] as $image) {
                if (isset($aRealFiles[$image->getFileName()])) {
                    unset($aRealFiles[$image->getFileName()]);
                }
            }

            $iPage++;
        }

        if (!$aRealFiles) {
            echo "Unused files not found!" . PHP_EOL;
            die;
        }

        $aSizes = Config::Get('plugin.lsgallery.size');
        foreach ($aRealFiles as $sFileName => $bRes) {
            foreach ($aSizes as $aSize) {
                // Для каждого указанного в конфиге размера генерируем картинку
                $fileInfo = pathinfo($sFileName);
                $fileInfo['filename'] = $fileInfo['filename'] . '_' . $aSize['w'];
                if ($aSize['crop']) {
                    $fileInfo['filename'] .= 'crop';
                }

                $sNewFileName = $fileInfo['filename'] . DIRECTORY_SEPARATOR . $fileInfo['filename'] . $fileInfo['extension'] ? ".{$fileInfo['extension']}" : '';
                if (is_file($sNewFileName)) {
                    echo 'remove: ' . $sNewFileName . PHP_EOL;
                    unlink(Config::Get('path.root.server') . $sNewFileName);
                }
            }
            echo 'remove: ' . $sFileName . PHP_EOL;
            unlink(Config::Get('path.root.server') . $sFileName);
        }

        $count = count($aRealFiles);
        echo "Removing finished successful ({$count})" . PHP_EOL;
    }
}

$sLockFilePath = Config::Get('sys.cache.dir') . 'lsgallery_image.lock';
/**
 * Создаем объект крон-процесса,
 * передавая параметром путь к лок-файлу
 */
$app = new CreateImageClear($sLockFilePath);
print $app->Exec();