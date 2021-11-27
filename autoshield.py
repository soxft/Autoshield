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


# 获取用户设置的安全配置
def getSafeInfo():
    try:
        data = json.loads(public.ReadFile(SAFE_FILE_PATH, mode='r'))
    except:
        public.WriteLog(PLUGIN_NAME, '请正确配置防护设置后重试')
        print('请正确配置防护设置后重试')
        sys.exit()
    return data


# 获取用户的 域名列表
def getUserDomainList():
    try:
        data = json.loads(public.ReadFile(DOMAIN_FILE_PATH, mode='r'))
    except:
        public.WriteLog(PLUGIN_NAME, '请检查密钥信息后重试')
        print('请检查密钥信息后重试')
        sys.exit()
    return data


# 获取当前秒级时间戳
def getTimeStamp():
    return int(time.time())


# 开盾 老子的意大利炮呢
def underAttack():
    domainInfo = getUserDomainList()
    count = domainInfo['count']
    domainList = domainInfo['domains']
    print('检索到{count}个域名, 尝试开盾'.format(count=count))
    cf = Cloudflare()
    for domainName, domainInfo in domainList.items():
        domainId = domainInfo['id']
        active = domainInfo['status']
        print(domainName, end=' > ')
        if active:
            # 激活状态
            response = cf.setDomainMode(domainId=domainId, mode='under_attack')
            if response['success']:
                print('开启成功')
                changeDomainSecurity(domainName, 'under_attack')
            else:
                public.WriteLog(
                    PLUGIN_NAME,
                    '自动开盾时,{domain_name}开盾失败 > {error}'
                    .format(domain_name=domainName, error=json.dumps(response['errors']))
                )
                print('开启失败 > 具体错误可以在面板/安全选项卡查询')
        else:
            print('用户未开启,跳过')
    public.WriteLog(
        PLUGIN_NAME,
        '服务器遭遇攻击 > 开盾'
    )


#  关盾
def closeShield():
    domainInfo = getUserDomainList()
    count = domainInfo['count']
    domainList = domainInfo['domains']
    print('检索到{count}个域名, 尝试关盾'.format(count=count))
    cf = Cloudflare()
    for domainName, domainInfo in domainList.items():
        domainId = domainInfo['id']
        active = domainInfo['status']
        print(domainName, end=' > ')
        if active:
            # 激活状态
            response = cf.setDomainMode(domainId=domainId, mode='medium')
            if response['success']:
                print('关闭成功')
                changeDomainSecurity(domainName, 'medium')
            else:
                public.WriteLog(
                    PLUGIN_NAME,
                    '关盾时,{domain_name}关闭失败 > {error}'
                    .format(domain_name=domainName, error=json.dumps(response['errors']))
                )
                print('关闭失败 > 具体错误可以在面板/安全选项卡查询')
        else:
            print('用户未开启,跳过')
    public.WriteLog(
        PLUGIN_NAME,
        '服务器遭遇攻击结束 > 关盾'
    )


def changeDomainSecurity(domain, mode):
    re = getUserDomainList()
    try:
        re['domains'][domain]['security'] = mode
        public.WriteFile(
            DOMAIN_FILE_PATH,
            json.dumps(re),
            mode='w+'
        )
    except:
        pass


class Cloudflare:
    __base_url = "https://api.cloudflare.com/client/v4/"

    def __init__(self):
        data = json.loads(public.ReadFile(SETTING_FILE_PATH, mode='r'))
        self.key = data['key'] if data['key'] else ''
        self.email = data['email'] if data['email'] else ''

    def setDomainMode(self, domainId, mode):
        response = self.__patch(
            'zones/{}/settings/security_level'.format(domainId),
            {'value': mode}
        )
        return response

    def __patch(self, url, data):
        response = requests.patch(
            self.__base_url + url,
            data=json.dumps(data),
            headers={
                "Content-Type": "application/json",
                "X-Auth-Key": self.key,
                "X-Auth-Email": self.email,
            }
        )
        return response.json()

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
        return response.json()

    def __get(self, url, param):
        response = requests.get(
            self.__base_url + url,
            params=param,
            headers={
                "X-Auth-Key": self.key,
                "X-Auth-Email": self.email,
            }
        )
        return response.json()


