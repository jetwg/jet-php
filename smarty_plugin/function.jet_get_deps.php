<?php
if (!class_exists("Jet_Singleton", false)) {
    if (!defined('JET_ROOT_DIR')) {
        define('JET_ROOT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
    }

    // jet核心库
    require(JET_ROOT_DIR . '/lib/JetSingleton.class.php');
}

function smarty_function_jet_get_deps($params,  Smarty_Internal_Template $template) {
    if (!isset($params["configVar"]) || empty($params["configVar"])) {
        throw new Exception("the param 'configVar' is required;");
    }

    $deps = Jet_Singleton::flushDeps();

    // 传出配置
    $template->assign($params["configVar"], $deps);
}
