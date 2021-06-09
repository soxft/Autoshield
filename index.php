<?php
// +-------------------------------------------------------------------
// | cloudflare自动开盾
// +-------------------------------------------------------------------
// | Copyright (c) 2020 xcsoft(http://xsot.cn) All rights reserved.
// +-------------------------------------------------------------------
// | Author: xcsoft(contact@xcosft.top)
// | Version: v1.6.
// | Date: 2021-06-08
// +-------------------------------------------------------------------
class bt_main {
    public function __construct() {
        #初始化判断
        if(!is_dir('./config/')) mkdir('./config', 0776);
        if (!is_dir('./config/domaindns')) mkdir('./config/domaindns',0776); 

        #初始化判断 END
        
        $this -> setting = file::read("config/setting.json");
        $this -> safe = file::read("config/safe.json");
        $this -> domain = file::read("config/domain.json");
        //读取数据

        $data = json_decode($this->safe,true);
        $this -> waittime = $data['waittime'];
        $this -> sleeptime = $data['sleeptime'];
        $this -> checktime = $data['checktime'];
        $this -> safeload = $data['safeload'];
        //防护设置
        $data = json_decode($this->setting,true);
        $this -> cfkey = $data['cfkey'];
        $this -> cfemail = $data['cfemail'];
        //密钥设置
        $this -> domain = json_decode($this->domain,true);
        //域名列表
    }

