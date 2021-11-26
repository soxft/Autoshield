#!/usr/bin/python
# coding: utf-8

# @Descript cloudflare_autoshield <cloudflare自动开盾>
# @Version 2.0.0_Python_rebuild
# @Author xcsoft<contact@xcsoft.top>
# @Date 2021 11 23
import sys
import os
import time
import requests
import json

os.chdir("/www/server/panel")
sys.path.append("class/")
import public

PLUGIN_NAME = 'autoshield'
PLUGIN_PATH = "/www/server/panel/plugin/{}/".format(PLUGIN_NAME)

SETTING_FILE_PATH = PLUGIN_PATH + 'config/setting.json'  # setting文件路径
SAFE_FILE_PATH = PLUGIN_PATH + 'config/safe.json'  # safe文件路径
DOMAIN_FILE_PATH = PLUGIN_PATH + 'config/domain.json'  # 用户域名temp文件路径


# 返回服务器的当前负载 (1min)
def getLoadNow():
    f = public.ExecShell(
        "uptime | sed 's/,//g' | awk '{print $10}'"
    )[0]
    return float(f.strip())


# 用户用户设置的安全配置
def getSafeInfo():
    try:
        data = json.loads(public.ReadFile(SAFE_FILE_PATH, mode='r'))
    except:
        public.WriteLog(PLUGIN_NAME, '请正确配置防护设置后重试')
        print('请正确配置防护设置后重试')
        sys.exit()
    return data


# 获取当前秒级时间戳
def getTimeStamp():
    return int(time.time())


class Cloudflare:
    __base_url = "https://api.cloudflare.com/client/v4/"

    def __init__(self):
        data = json.loads(public.ReadFile(SETTING_FILE_PATH, mode='r'))
        self.key = data['key'] if data['key'] else ''
        self.email = data['email'] if data['email'] else ''

    def __post(self, url, data):
        response = requests.post(
            self.__base_url + url,
            data=json.dumps(data),
            headers={
                "Content-Type": "application/json",
                "X-Auth-Key": self.key,
                "X-Auth-Email": self.email,
            }
        )
        return json.loads(response.json)

    def __get(self, url, param):
        response = requests.get(
            self.__base_url + url,
            params=param,
            headers={
                "X-Auth-Key": self.key,
                "X-Auth-Email": self.email,
            }
        )
        return json.loads(response.text)


if __name__ == '__main__':
    print('----{}----'.format(PLUGIN_NAME))
    print('尝试获取服务器基本信息 > ')

    res = getSafeInfo()
    wait = res['wait']  # 负载恢复后的等待周期
    sleep = res['sleep']  # 检测周期
    check = res['check']  # 持续监测时间
    load_safe = res['load']  # 安全负载

    print('''
        等待时间: {}
        检测周期: {}
        检测时间: {}
        负载阀值: {}
    '''.format(wait, sleep, check, load_safe))
    print('服务将在2秒后运行')
    # time.sleep(2)

    # 循环检测
    while True:
        load_now = getLoadNow()

        if load_now < load_safe:
            msg = '当前负载小于安全负载({})'.format(load_safe)
            print("当前负载: {load_now}, 小于安全负载({load_safe})"
                  .format(load_now=load_now, load_safe=load_safe))
        else:
            public.WriteLog(
                PLUGIN_NAME, "服务器负载超过设定阀值,数值为{load_now} > 持续等待{check}判断"
                .format(load_now=load_now, check=check)
            )
            print("当前负载: {load_now}, 高于安全负载({load_safe}) > 持续等待{check}判断"
                  .format(load_now=load_now, load_safe=load_safe, check=check)
                  )
            # 负载爆表 > 开始持续监测
            start_check_time = getTimeStamp()
            print('开始维持{check}秒的持续监测'.format(check=check))
            while True:
                load_now = getLoadNow()
                time_pass = getTimeStamp() - start_check_time # 检测过去了多久
                time_left = check - time_pass # 检测剩余时间
                if (load_now > load_safe):
                    print('当前负载: {load_now} > 安全负载: {load_safe} > 持续等待{time_left}'
                          .format(load_now=load_now, load_safe=load_safe, time_left=time_left)
                          )
                else:
                    print('当前负载: {load_now} < 安全负载: {load_safe} > 威胁解除'
                          .format(load_now=load_now, load_safe=load_safe)
                          )
                    public.WriteLog(
                        PLUGIN_NAME, "服务器负载在{time_pass}后恢复到负载阀值一下,当前负载为{load_now} > 威胁解除"
                        .format(load_now=load_now, time_pass=time_pass)
                    )
                    break
                time.sleep(1)

        time.sleep(sleep)
