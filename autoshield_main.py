#!/usr/bin/python
# coding: utf-8

# @Descript cloudflare_autoshield <cloudflare自动开盾>
# @Version 2.0.0_Python_rebuild
# @Author xcsoft<contact@xcsoft.top>
# @Date 2021 11 23

import public
import os
import sys
import json
import requests

os.chdir("/www/server/panel")
sys.path.append("class/")
import psutil

PLUGIN_NAME = 'autoshield'
FIREWALL_SERVICE_NAME = 'autoshield.py'
PLUGIN_PATH = "/www/server/panel/plugin/{}/".format(PLUGIN_NAME)
FIREWALL_SERVICE_PATH = PLUGIN_PATH + FIREWALL_SERVICE_NAME

SETTING_FILE_PATH = PLUGIN_PATH + 'config/setting.json'  # setting文件路径
SAFE_FILE_PATH = PLUGIN_PATH + 'config/safe.json'  # safe文件路径
DOMAIN_FILE_PATH = PLUGIN_PATH + 'config/domain.json'  # 用户域名temp文件路径
DOMAIN_DNS_BASE_PATH = PLUGIN_PATH + 'config/dns/'  # 用户域名temp文件路径

PER_PAGE = 500  # 获取的域名个数 值应该在1到1000之间


class autoshield_main:
    # 构造方法
    def __init__(self):
        config_path = PLUGIN_PATH + 'config/'
        dns_path = PLUGIN_PATH + 'config/dns/'
        if not os.path.isdir(config_path):
            os.makedirs(config_path, 755)
        if not os.path.isdir(dns_path):
            os.makedirs(dns_path, 755)
        pass

    # 获取服务运行状态
    def get_status(self, args):
        result = public.ExecShell('ps -C btpython -f')
        runStatus = FIREWALL_SERVICE_NAME in result[0]
        return {'runStatus': runStatus}

    # 获取cloudflare key & email
    def get_setting(self, args):
        default = {
            'email': "",
            'cfkey': "",
        }
        if not os.path.exists(SETTING_FILE_PATH):
            public.WriteFile(SETTING_FILE_PATH, json.dumps(default), mode='w+')
        try:
            data = json.loads(public.ReadFile(SETTING_FILE_PATH, mode='r'))
            return {
                'key': data['key'] if data['key'] else '',
                'email': data['email'] if data['email'] else '',
            }
        except:
            public.WriteFile(SETTING_FILE_PATH, json.dumps(default), mode='w+')
        return default

    def get_domain(self, args):
        try:
            res = public.readFile(DOMAIN_FILE_PATH, mode='r')
            response = json.loads(res)
            return {'code': 200, 'data': response['domains']}
        except:
            return {'code': -1, 'msg': '请先配置密钥信息'}

    # 获取防御等级
    def get_safe(self, args):
        default = {
            "wait": 300,  # 负载恢复后的等待周期
            "sleep": 5,  # 检测周期
            "check": 30,  # 持续监测时间
            "load": self.get_safe_load({})['safe_load'],
        }
        if not os.path.exists(SAFE_FILE_PATH):
            public.WriteFile(SAFE_FILE_PATH, json.dumps(default), mode='w+')
        try:
            data = json.loads(public.ReadFile(SAFE_FILE_PATH, mode='r'))
            return {
                "wait": data['wait'] if data['wait'] else 300,
                "sleep": data['sleep'] if data['sleep'] else 5,
                "check": data['check'] if data['check'] else 30,
                "load": data['load'] if data['load'] else self.get_safe_load({})['safe_load'],
            }
        except:
            public.WriteFile(SAFE_FILE_PATH, json.dumps(default), mode='w+')
        return default

    # 启动服务
    def start(self, args):
        self.stop({})  # 先关掉其他的
        public.ExecShell('nohup btpython {} &'.format(
            FIREWALL_SERVICE_PATH))
        return {'code': 200}

    # 停止服务
    def stop(self, args):
        servicePids = public.ExecShell(
            "ps -aux | grep 'btpython " +
            FIREWALL_SERVICE_PATH + "' | awk '{print $2}'"
        )[0].strip().split('\n')[0:-1]
        for i in servicePids:
            public.ExecShell('kill {}'.format(i))
        return {'code': 200}

    # 设置cloudflare key & email
    def set_setting(self, args):
        email = args['email']
        key = args['key']
        if not email or not key:
            return {'code': -1, 'msg': '必填项不能为空'}

        public.WriteFile(SETTING_FILE_PATH, json.dumps({
            'email': email,
            'key': key
        }), mode='w+')
        return {'msg': 'success'}

    # 设置 防护属性
    def set_safe(self, args):
        check = args['check']
        wait = args['wait']
        sleep = args['sleep']
        load = args['load']
        if not check or not wait or not sleep or not load:
            return {'code': -1, 'msg': '必填项不能为空'}
        if int(check) <= 0 or int(wait) <= 0 or int(sleep) <= 0 or float(load) <= 0:
            return {'code': -1, 'msg': '数值必须为大于0的整数'}
        public.WriteFile(SAFE_FILE_PATH, json.dumps({
            "wait": int(wait),  # 负载恢复后的等待周期
            "sleep": int(sleep),  # 检测周期
            "check": int(check),  # 持续监测时间
            "load": round(float(load), 2),
        }), mode='w+')
        return {'code': 200}

    # 设置域名是否自动开盾
    def setDomainStatus(self, args):
        domainName = args['domainName']
        res = json.loads(public.ReadFile(DOMAIN_FILE_PATH, mode="r+"))
        res['domains'][domainName]['status'] = not res['domains'][domainName]['status']
        public.WriteFile(DOMAIN_FILE_PATH, json.dumps(res), mode="w+")
        return {'code': 200}

    # 刷新域名列表
    def refresh_domain(self, args):
        response = Cloudflare().getDomain()
        if response['success']:
            # 获取成功
            count = response['result_info']['count']  # 域名数量
            result = response['result']  # 域名信息

            data = {}  # 初始化data
            index = []  # 域名索引
            for v in result:
                data[v['name']] = {
                    'id': v['id'],
                    'security': "unknow",
                    'status': True
                }
                public.WriteFile(
                    DOMAIN_DNS_BASE_PATH + v['name'] + '.json',
                    "{}",
                    mode='w+'
                )
                index.append(v['name'])
            res = {
                'count': count,
                'domains': data,
                'index': index
            }
            public.WriteFile(DOMAIN_FILE_PATH, json.dumps(res), mode='w+')
            public.WriteLog(PLUGIN_NAME, '刷新域名列表成功')
            return {'code': 200, 'msg': 'success', 'count': count}
        # 获取失败
        public.WriteLog(
            PLUGIN_NAME,
            "尝试登录时遇到错误 > " + json.dumps(response['errors'])
        )
        return {'code': -1, 'msg': "邮箱或API密钥错误<br/>(您可以在面板安全板块查询详细错误信息)"}

    # 刷新所有域名的防护等级
    def refresh_domain_security(self, args):
        domainList = json.loads(public.ReadFile(DOMAIN_FILE_PATH, mode='r'))
        for domainName, v in domainList['domains'].items():
            domainId = v['id']  # 域名信息
            domainInfo = Cloudflare().getSecurity(domainId)
            if domainInfo['success']:  # 获取成功
                domainList['domains'][domainName]['security'] = domainInfo['result']['value']
            else:
                public.WriteLog(
                    PLUGIN_NAME,
                    '获取域名{}防御等级时出现错误 > {}'.format(
                        domainName, json.dumps(domainInfo['errors']))
                )
        public.WriteFile(DOMAIN_FILE_PATH, json.dumps(domainList), mode='w+')
        return {
            'code': 200,
        }

    # 获取安全负载
    def get_safe_load(self, args):
        cpuCount = psutil.cpu_count()
        safe_load = cpuCount * 1.75
        return {'cpu_count': cpuCount, 'safe_load': safe_load}


class Cloudflare:
    __base_url = "https://api.cloudflare.com/client/v4/"

    def __init__(self):
        data = json.loads(public.ReadFile(SETTING_FILE_PATH, mode='r'))
        self.key = data['key'] if data['key'] else ''
        self.email = data['email'] if data['email'] else ''

    # 获取用户域名
    def getDomain(self):
        response = self.__get('zones', {
            'per_page': PER_PAGE  # 拉满
        })
        return response

    # 获取域名防御等级
    def getSecurity(self, domainId):
        response = self.__get(
            'zones/{}/settings/security_level'.format(domainId),
            {}
        )
        return response

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