    //返回前端
    public function get_index() {
        $disabled = explode(',', ini_get('disable_functions'));
        $ifuse = !in_array('exec_shell', $disabled);
        //函数禁用检测
        if (!$ifuse) {
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
            <div class='line'><span class='tname'>CF 绑定邮箱</span>
                <div class='info-r'>
                    <input id='cfemail' placeholder='请输入您的Cloudflare绑定的邮箱地址' type='text' class='bt-input-text mr5' value='$this->cfemail' style='width: 430px;'>
                    <br />
                    <span style='color: rgb(153, 153, 153);'>请输入您的Cloudflare绑定的邮箱地址</span>
                </div>
            </div>
            <div class='line'><span class='tname'>CF API密钥</span>
                <div class='info-r'>
                    <input id='cfkey' placeholder='请输入Cloudflare global API 密钥' type='password' class='bt-input-text mr5' value='$this->cfkey' style='width: 430px;'>
                    <br />
                    <span style='color: rgb(153, 153, 153);'>请输入Cloudflare global 密钥，获取方式详见关于界面</span>
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
        if (empty($this -> setting))  return "<h2 style='text-align:center;margin-top:30%;'>请先在密钥设置中设置cloudflare密钥!</h2>";
        
        if (empty($this -> checktime) || empty($this -> sleeptime) || empty($this -> waittime) || empty($this -> safeload)) {
            $data = array(
                "waittime" => "300",
                "sleeptime" => "5",
                "checktime" => "30",
                "safeload" => (string)load::getServerInfo()['advanceLoad']
            );
            
            $json = json_encode($data);
            file::write('config/safe.json',$json);

            $this -> waittime = "300";
            $this -> sleeptime = "5";
            $this -> checktime = "30";
            $this -> safeload = (string)load::getServerInfo()['advanceLoad'];
        }
        $html = "
            <div class='line'><span class='tname'>等待时间</span>
                <div class='info-r'>
                    <input id='waittime' placeholder='300' type='number' step='1' min='1' max='500' class='bt-input-text mr5' value='$this->waittime' style='width: 100px;'> <span style='color: rgb(153, 153, 153);'>* 在被攻击后,负载恢复正常时关闭5秒盾的等待时间(单位:秒)</span>
                </div>
            </div>
            <div class='line'><span class='tname'>检测周期</span>
                <div class='info-r'>
                    <input id='sleeptime' placeholder='5' type='number' step='1' min='1' max='300' class='bt-input-text mr5' value='$this->sleeptime' style='width: 100px;'> <span style='color: rgb(153, 153, 153);'>* 每多少秒检测一次服务器负载(单位:秒)</span>
                </div>
            </div>
            <div class='line'><span class='tname'>检测时间</span>
                <div class='info-r'>
                    <input id='checktime' placeholder='30' type='number' step='1' min='1' max='1000' class='bt-input-text mr5' value='$this->checktime' style='width: 100px;'> <span style='color: rgb(153, 153, 153);'>* 连续超过安全负载多长时间自动开盾(单位:秒)</span>
                </div>
            </div>
            <div class='line'><span class='tname'>安全负载</span>
                <div class='info-r'>
                    <input id='safeload' placeholder='$this->safeload' type='number' step='0.1' min='1' max='100' class='bt-input-text mr5' value='$this->safeload' style='width: 100px;'> <span style='color: rgb(153, 153, 153);'>* 达到设置的安全负载,则判断为被攻击</span>
                </div>
            </div>
            <div class='line'>
                <span class='tname'></span>
                    <div class='info-r'>
                        <button onclick='save_data_safe()' class='btn btn-success btn-sm'>保存配置</button>
                    </div>
                </div>
            </div>
            <br />
            <span style='color: rgb(153, 153, 153);'>服务器信息: CPU个数: " . load::getServerInfo()['cpunum'] . "个; 每个CPU核心数:" . load::getServerInfo()['cpucore'] . "个; 总核心数:" . load::getServerInfo()['coreAll'] ."; 推荐设置的安全负载: " . load::getServerInfo()['advanceLoad'] . "</span>
       ";
        return $html;
    }

    public function get_domain() {
        if (empty($this -> setting))  return "<h2 style='text-align:center;margin-top:30%;'>请先在密钥设置中设置cloudflare密钥!</h2>";

        @$num = count($this -> domain);
        //or die("<h2 style='text-align:center;margin-top:30%;'>请先在密钥设置中设置cloudflare密钥!</h2>");
        //self::getSecurity();
        $domainlist = '';
        for ($i = 0;$i <= $num - 1;$i++) {
            $domain = $this -> domain[$i]['domain'];
            $zone = $this -> domain[$i]['zone'];
            $security = $this -> domain[$i]['security'];
            $auto = $this -> domain[$i]['auto'];
            if ($auto) {
                $auto = "checked=''";
            } else {
                $auto = "off";
            }
            switch ($security) {
                case 'essentially_off':
                    $securityx = "本质上为关";
                    break;
                case 'low':
                    $securityx = "低";
                    break;
                case 'medium':
                    $securityx = "中";
                    break;
                case 'high':
                    $securityx = "高";
                    break;
                case 'under_attack':
                    $securityx = "开盾";
                    break;

            }
            //转译？？？  <td><a onclick='domain_set($zone)' class='btlink'>详情</a></td>    //    <th>管理</th>
            $domainlist .= "
             <tr>
                <td>$domain</td>
                <td><a class='btlink' onclick=\"set_security('$domain','$zone','$security')\">$securityx</a></td>
                <td>
                    <div class='butt' style='margin-left:0'>
                        <input class='btswitch btswitch-ios' id='$zone' type='checkbox' $auto>
                        <label class='btswitch-btn' for='$zone' onclick=\"set_auto('$zone')\"></label>
                    </div>
                </td>
                <td><a class='btlink' onclick=\"domain_dns('$domain','$zone')\">管理</a></td>
            </tr>
            ";
        }
        $html = "
        <button class='btn btn-success btn-sm' type='button' style='margin-bottom:12px;' onclick='update_domain()'>更新域名列表</button>
        &emsp;
        <button class='btn btn-success btn-sm' type='button' style='margin-bottom:12px;' onclick='update_security()'>更新域名状态</button>
        <div style='max-height:480px;overflow:auto;border:#ddd'>
            <div class='divtable'>
                <table width='100%' border='0' cellpadding='0' cellspacing='0' class='table table-hover'>
                <thead>
                    <tr>
                        <th>域名</th>
                        <th>防御状态</th>
                        <th>自动开盾</th>
                        <th>解析</th>
                    </tr>
                </thead>
                <tbody>
                   $domainlist
                </tbody>
                </table>
            </div>
        </div>";
        return $html;
    }

    public function get_domain_dns() {
        if (empty($this -> setting))  return "<h2 style='text-align:center;margin-top:30%;'>请先在密钥设置中设置cloudflare密钥!</h2>";
        
        $domain = _post("domain");
        //获取查询的域名
        $zone = _post("zone");
        //获取查询的zone
        $file = new file();
        $dns = new dnscontrol($domain,$zone);
        $return = $dns -> getdnslist();
        $num = count($return);
        $dnslist = '';
        if (empty($num)) {
            $dnslist .= "
            <tr>
                <td></td>
                <td>无记录</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        ";
        }
        for ($i = 0;$i <= $num-1;$i++) {
            $id = $return[$i]['id'];
            $type = $return[$i]['type'];
            $name = $return[$i]['name'];
            $ttl = $return[$i]['ttl'];
            $content = $return[$i]['content'];
            $proxied = $return[$i]['proxied'];
            if ($proxied) {
                $proxiedif = '是';
            } else {
                $proxiedif = '否';
            }
            if ($ttl = "1") {
                $ttl = '自动';
            }
            //字符超出字数，则处理下
            $nameShort = strlen($name) > 25 ? substr($name,0,25) . "..." : $name;
            $contentShort = strlen($content) > 25 ? substr($content,0,25) . "..." : $content;

            $dnslist .= "
            <tr>
                <td>$type</td>
                <td>$nameShort</td>
                <td>$contentShort</td>
                <td>$ttl</td>
                <td>$proxiedif</td>
                <td><a class='btlink' onclick=\"domain_dns_edit('$domain','$type','$name','$content','$ttl','$proxied','$zone','$id')\">编辑</a></td>
            </tr>
        ";
        //，每一个域名
        }
        //大框架
        $html = "
        <button class='btn btn-success btn-sm' type='button' style='margin-bottom:12px;' onclick='get_domain_list()'>＜返回</button>
        <button class='btn btn-success btn-sm' type='button' style='margin-bottom:12px;' onclick='update_domain_dns(\"$domain\",\"$zone\")'>更新解析列表</button>
        <button class='btn btn-success btn-sm' type='button' style='margin-bottom:12px;' onclick='add_domain_dns(\"$domain\",\"$zone\")'>添加记录</button>
        <div style='max-height:480px;overflow:auto;border:#ddd'>
            <div class='divtable'>
                <table width='100%' border='0' cellpadding='0' cellspacing='0' class='table table-hover'>
                <thead>
                    <tr>
                        <th>类型</th>
                        <th>名称</th>
                        <th>内容</th>
                        <th>TTL</th>
                        <th>CDN</th>
                        <th>管理</th>
                    </tr>
                </thead>
                <tbody>
                   $dnslist
                </tbody>
                </table>
            </div>
        </div>";
        return $html;
    }

    public function get_runlog() {
        
        $underattacklog = file::read("config/run.log");
        $html = "
            <textarea readonly='' style='margin: 0px;width: 560px;height: 520px;background-color: #333;color:#fff; padding:0 5px' id='error_log'>$underattacklog</textarea>
       ";
        return $html;
    }

    public function get_errorlog() {
        
        $errorlog = file::read("config/error.log");
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
                <p><b>版本：v1.6.0</p>
                <p><b>使用说明：</b>当服务器负载超过安全负载时,自动启动Cloudflare 5秒盾,来防止服务器宕机,同时支持内置解析修改,享受不用打开Cloudflare官网就可以流畅修改域名解析</p>
                <p><b>版权说明：</b>部分功能基于CloudFlare API,使用时应同时遵守Cloudflare相关协议</p>
                <p><b>官网：</b><a class='btlink' href='http://xsot.cn' target='_blank'>http://xsot.cn</a></p>
                <p><b>插件作者：</b>xcsoft</p>
                <p><b>Telegram：</b><a class='btlink' href='https://t.me/xcsoft'>@xcsoft</a></p>
                <p><b>QQ群：</b><a class='btlink' href='https://jq.qq.com/?_wv=1027&k=52w0Rgc'>608265912</a></p>
                <p><b>Email：</b><a class='btlink' href='mailto:contact@xcsoft.top'>contact@xcsoft.top</a></p>
            </div>";
        return $html;
    }

    //处理前端
    public function getZone() {
        //验证cloudflareAPI是否输入正确
        $cfkey = _post('cfkey');
        $cfemail = _post('cfemail');
        $cf = new checkcloudflare($cfkey,$cfemail);
        $data = $cf->getzone();
        if ($data['success']) {
            //如果可以访问
            //直接遍历zong写入temp文件 (节省时间)
            $zones = $data['data'];
            $i = 0;
            //初始化
            foreach ($zones as $zone) {
                $arr[$i]['zone'] = $zone['id'];
                $arr[$i]['domain'] = $zone['name'];
                //获取zone
                $i++;
            }
            $arr['num'] = $i;
            //域名个数
            
            file::write('config/temp.json',json_encode($arr));
            //写入temp 临时文件
            $data = array(
                "cfkey" => _post('cfkey'),
                "cfemail" => _post('cfemail')
            );
            
            $json = json_encode($data);
            file::write('config/setting.json',$json);
            //保存密钥信息
            return 200;
        } else {
            
            $time = date("Y-m-d H:i:s");
            file::write_log("config/error.log","[error]$time | 访问CLOUDFLARE API失败,请检查APi以及邮箱是否输入正确,错误代码:" . $cf->getzone()['code'] . "错误信息:" . $cf->getzone()['message']);
            //写日志
            return 1001;
            //返回错误
        }
    }

    public function updateZone() {
        //验证cloudflareAPI是否输入正确
        $cfkey = $this -> cfkey;
        $cfemail = $this -> cfemail;
        $cf = new checkcloudflare($cfkey,$cfemail);
        $data = $cf->getzone();
        if ($data['success']) {
            //如果可以访问
            //直接遍历zong写入temp文件 (节省时间)
            $zones = $data['data'];
            $i = 0;
            //初始化
            foreach ($zones as $zone) {
                $arr[$i]['zone'] = $zone['id'];
                $arr[$i]['domain'] = $zone['name'];
                //获取zone
                $i++;
            }
            $arr['num'] = $i;
            //域名个数
            
            file::write('config/temp.json',json_encode($arr));
            //写入temp 临时文件
            return 200;
        } else {
            
            $time = date("Y-m-d H:i:s");
            file::write_log("config/error.log","[error]$time | 访问CLOUDFLARE API失败,请检查APi以及邮箱是否输入正确,错误代码:" . $cf->getzone()['code'] . "错误信息:" . $cf->getzone()['message']);
            //写日志
            return 1001;
            //返回错误
        }
    }

    public function getSecurity() {
        $cf = new getsercuity;
        $arr = $cf -> getsecurity();
        if ($arr['success']) {
            $json = json_encode($arr['data']);
            
            file::write('config/domain.json',$json);
            return 200;
        } else {
            return '域名列表获取失败';
        }
    }

    public function setSecurity() {
        //手动切换防护等级
        $zone = _post("zone");
        $security = _post("security");
        $cf = new setsecurity($zone);
        $result = $cf -> Underattack($security);
        if ($result['success']) {
            //成功
            $data = json_encode($result['data']);
            
            file::write("config/domain.json",$data);
            //写文件
            return 200;
        } else {
            return "修改失败,错误信息:" . $result['code'] . " | " . $result['message'];
        }
    }

    public function setAuto() {
        //手动切换防护等级
        $zone = _post("zone");
        $auto = _post("auto");
        $num = count($this -> domain);
        for ($i = 0;$i <= $num - 1;$i++) {
            if ($this -> domain[$i]['zone'] == $zone) {
                $this -> domain[$i]['auto'] = self::is_true($auto,true);
                $status = true;
                break;
            }
        }
        
        file::write("config/domain.json",json_encode($this -> domain));
        if ($status) {
            return 200;
        } else {
            return "未找到该域名,请尝试更新域名列表";
        }
    }

    public function setSafe() {
        if(!is_numeric(_post('waittime')) || !is_numeric(_post('sleeptime')) || !is_numeric(_post('safeload')) || !is_numeric(_post('checktime'))){
            return "表单内容只能为数字";
        }
        if((int)_post('waittime') <= 0 || (int)_post('sleeptime') <= 0 || (int)_post('safeload') <= 0 || (int)_post('checktime') <= 0){
            return "表单内容必须为正数";
        }

        $data = array(
            "waittime" => (int)_post('waittime'),
            "sleeptime" => (int)_post('sleeptime'),
            "checktime" => (int)_post('checktime'), //强制转换类型
            "safeload" => _post('safeload')
        );
        
        $json = json_encode($data);
        file::write('config/safe.json',$json);
        return 200;
    }

    public function refresh_domain_dns() {
        $domain = _post("domain");
        //获取查询的域名
        $zone = _post("zone");
        //获取查询的zone
        $file = new file();
        $dns = new dnscontrol($domain,$zone);
        $dns -> refreshdnslist();
        return "ready";
    }
    
    public function addDns() {
      //$domain = self::zoneTodomain(_post('zone'));
      //当初因为忘记转译了找的备用方法
      if(empty(_post('namex')) || empty(_post('type')) || empty(_post('ttl')))
      {
        return "请确认表单填写完整";      
      }
      if(!strpos(_post('namex'),_post('domain')) !== false && _post('namex') !== _post('domain'))
      {
        //baohan
        return "请输入完整的域名记录(包括一级域名)";
      }
      if(!is_numeric(_post('ttl')))
      {
        return "ttl应为大于60的整数";
      }elseif((double)_post('ttl') < 60 || floor((double)_post('ttl')) !== (double)_post('ttl'))
      {
        return "ttl应为大于60的整秒数";
      }
      $cf = new dnscontrol(_post('domain'),_post('zone'));
      $return = $cf->addDns(_post('type'),_post('namex'),_post('content'),_post('ttl'),_post('proxied'));
      if($return['code'] == 200)
      {
        self::addRecordtoLocal(_post('domain'),$return['id'],$return['type'],$return['name'],$return['content'],$return['proxied'],$return['ttl']);
        return 200;
      }elseif($return['code'] == 81057){
        return "记录值已存在！";
      }elseif($return['code'] ==1004){
        return "不合法的解析地址";
      }else{
        return "新增失败,错误代码: " . $return['code'] . '错误信息：' . $return['msg'];
      }
    }
    
    public function delDns()
    {
      $cf = new dnscontrol(_post('domain'),_post('zone'));
      $return = $cf->delDns(_post('id'));
      if($return['code'] == 200)
      {
        return 200;
      }elseif($return['code'] == 81044){
        $cf->delLocalDns(_post('domain'),_post('id'));
        return "该解析不存在";
      }else{
        return "新增失败,错误代码: " . $return['code'] . '错误信息：' . $return['msg'];
      }
    }
    
    public function editDns()
    {
      if(empty(_post('namex')) || empty(_post('type')) || empty(_post('ttl')))
      {
        return "请确认表单填写完整";      
      }
      if(!strpos(_post('namex'),_post('domain')) !== false && _post('namex') !== _post('domain'))
      {
        //baohan
        return "请输入完整的域名记录(包括一级域名)";
      }
      if(_post('ttl') == "自动")
      {
        $ttl = 60;
      }else{
        $ttl = _post('ttl');
      }
      if(!is_numeric($ttl))
      {
        return "ttl应为大于60的整数";
      }elseif((double)$ttl < 60 || floor((double)$ttl) !== (double)$ttl)
      {
        return "ttl应为大于60的整秒数";
      }
      $cf = new dnscontrol(_post('domain'),_post('zone'));
      $return = $cf->editDns(_post('id'),_post('type'),_post('namex'),_post('content'),$ttl,_post('proxied'));
      if($return['code'] == 200)
      {
        self::editRecordtoLocal(_post('domain'),$return['id'],$return['type'],$return['name'],$return['content'],$return['proxied'],$return['ttl']);
        return 200;
      }else{
        return "修改失败,错误代码: " . $return['code'] . '错误信息：' . $return['msg'];
      }
    }
    
    public function start() {
        if (empty($this -> checktime) || empty($this -> sleeptime) || empty($this -> waittime)) {
            //未设置防护信息
            return 1002;
        }
        if (empty($this -> cfkey) || empty($this -> cfemail)) {
            //未设置密钥信息
            return 1003;
        }

        $output = shell_exec('ps -C php -f');
        if (!strpos($output, "firewall.php") === false) {
            return 1001;
        } else {
            shell_exec('cd ' . PLU_PATH . ' > /dev/null 2>&1 &');
            shell_exec('php firewall.php > /dev/null 2>&1 &');
            
            $time = date("Y-m-d H:i:s");
            file::write_log("config/run.log","[start]$time | 服务启动");
            return 200;
        }
    }

    public function stop() {
        
        $time = date("Y-m-d H:i:s");
        file::write_log("config/run.log","[stop]$time | 服务关闭");
        $output = shell_exec('pgrep -f firewall.php');
        $data = explode("\n",$output);
        $num = count($data);
        for ($i = 0;$i <= $num-2;$i++) {
            shell_exec('kill ' . $data[$i]);
        }
        return 200;
    }

    public function restart() {
        if (empty($this -> checktime) || empty($this -> sleeptime) || empty($this -> waittime)) {
            //未设置防护信息
            return 1002;
        }
        if (empty($this -> cfkey) || empty($this -> cfemail)) {
            //未设置密钥信息
            return 1003;
        }

        
        $time = date("Y-m-d H:i:s");
        file::write_log("config/run.log","[stop]$time | 服务重启");
        $output = shell_exec('pgrep -f firewall.php');
        $data = explode("\n",$output);
        $num = count($data);
        for ($i = 0;$i <= $num-2;$i++) {
            shell_exec('kill ' . $data[$i]);
        }
        shell_exec('cd ' . PLU_PATH . ' > /dev/null 2>&1 &');
        shell_exec('php firewall.php > /dev/null 2>&1 &');
        return 200;
    }
      
    public static function addRecordtoLocal($domain,$id,$type,$name,$content,$proxied,$ttl)
    {
      $f = new file();
      $data = json_decode($f -> read("config/domaindns/" . $domain . ".json"),true);
      array_push($data,array(
        "id" => $id,
        "type" => $type,
        "name" => $name,
        "content" => $content,
        "proxied" => $proxied,
        "ttl" => $ttl
        ));
        $f->write("config/domaindns/" . $domain . ".json",json_encode($data));
    }
    
    public function editRecordtoLocal($domain,$id,$type,$name,$content,$proxied,$ttl)
    {
      $f = new file();
      $data = json_decode($f -> read("config/domaindns/" . $domain . ".json"),true);
      $c = count($data);
      for($i = 0;$i <= $c - 1;$i++)
      {
        if($data[$i]['id'] == $id)
        {
          $data[$i]["id"] = $id;
          $data[$i]["type"] = $type;
          $data[$i]["name"] = $name;
          $data[$i]["content"] = $content;
          $data[$i]["proxied"] = $proxied;
          $data[$i]["ttl"] = $ttl;
          break;
        }
      }
      $f->write("config/domaindns/" . $domain . ".json",json_encode($data));
    }
    
    /*
    public function zoneTodomain($zone)
    {
      //通过本地文件将zone转换为域名
      $f = new file();
      $data = json_decode($f->read("config/domain.json"),true);
      $count = count($data);
      for($i = 0;$i <= $count - 1;$i++)
      {
        if($zone = $data[$i]['zone'])
        {
          $domain = $data[$i]['domain'];
          break;
        }
      }
      if(!empty($domain)){
        return array("code" => 200 , "domain" => $domain);
      }else{
        return array("code" => 1001);
      }
    }
    */
    public static function is_true($val, $return_null = false) {
        $boolval = (is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool) $val);
        return ($boolval === null && !$return_null ? false : $boolval);
    }
}

