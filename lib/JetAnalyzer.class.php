<?php

class Jet_Analyzer {
    private static $depMap = array();
    private static $loaders = array();

    /**
     * 增加一份依赖关系图，用于分析指定模块的依赖关系
     * @param $depConf {Array} 依赖关系数组
     */
    public static function addDepMap($depConf) {
        if (!is_array($depConf)) {
            return;
        }
        self::$depMap = array_merge(self::$depMap, $depConf);
    }

    /**
     * 注册一个Jet的loader，用于处理模块ID，默认情况，会注册一个默认loader
     * @param $callback {callable} 处理模块Id的函数
     */
    public static function registerLoader(callable $callback) {
        if (empty($callback) || in_array($callback, self::$loaders)) {
            return;
        }
        array_push(self::$loaders, $callback);
    }

    /**
     * 运行所有的loader
     * @param $id {string} 要处理模块ID
     */
    public static function runLoaders($id) {
        $ctx = array(
            'id' => $id
        );
        foreach (self::$loaders as $key => $loader) {
            call_user_func($loader, $ctx);
        }
    }

    /**
     * 分析指定模块id依赖
     * @param $id {string} 模块ID
     * @param $outDepObj {Array} 当前已找到的依赖关系，用引用方式，避免传值引用消耗
     * @return void
     */
    public static function analyzeDependency($id, &$outDepObj) {
        if (!isset(self::$depMap[$id])) {
            // 执行所有的loader，去查找有该id的依赖关系表，并添加到总依赖关系表里
            self::runLoaders($id);

            // 执行完loader依然没有
            if (!isset(self::$depMap[$id])) {
                $outDepObj[$id] = array('p' => $id . '.js'); // 默认模块的path就是id + .js
                return;
            }
        }

        // 非数组，退出，id的直接依赖是数组形式的
        if (!is_array(self::$depMap[$id])) {
            return;
        }

        // 获取该模块的直接依赖
        $node = self::$depMap[$id];

        // 处理同步依赖
        if (isset($node['d']) && !empty($node['d'])) {
            foreach ($node['d'] as $i => $nextid) {
                if (!array_key_exists($nextid, $outDepObj)) {
                    self::analyzeDependency($nextid, $outDepObj);
                }
            }
        }

        // 处理异步依赖，和同步依赖一样
        if (isset($node['a']) && !empty($node['a'])) {
            foreach ($node['a'] as $i => $nextid) {
                if (!array_key_exists($nextid, $outDepObj)) {
                    self::analyzeDependency($nextid, $outDepObj);
                }
            }
        }

        $outDepObj[$id] = self::$depMap[$id];
    }

    /**
     * 分析并获取指定模块的依赖关系
     * @param $id {string} 模块ID
     * @return {Array}
     */
    public static function analyze($id) {
        $outDepObj = array();
        self::analyzeDependency($id, $outDepObj);
        return $outDepObj;
    }
}
