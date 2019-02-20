<?php

namespace Debugger;


define(__NAMESPACE__.'_DEBUG_PASS', 'miaosha_debug'); // 请定义自己的密钥
define(__NAMESPACE__.'_IS_DEBUG', false); // 是否开启调试状态
define(__NAMESPACE__.'_ERROR_LEVEL', E_ALL); // 异常信息等级


define(__NAMESPACE__.'DEBUG_LOG_ERROR', 'ERROR');
define(__NAMESPACE__.'DEBUG_LOG_WARNING', 'WARNING');
define(__NAMESPACE__.'DEBUG_LOG_INFO', 'INFO');


class Debugger {

    private $logId;
    private $timeList;
    private $logList;
    private $httpList;
    private $redisList;
    private $mysqlList;
	private $cacheList;

    private static $instance = false;

    /**
     * 私有方法，防止直接实例化
    */
    private function __construct() {}

    /**
     * 初始化调试日志操作类，没有经过初始化的后续调试代码都不会生效
     */
    public static function _init() {
        if (!self::$instance) {
            self::$instance = new self();
            self::$instance->logId = microtime();
        }
    }


    /**
     *
     * 配置相关设置
     *
    */
    public static function _enable(){


        if(
            (defined(__NAMESPACE__.'_IS_DEBUG') && __NAMESPACE__._IS_DEBUG) ||
            (isset($_REQUEST['__debug']) && strpos($_REQUEST['__debug'], _DEBUG_PASS) !== false)
        ){
            //    $_REQUEST['__debug'] = _DEBUG_PASS + 1 (2 数字表示级别 )
            $debug_level = intval(substr($_REQUEST['__debug'], -1));
            if ($debug_level > 0) {
                define(__NAMESPACE__.'DEBUG_LEVEL', $debug_level );
            } else {
                define(__NAMESPACE__.'DEBUG_LEVEL', 1);
            }
            //Debug模式将错误打开
            ini_set('display_errors', true);
            //设置错误级别
            error_reporting(__NAMESPACE__._ERROR_LEVEL);
            //开启ob函数
            ob_start();
            //Debug开关打开
            self::_init();
            //注册shutdown函数用来Debug显示
            register_shutdown_function(array('Debugger', '_show'));
        } else {
            define(__NAMESPACE__.'DEBUG_LEVEL', 0);
        }
    }



    /**
     * 记录时间，方便调试程序执行逻辑和每一步的执行效率
     */
    public static function _time($label, $handler = false) {
        if (self::$instance === false) {
            return;
        }
        self::$instance->timeList[] = array($label, microtime(), $handler);
    }

    /**
     * 记录运行时的调试信息，分为 DEBUG_LOG_INFO 和 DEBUG_LOG_ERROR，DEBUG_LOG_INFO 只有在全量输出调试信息的时候才会输出
     */
    public static function _log($label, $info, $level=DEBUG_LOG_INFO, $handler = false) {
        if (self::$instance === false || (__NAMESPACE__.DEBUG_LEVEL < 2 && $level == DEBUG_LOG_INFO)) {
            return;
        }
        self::$instance->logList[] = array($label, $info, $level, $handler);
    }

    /**
     * 记录运行时的http请求
     */
    public static function _http($label, $params, $config, $mtime1, $mtime2, $data = null, $handler = false) {
        if (self::$instance === false) {
            return;
        }
        if (DEBUG_LEVEL === 1) {
            self::$instance->httpList[] = array($label, json_encode($params), json_encode($config), $mtime1, $mtime2, null, $handler);
        } else {
            self::$instance->httpList[] = array($label, json_encode($params), json_encode($config), $mtime1, $mtime2, $data, $handler);
        }
    }

    /**
     * 记录运行时的redis请求
     */
    public static function _redis($label, $params, $config, $mtime1, $mtime2, $data = null, $handler = false) {
        if (self::$instance === false) {
            return;
        }
        if (DEBUG_LEVEL === 1) {
            if ('setex' == $label) {    // 过滤掉内容块，避免日志太多
                $params[2] = null;
            }
            self::$instance->redisList[] = array($label, json_encode($params), json_encode($config), $mtime1, $mtime2, null, $handler);
        } else {
            self::$instance->redisList[] = array($label, json_encode($params), json_encode($config), $mtime1, $mtime2, $data, $handler);
        }
    }

    /**
     * 记录运行时的mysql请求
     */
    public static function _mysql($label, $params, $config, $mtime1, $mtime2, $data = null, $handler = false) {
        if (self::$instance === false) {
            return;
        }
        if (DEBUG_LEVEL === 1) {
            self::$instance->mysqlList[] = array($label, json_encode($params), json_encode($config), $mtime1, $mtime2, null, $handler);
        } else {
            self::$instance->mysqlList[] = array($label, json_encode($params), json_encode($config), $mtime1, $mtime2, $data, $handler);
        }
    }

