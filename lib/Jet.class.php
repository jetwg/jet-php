<?php
if (!defined('JET_ROOT_DIR')) {
    define('JET_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
}

if (!defined('JET_LIB_DIR')) {
    define('JET_LIB_DIR', dirname(__FILE__));
}

require_once(JET_LIB_DIR . DIRECTORY_SEPARATOR . 'JetAnalyzer.class.php');


/**
 * Class Jet
 * @Class
 */
class Jet {
    private $deps = array();
    private $loaders = array();

    /**
     * 增加一个id的依赖关系
     * @param $id {string} js模块ID
     */
    public function addDep($id) {
        if (empty($id)) {
            return;
        }

        if (!empty($id) && !isset($this->deps[$id])) {
            $outDepObj = Jet_Analyzer::analyze($id);
            // 同步依赖
            $this->deps = array_merge($this->deps, $outDepObj);

            // 异步依赖作为新的入口
            // foreach ($outDepObj['asyncDeps'] as $i => $asyncId) {
            //     $this->addEntry($asyncId);
            // }
        }
    }

    /**
     * 输出当前所有ID依赖关系，同时重置当前实例的依赖关系为空
     * @return 依赖关系数组
     */
    public function flushDeps() {
        $deps = $this->deps;
        $this->deps = array();
        return $deps;
    }

    /**
     * 输出当前依赖关系
     * @return 依赖关系数组
     */
    public function getDeps() {
        return $this->deps;
    }
}
