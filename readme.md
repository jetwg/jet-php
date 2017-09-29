# jet-php
jet的php运行时库


## jet项目介绍
目前主流的前端工程化一般都是采用webpack、fis、gulp工具，并基于cmd或amd规范来组织javascript代码，实现了静态依赖分析和资源合并打包。
但是目前已有技术方案无法很好的做到非常理想的资源管理机制，如静态资源合并代码，在一次页面运行时里往往会有多余的模块加载和资源浪费，因此Jet将提供以下解决方案：
* 通用代码将通过包管理的方式来进行，并提供适合Web的AMD声明机制；
* 代码组织方式通过更严格的AMD子集标准来体现，并且是平铺式的文件粒度的Define；
* 页面的模块依赖方式将被动态的解析处理，结合应用场景完成智能化打包合并资源请求；


## 安装使用
### 基于Smarty的php版本
1. 安装jet-php
克隆jet-php到php后端工程里

2. Smarty插件安装

在jet-php下有`smarty_plugin`目录，该目录下有smarty插件，在php的$smarty实例注册该jet插件目录，以方便在smarty调用jet函数实现页面模块依赖注册
```
$smarty = new Smarty();
$smarty->addPluginsDir("{jet目录}/smarty_plugin");
```

3. 在smarty模板里调用函数
以下代码说明：

    * 调用jet_add_dep，增加a模块和e模块的为该页面依赖的模块
    * 调用jet_get_deps，获取到页面所有依赖模块的映射表，并赋给变量jet_deps
    * 将映射表保存到页面，供后续的jet-loader分析使用，实现动态打包

```
{%jet_add_dep id="a"%}
{%jet_add_dep id="e"%}


{%jet_get_deps configVar="jet_deps"%}

<script>
var jetOpt = {
    map: {%$jet_deps|@json_encode|escape:none|default: null%} || {}
};
<script>
```


3. 后端初始化
```
require_once('../jet-php/lib/JetSingleton.class.php');
$opt = array(
    "depHost" => "http://127.0.0.1:8111", // 仅仅后端没有ral模块时采用curl才生效，否则走ral配置，请注意
    "logid" => LOG_ID,
    'jetMapDir' => '/home/work/search/jet/public/jetmap',
);
Jet_Singleton::startPage($opt);
```
