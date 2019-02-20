#### Debugger日志操作
 > DEBUG_LEVEL=0的时候不会在后端运行，
 > DEBUG_LEVEL=1的时候会记录错误、警告信息以及资源调用的耗时汇总统计，
 > DEBUG_LEVEL=2的时候，会记录全部的数据
 > 如果在参数列表中出现 __DEBUG_LEVEL ，则会强制覆盖 DEBUG_LEVEL 的值
 
 > 功能列表如下： 
 > 1 time 性能探针，计算运行的步骤以及每一步的执行效率;
 > 2 log 日志记录，把每一个日志信息记录下来;
 > 3 http 接口调用的记录以及耗时的汇总统计;
 > 4 redis redis调用的记录以及耗时的汇总统计;
 > 5 mysql mysql调用的记录以及耗时的汇总统计;
 > 6 cache memcache调用的记录以及耗时的汇总统计;
 
- 安装 `composer require php-debug/debugger`
- 初始化 `require(__DIR__ . '/../vendor/autoload.php');`
- 使用手册
    - 调用 `\Debugger\Debugger::_enable();`
    - 调试探针，初始化完成，页面开始执行`\Debugger\Debugger::_time(__FILE__.', start page');`
    - 访问：http://localhost/?__debug=自定义密钥值
    - MySQL调用
    ```
    $mtime1 = microtime();
        数据库连接操作...
    $mtime2 = microtime();
     \Debugger\Debugger::_mysql(
        'connect',   //标签
        [],   //额外参数，数组形式
        array('host' => $this->settings['host'], 'dbname' => $this->settings['dbname']), //主要参数输出 
        $mtime1, $mtime2 // 开始时间 结束时间
     );
    ```
    - _log、_http、_redis、_cache同理
    - 将消息输出到指定的文件 `\Debugger\Debugger::writeDebugLog($content,$file)`
    - debug_backtrace `\Debugger\Debugger::writeBacktrace()`



