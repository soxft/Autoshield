<?php
// +-------------------------------------------------------------------
// | cloudflare自动开盾
// +-------------------------------------------------------------------
// | Copyright (c) 2020 xcsoft(http://xsot.cn) All rights reserved.
// +-------------------------------------------------------------------
// | Author: xcsoft(contact@xcosft.top)
// +-------------------------------------------------------------------
//服务器负载获取类
class load {
  public function getLoad() {
    $output = shell_exec('uptime');
    $start = strpos($output,"average:")+9;
    $output = substr($output,$start);
    $end = strpos($output,",");
    $output = trim(substr($output,0,$end));
    //获取服务器目前负载
    return (double)$output;
  }
  public function getSafeload() {
    $cpunum = (double)shell_exec('cat /proc/cpuinfo| grep "physical id"| sort| uniq| wc -l');
    //获取cpu个数
    $cpucore = shell_exec('cat /proc/cpuinfo| grep "cpu cores"| uniq');
    $start = strpos($cpucore,":")+2;
    $cpucore = (double)substr($cpucore,$start);
    //获取cpu核心数
    return ($cpucore * $cpunum) * 2 * 0.75;
    //返回服务器安全负载
  }
}

//cloudflare访问类
class cloudflare
{
  function __construct() {
    $file = new file;
    $this -> setting = $file -> read("config/setting.json");
    $data = json_decode($this->setting,true);
    $this->cfkey = $data['cfkey'];
    $this->cfemail = $data['cfemail'];
  }

  function getzone() {
    $url = "https://api.cloudflare.com/client/v4/zones/";
    $zones = cloudflare::postgo($url,"GET","",$this->cfkey,$this->cfemail);
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

  function postgo($url = "",$method = "GET",$data = array(),$apikey,$email) {
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

  function Underattack($mood) {
    $zones = cloudflare::getzone($this->cfkey,$this->cfemail)['data'];
    //echo($zones."hello");
    foreach ($zones as $zone) {
      $id = $zone['id'];
      $url = "https://api.cloudflare.com/client/v4/zones/$id/settings/security_level";
      //echo($url."\n");
      $result = cloudflare::postgo($url,"PATCH",array("value" => "$mood"),$this->cfkey,$this->cfemail);
    }
    $result = json_decode($result,true);
    if ($result['success']) {
      return array(
        "success" => true
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
//文件写入读取类
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

$cf = new cloudflare;
if (!$cf->getzone()['success']) {
  $file = new file;
  $time = date("Y-m-d H:i:s");
  $file->write_log("config/error.log","[error]$time | 访问CLOUDFLARE API失败,请检查APi以及邮箱是否输入正确,错误代码:" . $cf->getzone()['code'] . "错误信息:" . $cf->getzone()['message']);
  $file->write_log("config/run.log","[error]$time | 检测到错误,详见错误日志,服务停止");
  exit();
}
//检测cloudflareAPI是否可以访问

$file = new file;
$setting = $file -> read("config/safe.json");
$data = json_decode($setting,true);
$sleeptime = $data['sleeptime'];
$waittime = $data['waittime'];
$checktime = $data['checktime'];
if (empty($sleeptime) || empty($waittime) || empty($checktime)) {
  $file = new file;
  $time = date("Y-m-d H:i:s");
  $file->write_log("config/error.log","[error]$time | 未设置防护信息,请重新配置访问设置");
  $file->write_log("config/run.log","[error]$time | 检测到错误,详见错误日志,服务停止");
  exit();
  exit();
}

$load = new load;
$load_safe = $load->getSafeload();
//获取服务器安全负载
while (true) {
  $time = date("Y-m-d H:i:s");
  $load_one = $load->getLoad();
  //获取服务器目前负载
  if (!$ifunderattack) {
    if ($load_one < $load_safe*0.6) {
      echo "[safe]$time | 当前负载: " . $load_one . "远小于安全负载: " . $load_safe . " -> 安全\n";
      $detectime = time();
    } elseif ($load_one >= $load_safe*0.7 && $load_one <= $load_safe) {
      echo "[normal]$time | 当前负载: " . $load_one . "接近安全负载: " . $load_safe . " -> 即将开盾\n";
      $detectime = time();
    } else {

      $file = new file;
      $safe = $file -> read("config/safe.json");
      $data = json_decode($safe,true);
      $checktime = $data['checktime'];

      $tttime = $checktime - (time() - $detectime);
      //判断是否达到等待时间

      echo "[underattack]$time | 当前负载: " . $load_one . "超过安全负载: " . $load_safe . " -> 检测到攻击 -> 等待持续判断 $tttime 秒观察\n";

      if ($tttime <= 0) {
        $file = new file;
        $file->write_log("config/run.log","[caveat]$time | 检测到攻击,尝试开盾...");
        $return = $cf->Underattack("under_attack");
        if ($return['success']) {
          echo "[underattack]$time | 开盾成功\n";

          $file = new file;
          $file->write_log("config/run.log","[underattack]$time | 开盾成功...");

          $ifunderattack = true;
          $check = true;
        } else {
          echo "[underattack]$time | 开盾失败,错误代码:" . $return['code'] . "错误信息:" . $return['message'] . "\n";
          $file = new file;
          $file->write_log("config/run.log","[start]$time | 开盾失败...");
        }
      }
    }
    sleep($sleeptime);
  } else {
    $time = date("Y-m-d H:i:s");
    $load_one = $load->getLoad();
    //获取服务器目前负载
    if ($load_one >= $load_safe) {
      echo "[underattack]$time | 当前负载: " . $load_one . "持续被攻击中: " . $load_safe . " -> 持续开盾\n";
      $attacktime = time();
    } elseif ($load_one >= $load_safe*0.7 && $load_one < $load_safe*0.9) {
      echo "[underattack]$time | 当前负载: " . $load_one . "攻击稍许降低: " . $load_safe . " -> 持续观察中\n";
      $attacktime = time();
    } else {
      $file = new file;
      $safe = $file -> read("config/safe.json");
      $data = json_decode($safe,true);
      $waittime = $data['waittime'];
      $ttime = $waittime - (time() - $attacktime);
      echo "[underattack]$time | 当前负载: " . $load_one . "攻击降低: " . $load_safe . " -> 等待 $ttime 秒观察\n";
      if ($ttime <= 0) {
        $return = $cf->Underattack("medium");
        if ($return['success']) {
          echo "[underattack]$time | 关盾成功\n";

          $file = new file;
          $file->write_log("config/run.log","[safe]$time | 关盾成功...");

          $ifunderattack = false;
        } else {
          echo "[underattack]$time | 关盾失败,错误代码:" . $return['code'] . "错误信息:" . $return['message'] . "\n";
        }
      }
    }
  }
  sleep($sleeptime);
}