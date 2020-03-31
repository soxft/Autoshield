<?php
// +-------------------------------------------------------------------
// | cloudflare自动开盾
// +-------------------------------------------------------------------
// | Copyright (c) 2020 xcsoft(http://xsot.cn) All rights reserved.
// +-------------------------------------------------------------------
// | Author: xcsoft(contact@xcosft.top)
// +-------------------------------------------------------------------
class bt_main {
    public function __construct() {
        $file = new file;
        $this -> setting = $file -> read("config/setting.json");
        $this -> safe = $file -> read("config/safe.json");
        $data = json_decode($this->safe,true);
        $this->waittime = $data['waittime'];
        $this->sleeptime = $data['sleeptime'];
        $data = json_decode($this->setting,true);
        $this->bturl = $data['bturl'];
        $this->btkey = $data['btkey'];
        $this->cfkey = $data['cfkey'];
        $this->cfemail = $data['cfemail'];
    }

    //返回前端
    public function get_index() {
        $disabled = explode(',', ini_get('disable_functions'));
        $ifuse = !in_array('exec_shell', $disabled);
        //函数禁用检测
        if(!$ifuse)
        {
            $html = "<h1 style='text-align:center;margin-top:30%;'>该插件需要exec_shell函数支持</h1>";
            return $html;   
        }
        $output = shell_exec('ps -C php -f');
        if (strpos($output, "firewall.php") === false) {
            $result = "<span>关闭</span><span style='color: red; margin-left: 3px;' class='glyphicon glyphicon-pause'></span> </p>
        <div class='sfm-opt'>
        <button class='btn btn-default btn-sm' onclick='start()'>启动</button>";
        } else {
            $result = "<span>开启</span><span style='color: #20a53a; margin-left: 3px;' class='glyphicon glyphicon glyphicon-play'></span> </p>
        <div class='sfm-opt'>
        <button class='btn btn-default btn-sm' onclick='stop()'>停止</button>";
        }
            $html = "
        <div class='soft-man-con bt-form'>
            <p class='status'>当前状态：$result
        <button class='btn btn-default btn-sm' onclick='restart()')'>重启</button>
        </div>
    </div>";
        return $html;
    }

    public function get_setting() {

        $html = "
            <div class='line'><span class='tname'>CF API密钥</span>
                <div class='info-r'>
                    <input id='cfkey' placeholder='请输入Cloudflare global API 密钥' type='text' class='bt-input-text mr5' value='$this->cfkey' style='width: 430px;'>
                    <br />
                    <span style='color: rgb(153, 153, 153);'>请输入Cloudflare global 密钥，获取方式详见关于界面</span>
                </div>
            </div>
            <div class='line'><span class='tname'>CF 绑定邮箱</span>
                <div class='info-r'>
                    <input id='cfemail' placeholder='请输入您的Cloudflare绑定的邮箱地址' type='text' class='bt-input-text mr5' value='$this->cfemail' style='width: 430px;'>
                    <br />
                    <span style='color: rgb(153, 153, 153);'>请输入您的Cloudflare绑定的邮箱地址</span>
                </div>
            </div>
            <div class='line'>
            <span class='tname'></span>
                <div class='info-r'>
                    <button onclick='save_data_setting()' class='btn btn-success btn-sm'>保存配置</button>
                </div>
            </div>
        </div>
       ";
        return $html;
    }

    public function get_safe() {

        $html = "
            <div class='line'><span class='tname'>等待时间</span>
                <div class='info-r'>
                    <input id='waittime' placeholder='300' type='number' class='bt-input-text mr5' value='$this->waittime' style='width: 100px;'> <span style='color: rgb(153, 153, 153);'>* 在被攻击后,负载恢复正常时关闭5秒盾的等待时间(单位:秒)</span>
                </div>
            </div>
            <div class='line'><span class='tname'>检测周期</span>
                <div class='info-r'>
                    <input id='sleeptime' placeholder='10' type='number' class='bt-input-text mr5' value='$this->sleeptime' style='width: 100px;'> <span style='color: rgb(153, 153, 153);'>* 每多少秒检测一次服务器负载(单位:秒)</span>
                </div>
            </div>
            <div class='line'>
                <span class='tname'></span>
                    <div class='info-r'>
                        <button onclick='save_data_safe()' class='btn btn-success btn-sm'>保存配置</button>
                    </div>
                </div>
            </div>
       ";
        return $html;
    }
    
    public function get_runlog() {
        $file = new file;
        $underattacklog = $file -> read("config/run.log");
        $html = "
            <textarea readonly='' style='margin: 0px;width: 560px;height: 520px;background-color: #333;color:#fff; padding:0 5px' id='error_log'>$underattacklog</textarea>
       ";
        return $html;
    }
    
    public function get_errorlog() {
        $file = new file;
        $errorlog = $file -> read("config/error.log");
        $html = "
            <textarea readonly='' style='margin: 0px;width: 560px;height: 520px;background-color: #333;color:#fff; padding:0 5px' id='error_log'>$errorlog</textarea>
       ";
        return $html;
    }

    public function get_about() {
        $html = "
            <div class='plugin_about'>
                <img src='https://cdn.jsdelivr.net/gh/soxft/cdn@master/team/team.png' width='500px' alt='XUSOFT'>
                <p><b>插件名称：</b>cloudflare自动开盾</p>
                <p><b>使用说明：</b>当服务器负载超过安全负载时,自动启动Cloudflare 5秒盾,来防止服务器宕机</p>
                <p><b>官网：</b><a class='btlink' href='http://xsot.cn' target='_blank'>http://xsot.cn</a></p>
                <p><b>插件作者：</b>xcsoft</p>
                <p><b>Telegram：</b><a class='btlink' href='https://t.me/xcsoft'>@xcsoft</a></p>
                <p><b>QQ群：</b><a class='btlink' href='https://jq.qq.com/?_wv=1027&k=52w0Rgc'>608265912</a></p>
                <p><b>Email：</b><a class='btlink' href='mailto:contact@xcsoft.top'>contact@xcsoft.top</a></p>
            </div>";
        return $html;
    }

    //处理前端
    public function setData() {
        $data = array(
            "cfkey" => _post('cfkey'),
            "cfemail" => _post('cfemail')
        );
        $file = new file;
        $json = json_encode($data);
        $file->write('config/setting.json',$json);
        return 200;
    }

    public function setSafe() {
        if(floor(_post('waittime')) !== (double)_post('waittime') || floor(_post('sleeptime')) !== (double)_post('sleeptime') || (double)_post('sleeptime') < 1 || (double)_post('waittime') < 1)
        {
            //判断是否为整数
            return "等待周期和检测时间只能为大于1的整数!";
        }
        $data = array(
            "waittime" => _post('waittime'),
            "sleeptime" => _post('sleeptime'),
        );
        $file = new file;
        $json = json_encode($data);
        $file->write('config/safe.json',$json);
        return 200;
    }
    
    public function start() {
        $output = shell_exec('ps -C php -f');
        if (!strpos($output, "firewall.php") === false) {
            return 1001;
        }else{
            shell_exec('cd ' . PLU_PATH . ' > /dev/null 2>&1 &');
            shell_exec('php firewall.php > /dev/null 2>&1 &');
            $file = new file;
            $time = date("Y-m-d H:i:s");
            $file->write_log("config/run.log","[start]$time | 服务启动");
            return 200;
        }
    }
    
    public function stop() {
        $file = new file;
        $time = date("Y-m-d H:i:s");
        $file->write_log("config/run.log","[stop]$time | 服务关闭");
        $output = shell_exec('pgrep -f firewall.php');
        $data = explode("\n",$output);
        $num = count($data);
        for($i=0;$i<=$num-2;$i++)
        {
            shell_exec('kill ' . $data[$i]);
        }
        return 200;
    }
    
    public function restart() {
        $output = shell_exec('pgrep -f firewall.php');
        $data = explode("\n",$output);
        $num = count($data);
        for($i=0;$i<=$num-2;$i++)
        {
            shell_exec('kill ' . $data[$i]);
        }
        shell_exec('cd ' . PLU_PATH . ' > /dev/null 2>&1 &');
        shell_exec('php firewall.php > /dev/null 2>&1 &');
        return 200;
    }
}

//文件处理类
class file {
    public function write($filepath,$content) {
        file_put_contents($filepath, $content);
    }
    public function read($filepath) {
        if (file_exists($filepath)) {
            $str = file_get_contents($filepath);
            return $str;
        } else {
            return "请输入内容";
        }
    }
    public function write_log($filepath,$content) {
        file_put_contents($filepath, $content.PHP_EOL, FILE_APPEND);
    }
}
?>