	/**
     * 记录运行时的memcache请求
     */
    public static function _cache($label, $params, $config, $mtime1, $mtime2, $data = null, $handler = false) {
        if (self::$instance === false) {
            return;
        }
        if (DEBUG_LEVEL === 1) {
            self::$instance->cacheList[] = array($label, json_encode($params), json_encode($config), $mtime1, $mtime2, null, $handler);
        } else {
            self::$instance->cacheList[] = array($label, json_encode($params), json_encode($config), $mtime1, $mtime2, $data, $handler);
        }
    }

    /**
     * 输出日志
     */
    public static function _show() {
        if (self::$instance === false) {
            return;
        }
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            // 界面上可视化模式输出内容
            self::$instance->showViews();
        } else {
            self::$instance->writeLogs();
        }
    }

    /**
     * 是否有可视化界面输出，HTML代码直接返回到浏览器
     */
    public static function _is_show_view() {
        if (self::$instance && isset($_SERVER['HTTP_USER_AGENT'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 将microtime的时间字符串转换为float型的毫秒时间
     */
    private function _floatMicrotime($mt) {
        if (strpos($mt, ' ')) {
            list($ms, $m) = explode(' ', $mt);
            return ($m + $ms) * 1000;
        } else {
            return floatval($mt) * 1000;
        }
    }

    /**
     * 计算两个microtime时间的间隔时间
     * @param $m1 开始时间
     * @param $m2 结束时间
     * @param $round 保留小数位
     */
    private function _intervalTime($m1, $m2, $round = 3) {
        return round(($this->_floatMicrotime($m2) - $this->_floatMicrotime($m1)), $round);
    }

    /**
     * 将调试信息生成可视化的HTML代码
     */
    private function showViews() {
        $showTime = microtime();
        $output = array();
        $output[] = "\n";
        $output[] = '<ul>';
        $output[] = '<li><strong style="font-size:18px;">DebugLog showViews.total process time is ' . $this->_intervalTime($this->logId, $showTime) . 'ms</strong></li>';
        if ($this->timeList) {
            $total_num = count($this->timeList);
            $output[] = '<li><strong style="font-size:18px;">TimeList total count is ' . count($this->timeList) . ', log time is ' . $this->_intervalTime($this->logId, $this->timeList[$total_num - 1][1]) . '</strong></li>';
            $lasttime = $this->logId;
            $output[] = '<li>0.000 : start debug log ' . $lasttime . '</li>';
            foreach ($this->timeList as $info) {
                $lasttime2 = $info[1];
                $output[] = '<li>'. $this->_intervalTime($lasttime, $lasttime2) . ' : ' . implode("\t", $info) . '</li>';
                $lasttime = $lasttime2;
            }
        }
        if ($this->logList) {
            $output[] = '<li><strong style="font-size:18px;">LogList total count is ' . count($this->logList) . '</strong></li>';
            foreach ($this->logList as $info) {
                $output[] = '<li>' . implode("\t", $info) . '</li>';
            }
        }
        if ($this->httpList) {
            $current = count($output);
            $total_time = 0;
            $output[] = null;
            $max_num = array();
            $multi_num = array();
            foreach ($this->httpList as $info) {
                $intval = $this->_intervalTime($info[3], $info[4]);
                $multi_flag = @json_decode($info[2],true);
                if(isset($multi_flag) && isset($multi_flag['is_multi']) && $multi_flag['is_multi']==1)
                {
                    $multi_str = strval($multi_flag['multi_num']);

                    if($intval > $max_num[$multi_str])
                    {
                        $max_num[$multi_str] = $intval;

                        if(!in_array($multi_str, $multi_num))
                        {
                            $multi_num[] = $multi_str;
                        }
                    }
                }
                else
                {
                    $total_time += $intval;
                }
                if ($info[5] && is_array($info[5])) {
                    $info[5] = json_encode($info[5]);
                }

                $output[] = '<li>'. $intval .' : ' . implode("\t", $info) . '</li>';
            }

            if(!empty($multi_num ))
            {
                foreach($multi_num as $val)
                {
                    $total_time += $max_num[$val];
                }
            }

            $output[$current] = '<li><strong style="font-size:18px;">HttpList total count is ' . count($this->httpList) . ', total time is ' . $total_time . '</strong></li>';

        }
        if ($this->redisList) {
            $current = count($output);
            $total_time = 0;
            $output[] = null;
            foreach ($this->redisList as $info) {
                $intval = $this->_intervalTime($info[3], $info[4]);
                $total_time += $intval;
                if ($info[5] && is_array($info[5])) {
                    $info[5] = json_encode($info[5]);
                }
                $output[] = '<li>'. $intval .' : ' . implode("\t", $info) . '</li>';
            }
            $output[$current] = '<li><strong style="font-size:18px;">RedisList total count is ' . count($this->redisList) . ', total time is ' . $total_time . '</strong></li>';
        }
        if ($this->mysqlList) {
            $current = count($output);
            $total_time = 0;
            $output[] = null;
            foreach ($this->mysqlList as $info) {
                $intval = $this->_intervalTime($info[3], $info[4]);
                $total_time += $intval;
                if ($info[5] && is_array($info[5])) {
                    $info[5] = json_encode($info[5]);
                } elseif (!$info[5]) {
                    $info[5] = '';
                }
                $output[] = '<li>'. $intval .' : ' . implode("\t", $info) . '</li>';
            }
            $output[$current] = '<li><strong style="font-size:18px;">MysqlList total count is ' . count($this->mysqlList) . ', total time is ' . $total_time . '</strong></li>';
        }
        if ($this->cacheList) {
            $current = count($output);
            $total_time = 0;
            $output[] = null;
            foreach ($this->cacheList as $info) {
                $intval = $this->_intervalTime($info[3], $info[4]);
                $total_time += $intval;
                if ($info[5] && is_array($info[5])) {
                    $info[5] = json_encode($info[5]);
                }
                $output[] = '<li>'. $intval .' : ' . implode("\t", $info) . '</li>';
            }
            $output[$current] = '<li><strong style="font-size:18px;">CacheList total count is ' . count($this->cacheList) . ', total time is ' . $total_time . '</strong></li>';
        }
        $output[] =  '</ul>';
        echo implode("\n", $output);
    }

    /**
     * 将调试日志写入到本地文件中，使用JSON格式保存为一行
     */
    public function writeLogs() {
        $showTime = microtime();

         if (!defined('DEBUG_LOG_PATH')) {
            define('DEBUG_LOG_PATH', '/var/log/');
        }

        $serverList = array(
            'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'],
            'REQUEST_URI' => $_SERVER['REQUEST_URI'],
            'REMOTE_ADDR:PORT' => $_SERVER['REMOTE_ADDR'] . ':' . $_SERVER['REMOTE_PORT'],
        );
        $datalist = array(
            'logId'=>$this->logId,
            'logTime'=>$showTime,
            'timeList'=>$this->timeList,
            'logList'=>$this->logList,
            'httpList'=>$this->httpList,
            'redisList'=>$this->redisList,
            'mysqlList'=>$this->mysqlList,
            'server'=>$serverList,
            );
        $str = json_encode($datalist);
        $str = str_replace("\n", ' ', $str);
        $str .= "\n";
        $file_path = DEBUG_LOG_PATH . 'discuz_debug.log';
        if($fd = @fopen($file_path, 'a')) {
            fputs($fd, $str);
            fclose($fd);
        }
    }

    /**
     * 将消息输出到指定的文件
     * 默认 define('DEBUG_LOG_PATH', '/home/qiku/system/log/php/today/')
     * @param $msg 消息内容
     * @param string $file 日志文件名称，默认是 discuz_php.log
     */
    public static function writeDebugLog($msg, $file='discuz_php.log') {
        $dtime = date('Y-m-d H:i:s');
        if (!defined('DEBUG_LOG_PATH')) {
            $default_path = '/var/log/';
            if (file_exists($default_path)) {
                define('DEBUG_LOG_PATH', $default_path);
            } else {
                define('DEBUG_LOG_PATH', '');
            }
        }
//        $str_cookie = json_encode($_COOKIE);
		$str_cookie = 'no cookie';
        $str_server = json_encode(array($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']));
        $str = "[$dtime]||$msg||$str_cookie||$str_server\n";
        $file_path = DEBUG_LOG_PATH . $file;
        if($fd = @fopen($file_path, 'a')) {
            fputs($fd, $str);
            fclose($fd);
        }
    }

	/**
	 * 通过PHP的 debug_backtrace 可以详细的查看到方法调用的细节情况
	 */
	public static function writeBacktrace($deep=3, $all=false) {
		$result = array();
		$trace = debug_backtrace();
		unset($trace[0]);
		if ($deep < count($trace)) {
			for ($i = 1; $i <= $deep; $i++) {
				$info = $trace[$i];
				if (isset($info['object']) && $all === false) {
					unset($info['object']);
				}
				$result[] = $info;
			}
		} elseif ($all === false) {
			foreach ($trace as $info) {
				if (isset($info['object'])) {
					unset($info['object']);
				}
				$result[] = $info;
			}
		} else {
			$result = $trace;
		}
		self::writeDebugLog(json_encode($result), 'backtrace.log');
	}

}