//文件处理类
class file {
    public static function write($filepath,$content) {
        file_put_contents($filepath, $content);
    }
    public static function read($filepath) {
        if (file_exists($filepath)) {
            $str = file_get_contents($filepath);
            return $str;
        } else {
            return "";
        }
    }
    public static function write_log($filepath,$content) {
        file_put_contents($filepath, $content.PHP_EOL, FILE_APPEND);
    }
}

//cloudflare设置防御模式
class setsecurity {
    function __construct($zone) {
        $this -> zone = $zone;
        
        $this -> domain = json_decode(file::read("config/domain.json"),true);
        $this -> setting = json_decode(file::read("config/setting.json"),true);
        $this -> cfkey = $this -> setting['cfkey'];
        $this -> cfemail = $this -> setting['cfemail'];
    }

    function Underattack($mood) {
        $id = $this -> zone;
        $url = "https://api.cloudflare.com/client/v4/zones/$id/settings/security_level";
        $result = post::postgo($url,"PATCH",array("value" => "$mood"),$this -> cfkey,$this -> cfemail);
        $result = json_decode($result,true);
        //返回结果
        $num = count($this -> domain);
        for ($i = 0;$i <= $num - 1;$i++) {
            if ($this -> domain[$i]['zone'] == $this -> zone) {
                $this -> domain[$i]['security'] = $mood;
                $status = true;
                break;
            }
        }
        //遍历数组修改。。。回头改改json的数据形式
        //修改配置文件中的信息
        if (!$status) {
            return array(
                "success" => true,
                "code" => 1001,
                "message" => "未找到该域名,请尝试更新域名列表"
            );

        }

        if ($result['success']) {
            return array(
                "success" => true,
                "data" => $this -> domain
            );
        } else {
            return array(
                "success" => false,
                "code" => $result['errors'][0]['code'],
                "message" => $result['errors'][0]['message']
            );
        }
    }
}

