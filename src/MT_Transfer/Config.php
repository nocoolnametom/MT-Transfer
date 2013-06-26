<?php

namespace MT_Transfer;

class Config
{
    protected $_config = array();
    protected $_file;

    public function __construct($filename = null)
    {
        $this->setFile($filename);
    }
    
    public function setFile($filename)
    {
        return $this->_file = $filename; 
    }

    protected function parseIni()
    {
        return $this->_config = parse_ini_file($this->_file, true);
    }

    public function getConfig()
    {
        if (empty($this->_config))
        {
            $this->parseIni();
        }
        return $this->_config;
    }

    public function get($option, $section = null)
    {
        $config = $this->getConfig();
        if ($section)
        {
            if (!isset($config[$section]))
            {
                return false;
            }
            $config = $config[$section];
        }
        if (!isset($config[$option]))
        {
            return false;
        }
        return $config[$option];
    }
}
