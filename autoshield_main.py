#!/usr/bin/python
# coding: utf-8

# @Descript cloudflare_autoshield <cloudflare自动开盾>
# @Version 2.0.0_Python_rebuild
# @Author xcsoft<contact@xcsoft.top>
# @Date 2021 11 23

import public
import os
import json
import requests

PLUGIN_NAME = 'autoshield'
FIREWALL_SERVICE_NAME = 'autoshield.py'
PLUGIN_PATH = "/www/server/panel/plugin/{}/".format(PLUGIN_NAME)

SETTING_FILE_PATH = PLUGIN_PATH + 'config/setting.json'  # 设置temp文件路径
DOMAIN_FILE_PATH = PLUGIN_PATH + 'config/domain.json'  # 用户域名temp文件路径

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
        if not os.path.exists(SETTING_FILE_PATH):
            return {'key': '', 'email': ''}
        try:
            data = json.loads(public.ReadFile(SETTING_FILE_PATH, mode='r'))
            return {
                'key': data['key'] if data['key'] else '',
                'email': data['email'] if data['email'] else '',
            }
        except:
            public.WriteFile(SETTING_FILE_PATH, json.dumps({
                'email': "",
                'cfkey': "",
            }), mode='w+')

        return {'key': '', 'email': ''}

    # 设置cloudflare key & email
    def do_setting(self, args):
        email = args['email']
        key = args['key']
        if not email or not key:
            return {'status': False, 'msg': '<h3>必填项不能为空</h3>'}

        public.WriteFile(SETTING_FILE_PATH, json.dumps({
            'email': email,
            'key': key
        }), mode='w+')
        return {'status': True, 'msg': 'success'}

    def get_domain(self, args):
        try:
            res = public.readFile(DOMAIN_FILE_PATH, mode='r')
            response = json.loads(res)
            return response
        except:
            return {'status'}

    def refresh_domain(self, args):
        cf = Cloudflare()
        response = cf.getDomain()
        if response['success']:
            # 获取成功
            count = response['result_info']['count']  # 域名数量
            result = response['result']  # 域名信息

            data = {}  # 初始化data
            for v in result:
                data[v['name']] = v['id']
            res = {
                'count': count,
                'domains': data,
            }
            public.WriteFile(DOMAIN_FILE_PATH, json.dumps(res), mode='w+')
            return {'code': 200, 'msg': 'success'}
        # 获取失败
        return {'status': False, 'msg': "<h3>邮箱或API密钥错误</h3> <br/>返回信息:<br/>" + json.dumps(response['errors'])}


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