//cloudflare获取解析列表
class dnscontrol {
    function __construct($domain,$zone) {
        $this -> domain = $domain;
        $this -> zone = $zone;
        //初始化
        
        $this -> setting = file::read("config/setting.json");
        $data = json_decode($this->setting,true);
        $this->cfkey = $data['cfkey'];
        $this->cfemail = $data['cfemail'];
        //读取账户与密钥
    }
    //获取dns列表
    function getdnslist() {
        
        if (file_exists("config/domaindns/" . $this -> domain . ".json")) {
            return json_decode(file::read("config/domaindns/" . $this -> domain . ".json"),true);
            //如果存在缓存,直接输出
        } else {
            //如果不存在缓存,获取数据
            $url = "https://api.cloudflare.com/client/v4/zones/" . $this -> zone . "/dns_records";
            $return = json_decode(post::postgo($url,"GET","",$this->cfkey,$this->cfemail),true)['result'];
            //开始遍历 
            @$num = count($return);
            $data = array();
            for ($i = 0;$i <= $num-1;$i++) {
                $data[$i]['id'] = $return[$i]['id'];
                $data[$i]['type'] = $return[$i]['type'];
                $data[$i]['name'] = $return[$i]['name'];
                $data[$i]['content'] = $return[$i]['content'];
                $data[$i]['proxied'] = $return[$i]['proxied'];
                $data[$i]['ttl'] = $return[$i]['ttl'];
            }
            file::write("config/domaindns/" . $this -> domain . ".json",json_encode($data));
            return $data;
            //返回数据
        }
    }

