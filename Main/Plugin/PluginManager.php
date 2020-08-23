<?php

namespace YunTaIDC\Plugin;

use YunTaIDC\Main\Main;

class PluginManager{
    
    public $path = BASE_ROOT.'/Plugins/';
    public $dataFolder = BASE_ROOT .'/PluginData/';
    public $PluginClass = array();
    public $Plugins = array();
    public $PluginType = array();
    public $PluginPage = array();
    public $PluginPath = array();
    
    private $Database;
    private $System;
    
    public function __construct(Main $Main){
        $this->System = $Main;
        $this->Database = $this->System->getDatabase();
    }
    
    public function loadPluginFiles(){
        if($handle = opendir($this->path)){
            while(false !== ($entry = readdir($handle))){
                if($entry != '.' && $entry != '..' && is_dir($this->path .'/'. $entry)){
                    if(file_exists($this->path.'/'. $entry.'/plugin.json')){
                        $config = json_decode(file_get_contents($this->path.'/'. $entry.'/plugin.json'),true);
                        $pluginsconfig[$config['priority']][] = array(
                            'config' => $config,
                            'entry' => $entry,
                        );
                    }
                }
            }
            for($i = 100; $i >= 1; $i--){
                $priority = $i;
                if(is_array($pluginsconfig[$priority])){
                    foreach($pluginsconfig[$priority] as $k => $v){
                        $mainClass = $v['config']['main'];
                        $pluginPath = str_replace('\\', '/', $mainClass);
                        $mainPath = $this->path . '/' . $v['entry'] . '/src/' . $pluginPath .'.php';
                        if(!file_exists($mainPath) || !is_file($mainPath)){
                            $this->System->getLogger()->newCrashDump('无法加载插件', 'Cannot load plugins '.$v['config']['name'].' due to the incorrect or non existed path of the plugin main file, main file should be '.$mainPath);
                            exit('YunTaIDC:加载插件出错'.$mainPath);
                        }else{
                            require_once($mainPath);
                            $this->PluginClass[$v['config']['name']] = $v['config']['main'];
                            if(!empty($v['config']['page'])){
                                $PagePath = str_replace('\\', '/', $v['config']['page']);
                                $PagePath = $this->path . '/' . $v['entry'] . '/src/' . $PagePath . '.php';
                                if(!file_exists($PagePath) || !is_file($PagePath)){
                                    $this->System->getLogger()->newCrashDump('无法加载插件', 'Cannot load plugins '.$v['config']['name'].'\'s page due to the incorrect or non existed path of the plugin page file, main file should be '.$PagePath);
                                    exit('YunTaIDC:加载插件出错'.$PagePath);
                                }else{
                                    require_once($PagePath);
                                    $this->PluginPage[$v['config']['name']] = $v['config']['page'];
                                }
                            }
                            if(empty($config['type'])){
                                $config['type'] = 'FUNCTION';
                            }
                            $this->PluginType[$v['config']['type']][] = $v['config']['name'];
                            $this->PluginPath[$v['config']['name']] = $this->path . '/'. $v['entry'];
                        }
                    }
                }
            }
        }else{
            $this->System->getLogger()->newCrashDump('无法加载插件', 'Cannot load plugins due to the error open can\'t opening the directory of the plugins,please check if there is enough permission');
            exit('YunTaIDC:加载插件出错');
        }
    }

    public function loadPlugins(){
        $this->loadPluginFiles();
        foreach ($this->PluginClass as $k => $v){
            $pluginDataFolder = $this->dataFolder .$k;
            if(file_exists($pluginDataFolder) && !is_dir($pluginDataFolder)){
                $this->System->getLogger()->newCrashDump('无法加载插件', 'Projected plugin '.$k.' data folder cause to an error.');
                exit('YunTaIDC:加载插件出错');
            }
            if(!file_exists($pluginDataFolder)){
                mkdir($pluginDataFolder, 0755, true);
            }
            try {
                $plugin = new $v($this->System, $pluginDataFolder, $this->PluginPath[$k], $k);
                $plugin->onLoad();
                $this->Plugins[$k] = $plugin;
            } catch (Error $e){
                
            }
        }
        return $plugins;
    }
    
    public function loadEvent($name, $event){
        foreach ($this->Plugins as $k => $v) {
            try {
                if(method_exists($v, $name)){
                    $v->$name($event);
                }
            } catch (Exception $e) {
                $this->System->getLogger()->newCrashDump('插件运行出错', 'ErrorFile'.$e->getFile().'\n\r ErrorLine'.$e->getFile().'\n\r ErrorMessage:'.$e->getMessage());
                exit('YunTaIDC:插件运行出错');
            }
        }
    }
    
    public function loadEventByPlugin($name, $event, $pluginName){
        try {
            if(method_exists($this->Plugins[$pluginName], $name)){
                return $this->Plugins[$pluginName]->$name($event);
            } else {
                return false;
            }
        } catch (Exception $e) {
            $this->System->getLogger()->newCrashDump('插件运行出错', 'ErrorFile'.$e->getFile().'\n\r ErrorLine'.$e->getFile().'\n\r ErrorMessage:'.$e->getMessage());
            exit('YunTaIDC:插件运行出错');
        }
    }
    
    public function getPlugins($type = "all"){
        switch ($type) {
            case 'SERVER':
                return $this->PluginType['SERVER'];
            break;
            case 'PAYMENT':
                return $this->PluginType['PAYMENT'];
            break;
            case 'FUNCTION':
                return $this->PluginType['FUNCTION'];
            break;
            default:
                foreach($this->Plugins as $k => $v){
                    $return[] = $k;
                }
                return $return;
            break;
        }
    }
    
    public function PluginLoaded($name){
        if(empty($this->Plugins[$name])){
            return false;
        }else{
            return true;
        }
    }
    
    public function getPlugin($name){
        if(empty($this->Plugins[$name])){
            return false;
        }else{
            return $this->Plugins[$name];
        }
    }
    
    public function PageRegistered($Plugin){
        if(!empty($this->PluginPage[$Plugin])){
            return true;
        }else{
            return false;
        }
    }
    
    public function loadPluginPage($Plugin, $System){
        if($this->PageRegistered($Plugin)){
            $PageClass = $this->PluginPage[$Plugin];
            $dataFolder = $this->dataFolder .$Plugin;
            $sourceFolder = $this->PluginPath[$Plugin];
            return new $PageClass($System, $dataFolder, $sourceFolder);
        }else{
            return false;
        }
    }
    
    public function getPluginPath($plugin){
        return $this->PluginPath[$plugin];
    }

}

?>