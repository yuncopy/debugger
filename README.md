# debugger
调试日志操作组件
 * DEBUG_LEVEL=0的时候不会在后端运行，
 * DEBUG_LEVEL=1的时候会记录错误、警告信息以及资源调用的耗时汇总统计，
 * DEBUG_LEVEL=2的时候，会记录全部的数据
 * 如果在参数列表中出现 __DEBUG_LEVEL ，则会强制覆盖 DEBUG_LEVEL 的值
 * 功能列表如下：
 * 1 time 性能探针，计算运行的步骤以及每一步的执行效率
 * 2 log 日志记录，把每一个日志信息记录下来
 * 3 http 接口调用的记录以及耗时的汇总统计
 * 4 redis redis调用的记录以及耗时的汇总统计
 * 5 mysql mysql调用的记录以及耗时的汇总统计
 * 6 cache memcache调用的记录以及耗时的汇总统计