    function refreshdnslist() {
        
        //如果不存在缓存,获取数据
        $url = "https://api.cloudflare.com/client/v4/zones/" . $this -> zone . "/dns_records";
        $return = json_decode(post::postgo($url,"GET","",$this->cfkey,$this->cfemail),true)['result'];
        //开始遍历
        @$num = count($return);
        $data = array();
        for ($i = 0;$i <= $num-1;$i++) {
                $data[$i]['id'] = $return[$i]['id'];
                $data[$i]['type'] = $return[$i]['type'];
                $data[$i]['name'] = $return[$i]['name'];
                $data[$i]['content'] = $return[$i]['content'];
                $data[$i]['proxied'] = $return[$i]['proxied'];
                $data[$i]['ttl'] = $return[$i]['ttl'];
            }
        file::write("config/domaindns/" . $this -> domain . ".json",json_encode($data));
    }
    
    function addDns ($type,$name,$content,$ttl,$proxied)
    {
      $data = array(
        "type" => $type,
        "name" => $name,
        "content" => $content,
        "proxied" => (bool)$proxied,
        "ttl" => $ttl
        );
        $url = "https://api.cloudflare.com/client/v4/zones/$this->zone/dns_records";
        $return = post::postgo($url,"POST",$data,$this->cfkey,$this->cfemail);
        $return = json_decode($return,true);
        if($return['success']){
          return array(
            "code" => 200,
            "id" => $return['result']['id'],
            "type" => $return['result']['type'],
            "name" => $return['result']['name'],
            "content" => $return['result']['content'],
            "proxied" => $return['result']['proxied'],
            "ttl" => $return['result']['ttl']
          );
        }else{
          return array("code" => $return['errors']['0']['code'],"msg" => $return['errors']['0']['message']);
        }
    }
    
