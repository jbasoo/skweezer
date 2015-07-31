<?php

class Skweezer {

    function getFileSize($image){
        $info = shell_exec('s=$(ls -sh ' . $image . ' | awk "{ print $5}") && echo $s');
        return strtok($info, ' ');
    }

    function getImages($path){
        $images = shell_exec('find ' . $path . ' -regex ".*\.\(jpg\|gif\|png\|jpeg\)"');
        $images = explode(PHP_EOL, $images);
        $images = array_filter($images);

        return $images;
    }

    function getCache(){
        $cacheFile = 'skweezer-cache.json';
        // @todo check if file exists
        // if (!file_exists($cacheFile)){
        //     fopen($cacheFile, 'w');
        //     fwrite($cacheFile, json_encode(array(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        //     fclose($cacheFile);
        // }

        $cache = file_get_contents($cacheFile);
        return json_decode($cache, true);
    }

    function setCache($cache){
        $cacheFile = fopen('skweezer-cache.json', 'w');
        fwrite($cacheFile, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        fclose($cacheFile);
    }

    function refreshCache($paths){
        $cache = $this->getCache();
        $images = $this->getImages($paths['images']);

        foreach ($images as $image) {
            if (!array_key_exists($image, $cache)){
                $cache[$image] = 'unskweezed';
            }
        }

        // @todo remove from cache if image no longer exists

        $this->setCache($cache);
    }

    function updateCache($image, $status){
        $cache = $this->getCache();
        $cache[$image] = $status;
        $this->setCache($cache);
    }

    function resize($image){
        shell_exec('mogrify -resize 2000x2000\> ' . $image);
    }

    function compress($image){
        $filename = trim(shell_exec('ls ' . $image . ' | xargs -n 1 basename'));
        $originalDir = trim(shell_exec('dirname ' . $image));
        shell_exec('mkdir .tmp');
        shell_exec('mv ' . $image . ' .tmp/');
        shell_exec('imagemin .tmp/' . $filename . ' > ' . $originalDir . '/' . $filename);
        shell_exec('rm -rf .tmp');
    }

    function skweeze($paths){
        $this->refreshCache($paths);

        $cache = $this->getCache();

        foreach ($cache as $image => $status) {
            if ($status == 'unskweezed') {
                try {
                    echo 'Skweezing ' . $image . ' ...';
                    echo "\n";
                    $unskweezedSize = $this->getFileSize($image);
                    $this->resize($image);
                    $this->compress($image);
                    $skweezedSize = $this->getFileSize($image);
                    echo $unskweezedSize . ' -> ' . $skweezedSize;
                    echo "\n\n";
                    $this->updateCache($image, 'skweezed ' . $unskweezedSize . ' -> ' . $skweezedSize);
                } catch (Exception $e) {
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                }
            }
        }

    }

    public function init($paths){
        chdir($paths['root']);
        $this->skweeze($paths);
    }
}

$paths = [
    'root' => '.',
    'images' => 'images'
];

$skweezer = new Skweezer();

$skweezer->init($paths);