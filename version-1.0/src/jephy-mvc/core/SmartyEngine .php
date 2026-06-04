<?php
namespace App\Core;
// jephy-mvc/core/SmartyEngine.php

use App\Core\Framework;


interface ViewEngine
{
    public function assign($key, $value);
    public function render($template, $data = []);
    public function display($template);
}

class SmartyEngine implements ViewEngine
{
    private $smarty;
    
    public function __construct()
    {
        $this->smarty = Framework::getSmarty();
        $this->smarty->setTemplateDir( Config->getInstance()->get( 'smarty.template_dir' ) );
        $this->smarty->setCompileDir( Config->getInstance()->get( 'smarty.compile_dir' ) );
        $this->smarty->setCacheDir( Config->getInstance()->get( 'smarty.cache_dir' ) );
    }
    
    public function assign($key, $value)
    {
        $this->smarty->assign($key, $value);
        return $this;
    }
    
    public function render($template, $data = [])
    {
        foreach ($data as $key => $value) {
            $this->assign($key, $value);
        }
        
        return $this->smarty->fetch($template);
    }
    
    public function display($template)
    {
        $this->smarty->display($template);
    }
	
}

class PlainPhpEngine implements ViewEngine
{
    private $data = [];
    
    public function assign($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }
    
    public function render($template, $data = [])
    {
        $data = array_merge($this->data, $data);
        extract($data);
        
        ob_start();
        include $template;
        return ob_get_clean();
    }
    
    public function display($template)
    {
        echo $this->render($template);
    }
	
}