    function editDns($id,$type,$name,$content,$ttl,$proxied)
    {
      $data = array(
        "type" => $type,
        "name" => $name,
        "content" => $content,
        "proxied" => (bool)$proxied,
        "ttl" => $ttl
        );
        $url = "https://api.cloudflare.com/client/v4/zones/$this->zone/dns_records/$id";
        $return = post::postgo($url,"PUT",$data,$this->cfkey,$this->cfemail);
        $return = json_decode($return,true);
        if($return['success']){
          return array(
            "code" => 200,
            "id" => $return['result']['id'],
            "type" => $return['result']['type'],
            "name" => $return['result']['name'],
            "content" => $return['result']['content'],
            "proxied" => $return['result']['proxied'],
            "ttl" => $return['result']['ttl']
          );
        }else{
          return array("code" => $return['errors']['0']['code'],"msg" => $return['errors']['0']['message']);
        }
    }
    
    function delDns($id)
    {
        $url = "https://api.cloudflare.com/client/v4/zones/$this->zone/dns_records/$id";
        $return = post::postgo($url,"DELETE","",$this->cfkey,$this->cfemail);
        $return = json_decode($return,true);
        if($return['success']){
                //接下来删除本地解析记录
          self::delLocalDns($this->domain,$id);
          return array("code" => 200,);
        }else{
          return array("code" => $return['errors']['0']['code'],"msg" => $return['errors']['0']['message']);
        }
    }
    
