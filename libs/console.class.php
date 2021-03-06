<?php
/**
 * 控制台
 * User: Dean.Lee
 * Date: 16/9/26
 */

namespace Root;

class Console {

    Static Private $title = null;
    Static Private $type = null;
    Static Private $other_data = [];
    Const errortype = [
        E_ERROR              => 'Error',
        E_WARNING            => 'Warning',
        E_PARSE              => 'Parsing Error',
        E_NOTICE             => 'Notice',
        E_CORE_ERROR         => 'Core Error',
        E_CORE_WARNING       => 'Core Warning',
        E_COMPILE_ERROR      => 'Compile Error',
        E_COMPILE_WARNING    => 'Compile Warning',
        E_USER_ERROR         => 'User Error',
        E_USER_WARNING       => 'User Warning',
        E_USER_NOTICE        => 'User Notice',
        E_STRICT             => 'Runtime Notice',
        E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
    ];

    /**
     * 封装错误和异常处理
     */
    Static Public function load()
    {
        //error_reporting(0);
        //封装错误处理
        set_error_handler("Root\\Console::error");
        //封装异常处理
        set_exception_handler("Root\\Console::exception");
    }

    Static Public function error($errno, $errmsg, $filename, $linenum)
    {
        $data = [
            'datetime' => date("Y-m-d H:i:s"),
            'errornum' => $errno,
            'errortype' => self::errortype[$errno],
            'errormsg' => $errmsg,
            'scriptname' => $filename,
            'linenum' => $linenum
        ];
        self::$type = $errno;
        if(function_exists('debug_backtrace')){
            $backtrace = debug_backtrace();
            if(!empty($backtrace)){
                array_shift($backtrace);
                $data['errcontext'] = [];
                foreach($backtrace as $i=>$l){
                    if(!isset($l['file']) || !isset($l['line']))continue;
                    $data['errcontext'][] = [
                        'num' => $i,
                        'action' => (isset($l['class'])?$l['class']:'') . (isset($l['type'])?$l['type']:'') . (isset($l['function'])?$l['function']:''),
                        'file' => $l['file'],
                        'line' => $l['line']
                    ];
                }
            }
        }
        self::output($data);
    }

    Static Public function exception($exception)
    {
        $data = [
            'datetime' => date("Y-m-d H:i:s"),
            'errortype' => self::errortype[$exception->getCode()],
            'errormsg' => $exception->getMessage(),
            'scriptname' => $exception->getFile(),
            'linenum' => $exception->getLine()
        ];
        self::$type = $exception->getCode();
        if(function_exists('debug_backtrace')){
            $backtrace = debug_backtrace();
            array_shift($backtrace);
            $data['errcontext'] = [];
            foreach($backtrace as $i=>$l){
                $data['errcontext'][] = [
                    'num' => $i,
                    'action' => ($l['class']?:'') . ($l['type']?:'') . ($l['function']?:''),
                    'file' => $l['file'],
                    'line' => $l['line']
                ];
            }
        }
        self::output($data);
    }

    /**
     * 错误或异常输出
     */
    Static Public function output($data)
    {
        if(self::$title !== null){
            $data = array_merge(['title' => self::$title], $data);
            self::$title = null;
        }
        if(!empty(self::$other_data)){
            foreach(self::$other_data as $key => $val){
                if(is_string($key))$data[$key] = $val;
            }
        }

        $mod_name = \Root::$user ? \Root::$user->mod_name : 'common';
        if(C('LOG.errortype') == 'xml')
            L(array2xml($data) . "\n\n", 'error', $mod_name);
        else
            L(json_encode($data) . PHP_EOL, 'error', $mod_name);
        //如果是E_USER_ERROR/E_USER_WARNING级错误,则自动关闭服务
        if(self::$type == E_USER_ERROR){
            \Root::$serv->shutdown();
            die("由于致命错误导致服务器停止,请查询系统日志!\n");
        }
    }

    /**
     * 异常输出
     * @param string $title 错误标识
     * @param string $content 错误内容
     */
    Static Public function input($title, $content = null, $type = null)
    {
        if(is_array($content) && !empty($content)){
            $arr = $content;
            if(isset($arr['content'])){
                $content = $arr['content'];
                unset($arr['content']);
            }else{
                $content = $arr[0];
                unset($arr[0]);
            }
            self::$other_data = $arr;
        }
        if(in_array($type, [E_USER_NOTICE, E_USER_ERROR, E_USER_WARNING])){
            if($content === null)
                trigger_error($title, $type);
            else{
                self::$title = $title;
                trigger_error($content, $type);
            }
        }else{
            if($content === null)
                throw new \Exception($title);
            else{
                self::$title = $title;
                throw new \Exception($content);
            }
        }
    }

}