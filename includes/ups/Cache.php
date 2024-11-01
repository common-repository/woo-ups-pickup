<?php
/**
 * @category Ups
 * @copyright Ups Company
 */
namespace Ups;

class Cache
{
    protected $isLoaded = false;

    protected $cached = array();

    protected $filesystem;

    protected $filename = 'ups_services';

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public function get($key)
    {
        return false;
        if (!$this->isLoaded) {
            $this->loadCache();
        }

        return isset($this->cached[$key]) ? $this->cached[$key] : null;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return Cache
     */
    public function save($key, $value)
    {
        $this->cached[$key] = $value;

        return $this->_save();
    }

    /**
     * @param string $key
     * @return Cache
     */
    public function remove($key)
    {
        if (isset($this->cached[$key])) {
            unset($this->cached[$key]);
            return $this->_save();
        }

        return $this;
    }

    /**
     * @return Cache
     */
    protected function _save()
    {
        $cachePath = $this->getCacheFile();
        if (!file_exists($cachePath)) {
            $cachePath = $this->createCacheFile();
        }

        if (!$cachePath) {
            return $this;
        }

        $data = json_encode($this->cached);
        file_put_contents($cachePath, $data);

        return $this;
    }

    /**
     * @return Cache
     */
    protected function loadCache()
    {
        $cachePath = $this->getCacheFile();

        if(!file_exists($cachePath)) {
            $cachePath = $this->createCacheFile();
        }

        if (!$cachePath) {
            $this->isLoaded = true;
            return $this;
        }

        if ($this->isExpired($cachePath)) {
            $this->cleanCache();
            $this->isLoaded = true;
            return $this;
        }

        $data = file_get_contents($cachePath);
        $this->cached = json_decode($data, true);

        return $this;
    }

    /**
     * @param string $file
     * @return bool
     */
    protected function isExpired($file)
    {
        $creationTime = filectime($file);

        return (time() - $creationTime) > 86400;
    }

    /**
     * @return string
     *
     * @since 2.1.0
     */
    public function getFilename(){
        return $this->filename;
    }

    /**
     * @return bool|string
     */
    protected function createCacheFile()
    {
        return $this->filesystem->createFile('cache', $this->getFilename());
    }

    /**
     * @return string
     */
    protected function getCacheFile()
    {
        return $this->filesystem->getFilePath('cache',$this->getFilename());
    }

    /**
     * @return Cache
     */
    public function cleanCache()
    {
        $cachePath = $this->getCacheFile();

        if (is_file($cachePath) && is_writeable($cachePath)) {
            unlink($cachePath);
        }

        $this->cached = array();

        return $this;
    }
}