    public function delLocalDns($domain,$id)
    {
      $f = new file();
      $data = json_decode($f -> read("config/domaindns/" . $domain . ".json"),true);
      foreach($data as $v=>$k)
      {
        if($data[$v]['id'] == $id) 
        { 
          unset($data[$v]);
        }
        }
      $data = array_values($data);
       $data = $f -> write("config/domaindns/" . $domain . ".json",json_encode($data));
    }
}
//cloudflare获取站点防御信息类
class getsercuity{
    function __construct() {
        
        $this -> setting = file::read("config/setting.json");
        $data = json_decode($this->setting,true);
        $this->cfkey = $data['cfkey'];
        $this->cfemail = $data['cfemail'];
        //读取账户与密钥
        $this -> zone = json_decode(file::read("config/temp.json"),true);
        //读取临时文件
    }

    function getsecurity() {
        $num = $this -> zone['num'];
        if ($num == 0) {
            return array(
                "success" => false
            );
            //如果无域名
        }
        //域名个数
        $arr = array();
        for ($i = 0;$i <= $num - 1;$i++) {
            $id = $this -> zone[$i]['zone'];
            $url = "https://api.cloudflare.com/client/v4/zones/$id/settings/security_level";
            $arr[$i]['security'] = json_decode(post::postgo($url,"GET","",$this->cfkey,$this->cfemail),true)['result']['value'];
            $arr[$i]['domain'] = $this -> zone[$i]['domain'];
            $arr[$i]['zone'] = $this -> zone[$i]['zone'];
            $arr[$i]['auto'] = true;
            //是否自动开盾
        }
        return array(
            "success" => true,
            "data" => $arr
        );
    }
}

