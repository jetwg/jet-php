<?php
if (!defined('JET_ROOT_DIR')) {
    define('JET_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
}

if (!defined('JET_LIB_DIR')) {
    define('JET_LIB_DIR', dirname(__FILE__));
}

require_once(JET_LIB_DIR . DIRECTORY_SEPARATOR . 'Jet.class.php');
require_once(JET_LIB_DIR . DIRECTORY_SEPARATOR . 'Analyzer.php');

/**
 * Jet类封装，单实例
 * @class
 */
class Jet_Singleton {
    private static $_instance = NULL;
    private static $_opt = NULL;

    /**
     * 静态工厂方法，返还此类的唯一实例， 也可以自己new，维护实例
     */
    public static function getInstance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new Jet();
        }
        return self::$_instance;
    }

    /**
     * 默认的loader函数，用于找到模块ID的依赖关系图，在startPage传入moduleMap配置时，才会注册该loader
     */
    public static function defaultLoader($ctx)
    {
        $moduleMapDir = self::$_opt['moduleMapDir'];
        $moduleMap = self::$_opt['moduleMap'];
        $id = $ctx['id'];
        $confPath = '';
        foreach ($moduleMap as $module => $path) {
            // id以 $moduel开头
            if (strpos($id, $module) === 0 && !empty($path)) {
                $confPath = $path;
                break;
            }
        }

        if (!empty($confPath)) {
            $sep = DIRECTORY_SEPARATOR;
            $absPath = $moduleMapDir . "${sep}$confPath";
            if (file_exists($absPath)) {
                // todo: 依赖关系文件的格式需要确定，怎么和现有依赖关系合并
                // todo: 同时新增的依赖关系文件 和 现有的依赖关系 有重名ID，怎么合并
                $configs = require($absPath);
                self::addDepMap($configs);
            }
        }
    }

    /**
     * 初始化Jet，并传入配置项， 必须调用该方式，才能执行其它方法
     * @param $id {string}
     * @param $phpFilePath {string} 编译后的php文件路径
     */
    public static function startPage($opt = array()) {
        // todo，这个moduleMap怎么存储，存储到哪，谁来读取
        $defaultOpt = array(
            'moduleMapDir' => JET_ROOT_DIR . DIRECTORY_SEPARATOR . 'config',
            'moduleMap' => array()
        );
        self::$_opt = array_merge($defaultOpt, $opt);

        // 有moduleMap的话，默认注册一个loader
        if (!empty(self::$_opt['moduleMap'])) {
            self::registerLoader('Jet_Singleton::defaultLoader');
        }
    }

    /**
     * 增加一份依赖关系图，用于分析指定模块的依赖关系
     * @param $depConf {Array} 依赖关系数组
     */
    public static function addDepMap($depConf) {
        Jet_Analyzer::addDepMap($depConf);
    }

    /**
     * 注册一个Jet的loader，用于处理模块ID，默认情况，会注册一个默认loader
     * @param $callback {callable} 处理模块Id的函数
     */
    public static function registerLoader(callable $callback) {
        Jet_Analyzer::registerLoader($callback);
    }

    /**
     * 增加一个id的依赖关系
     * @param $id {string} js模块ID
     */
    public static function addDep($id) {
        $_instance = self::getInstance();
        $_instance->addDep($id);
    }

    /**
     * 输出当前所有ID依赖关系，同时重置当前实例的依赖关系为空
     * @return 依赖关系数组
     */
    public static function flushDeps() {
        $_instance = self::getInstance();
        return $_instance->flushDeps();
    }

    /**
     * 输出当前依赖关系
     * @return 依赖关系数组
     */
    public static function getDeps() {
        $_instance = self::getInstance();
        return $_instance->getDeps();
    }

    /**
     * 终止Jet处理，清空实例
     */
    public static function endPage() {
        self::$_instance = NULL;
    }
}
