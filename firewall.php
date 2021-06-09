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
//服务器负载获取类
class load
{
  /**
   * 获取服务器一分钟内的负载
   * @author xcsoft
   * @param void
   * @return float load 
   */
  public static function getLoad() : float
  {
    preg_match('/[0-9]{0,}[.][0-9]{1,}/',shell_exec('uptime'),$load); // 一分钟负载
    return (float)$load[0];
  }
  public static function getSafeload() : float
  {
    $re = json_decode(file::read('config/safe.json'),true)['safeoad'];
    return (float)$re;
    //返回服务器安全负载
  }
}

//文件写入读取类
class file
{
  public static function write($filepath, $content)
  {
    file_put_contents($filepath, $content);
  }
  public static function read($filepath)
  {
    if (file_exists($filepath)) {
      $str = file_get_contents($filepath);
      return $str;
    } else {
      return "请输入内容";
    }
  }
  public static function write_log($filepath, $content)
  {
    file_put_contents($filepath, $content . PHP_EOL, FILE_APPEND);
  }
}

//cloudflare访问类
class cloudflare
{
  function __construct()
  {
    $this->setting = file::read("config/setting.json");
    $data = json_decode($this->setting, true);
    $this->cfkey = $data['cfkey'];
    $this->cfemail = $data['cfemail'];
    //获取域名列表
    $this->domain = json_decode(file::read("config/domain.json"), true);
  }

  private static function  postgo($url = "", $method = "GET", $data = array(), $apikey, $email)
  {
    $headers = array(
      'Content-Type: ' . "application/json",
      "X-Auth-Key: " . "$apikey",
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
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
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
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp;
  }

  function Underattack($mood)
  {
    $num = count($this->domain);
    for ($i = 0; $i <= $num - 1; $i++) {
      if ($this->domain[$i]['auto']) {
        //确认是否打开了自动开盾
        $id = $this->domain[$i]['zone'];
        $url = "https://api.cloudflare.com/client/v4/zones/$id/settings/security_level";
        $result = self::postgo($url, "PATCH", array("value" => "$mood"), $this->cfkey, $this->cfemail);
        $this->domain[$i]['security'] = $mood;
        //写文件
      }
    }
    $result = json_decode($result, true);
    if ($result['success']) {
      file::write("config/domain.json", json_encode($this->domain));
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



# main


function main()
{
  $setting = file::read("config/safe.json");
  $data = json_decode($setting, true);
  $sleeptime = $data['sleeptime'];
  $waittime = $data['waittime'];
  $checktime = $data['checktime'];

  if (empty($sleeptime) || empty($waittime) || empty($checktime)) {
    $time = date("Y-m-d H:i:s");
    file::write_log("config/error.log", "[error]$time | 未设置防护信息,请重新配置访问设置");
    file::write_log("config/run.log", "[error]$time | 检测到错误,详见错误日志,服务停止");
    exit();
  }

  $load_safe = load::getSafeload();
  //获取服务器安全负载

  while (true) {
    $cf = new cloudflare;
    $time = date("Y-m-d H:i:s");
    $load_one = load::getLoad();
    //获取服务器目前负载
    $ifunderattack = false;
    if (!$ifunderattack) {
      if ($load_one < $load_safe * 0.6) {
        echo "[safe]$time | 当前负载: " . $load_one . "远小于安全负载: " . $load_safe . " -> 安全\n";
        $detectime = time();
      } elseif ($load_one >= $load_safe * 0.7 && $load_one <= $load_safe) {
        echo "[normal]$time | 当前负载: " . $load_one . "接近安全负载: " . $load_safe . " -> 即将开盾\n";
        $detectime = time();
      } else {

        $safe = file::read("config/safe.json");
        $data = json_decode($safe, true);
        $checktime = $data['checktime'];

        $tttime = $checktime - (time() - $detectime);
        //判断是否达到等待时间

        echo "[underattack]$time | 当前负载: " . $load_one . "超过安全负载: " . $load_safe . " -> 检测到攻击 -> 等待持续判断 $tttime 秒观察\n";

        if ($tttime <= 0) {
          file::write_log("config/run.log", "[caveat]$time | 检测到攻击,尝试开盾...");
          $return = $cf->Underattack("under_attack");
          if ($return['success']) {
            echo "[underattack]$time | 开盾成功\n";

            file::write_log("config/run.log", "[underattack]$time | 开盾成功...");

            $ifunderattack = true;
            $check = true;
          } else {
            echo "[underattack]$time | 开盾失败,错误代码:" . $return['code'] . "错误信息:" . $return['message'] . "\n";
            file::write_log("config/run.log", "[start]$time | 开盾失败...");
          }
        }
      }

      sleep($sleeptime);

    } else {

      $cf = new cloudflare;
      $time = date("Y-m-d H:i:s");
      $load_one = load::getLoad();
      //获取服务器目前负载
      if ($load_one >= $load_safe) {
        echo "[underattack]$time | 当前负载: " . $load_one . "持续被攻击中: " . $load_safe . " -> 持续开盾\n";
        $attacktime = time();
      } elseif ($load_one >= $load_safe * 0.7 && $load_one < $load_safe * 0.9) {
        echo "[underattack]$time | 当前负载: " . $load_one . "攻击稍许降低: " . $load_safe . " -> 持续观察中\n";
        $attacktime = time();
      } else {
        $safe = file::read("config/safe.json");
        $data = json_decode($safe, true);
        $waittime = $data['waittime'];
        $ttime = $waittime - (time() - $attacktime);
        echo "[underattack]$time | 当前负载: " . $load_one . "攻击降低: " . $load_safe . " -> 等待 $ttime 秒观察\n";
        if ($ttime <= 0) {
          $return = $cf->Underattack("medium");
          if ($return['success']) {
            echo "[underattack]$time | 关盾成功\n";
            file::write_log("config/run.log", "[safe]$time | 关盾成功...");
            $ifunderattack = false;
          } else {
            echo "[underattack]$time | 关盾失败,错误代码:" . $return['code'] . "错误信息:" . $return['message'] . "\n";
          }
        }
      }
    }
    sleep($sleeptime);
  }
}

while (true){
  echo load::getLoad() . PHP_EOL;
  sleep(1);
}