class checkcloudflare {
    function __construct($cfkey,$cfemail) {
        $this->cfkey = $cfkey;
        $this->cfemail = $cfemail;
    }

    function getzone() {
        $url = "https://api.cloudflare.com/client/v4/zones/";
        $zones = post::postgo($url,"GET","",$this->cfkey,$this->cfemail);
        $ifright = json_decode($zones,true);
        if ($ifright['success']) {
            $zones = json_decode($zones,true)['result'];
            return array(
                "success" => true,
                "data" => $zones,
            );
        } else {
            return array(
                "success" => false,
                "code" => $ifright['errors'][0]['code'],
                "message" => $ifright['errors'][0]['message']
            );
        }
    }
}

class post
{
    public static function postgo($url = "",$method = "GET",$data = array(),$apikey,$email) {
        $headers = array(
            'Content-Type: ' . "application/json" ,
            "X-Auth-Key: " . "$apikey" ,
            "X-Auth-Email: " . "$email"
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 360000);
        //设置超时
        if (0 === strpos(strtolower($url), 'https')) {
            //https请求
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            //对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            //从证书中检查SSL加密算法是否存在
        }
        curl_setopt($ch, CURLOPT_POST, false);
        if ($method == "DELETE") {
            curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        if ($method == "PUT") {
            curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data));
        }
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        if ($method == "PATCH") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        //curl_setopt( $ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec ($ch);
        curl_close ($ch);
        return $resp;
    }
}

class load{
  public static function getServerInfo() : array
    {
        $cpunum = (int)shell_exec('cat /proc/cpuinfo| grep "physical id"| sort| uniq| wc -l'); //获取cpu个数
        preg_match('/[0-9]{1,}/',shell_exec('cat /proc/cpuinfo| grep "cpu cores"| uniq'),$cpucore); //获取cpu核心数
        return array(
            'cpunum' => $cpunum,
            'cpucore' => (int)$cpucore[0],
            'coreAll' => (int)$cpunum * (int)$cpucore[0],
            'advanceLoad' => ((int)$cpucore[0] * $cpunum) * 2 * 0.75
        );
        //返回服务器安全负载
  }
}
?>