def main():
    print('---- {} ----'.format(PLUGIN_NAME))
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
    print("\r\n尝试获取用户域名列表 > ")
    print("\r\n{}\r\n".format(getUserDomainList()))
    print('服务将在2秒后运行')
    # time.sleep(2)

    # 循环检测
    while True:
        load_now = getLoadNow()

        if load_now < load_safe:
            print("当前负载: {load_now}, 小于安全负载({load_safe})"
                  .format(load_now=load_now, load_safe=load_safe))
        else:
            public.WriteLog(
                PLUGIN_NAME, "服务器负载超过设定阀值,数值为{load_now} > 持续等待{check}秒判断"
                .format(load_now=load_now, check=check)
            )
            print("当前负载: {load_now}, 高于安全负载({load_safe}) > 持续等待{check}秒判断"
                  .format(load_now=load_now, load_safe=load_safe, check=check)
                  )
            # 负载爆表 > 开始持续监测
            start_check_time = getTimeStamp()
            print('开始维持{check}秒的持续监测'.format(check=check))
            while True:
                time_pass = getTimeStamp() - start_check_time  # 检测过去了多久
                time_left = check - time_pass  # 检测剩余时间
                if (getLoadNow() > load_safe):
                    print('当前负载: {load_now} > 安全负载: {load_safe} > 持续监测{time_left}秒后开盾'
                          .format(load_now=getLoadNow(), load_safe=load_safe, time_left=time_left)
                          )
                    if (time_left <= 0):
                        # 开盾 >
                        print('尝试开盾 >')
                        underAttack()
                        print('持续监测负载状况')
                        time_underattact_start = getTimeStamp()
                        stopShield = False  # 初始化 是否关闭盾牌
                        while True:
                            # 开盾 状态 后 继续判断
                            time_underattact_pass = getTimeStamp() - time_underattact_start  # 过去了多久
                            if getLoadNow() > load_safe:
                                print('当前负载: {load_now} 仍然高于 安全负载: {load_safe} (当前处于开盾模式已经{time_underattact_pass}秒)'
                                      .format(load_now=getLoadNow(), load_safe=load_safe, time_underattact_pass=time_underattact_pass)
                                      )
                            else:
                                # 负载低于了 安全负载
                                # 持续等待”等待时间“
                                time_underattact_end = getTimeStamp()
                                while True:
                                    time_underattact_end_pass = getTimeStamp() - time_underattact_end  # 过去了多久
                                    time_still_wait_end = wait - time_underattact_end_pass
                                    print('当前负载: {load_now} 已经低于了 安全负载: {load_safe} 持续等待{time_still_wait_end}秒后关闭盾'
                                          .format(load_now=getLoadNow(), load_safe=load_safe, time_still_wait_end=time_still_wait_end)
                                          )
                                    if time_still_wait_end <= 0:
                                        closeShield()  # 关盾
                                        stopShield = True
                                        break
                                    if getLoadNow() > load_safe:
                                        break
                                    time.sleep(2)
                            time.sleep(2)  # 每两秒检测一次
                            if stopShield:
                                break
                        break
                else:
                    print('当前负载: {load_now} < 安全负载: {load_safe} > 威胁解除'
                          .format(load_now=load_now, load_safe=load_safe)
                          )
                    public.WriteLog(
                        PLUGIN_NAME, "服务器负载在{time_pass}后恢复到负载阀值以下,当前负载为{load_now} > 威胁解除"
                        .format(load_now=load_now, time_pass=time_pass)
                    )
                    break
                time.sleep(1)

        time.sleep(sleep)


if __name__ == '__main__':
    main()
   # print (getUserDomainList())
