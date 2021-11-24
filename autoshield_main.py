#!/usr/bin/python
# coding: utf-8

# @Descript cloudflare_autoshield <cloudflare自动开盾>
# @Version 2.0.0_Python_rebuild
# @Author xcsoft<contact@xcsoft.top>
# @Date 2021 11 23

from typing import Dict
import public
import os
import json
import requests


class autoshield_main:
    __plugin_name = 'autoshield'
    __firewall_filename = 'autoshield.py'
    __plugin_path = "/www/server/panel/plugin/{}/".format(__plugin_name)

    __setting = __plugin_path + 'config/setting.json'  # setting文件路径

    # 构造方法
    def __init__(self) -> None:
        config_path = self.__plugin_path + 'config/'
        dns_path = self.__plugin_path + 'config/dns/'
        if not os.path.isdir(config_path):
            os.makedirs(config_path, 755)
        if not os.path.isdir(dns_path):
            os.makedirs(dns_path, 755)
        pass

    # 获取服务运行状态
    def get_status(self, args) -> Dict:
        result = public.ExecShell('ps -C btpython -f')
        runStatus = self.__firewall_filename in result[0]
        return {'runStatus': runStatus}

    # 获取cloudflare key & email
    def get_setting(self, args) -> Dict:
        if not os.path.exists(self.__setting):
            return {'key': '', 'email': ''}
        try:
            data = json.loads(public.ReadFile(self.__setting, mode='r'))
            return {
                'key': data['key'] if data['key'] else '',
                'email': data['email'] if data['email'] else '',
            }
        except:
            public.WriteFile(self.__setting, json.dumps({
                'email': "",
                'cfkey': "",
            }), mode='w+')

        return {'key': '', 'email': ''}

    # 设置cloudflare key & email
    def do_setting(self, args) -> Dict:
        email = args['email']
        key = args['key']
        if not email:
            return {'code': -1, 'msg': '必填项不能为空'}
        if not key:
            return {'code': -1, 'msg': '必填项不能为空'}

        public.WriteFile(self.__setting, json.dumps({
            'email': email,
            'key': key
        }), mode='w+')
        return {'code': 200, 'msg': '保存成功'}


class Cloudflare:
    __base_url = "https://api.cloudflare.com/client/v4/"

    def __init__(self) -> None:
        data = json.loads(public.ReadFile(self.__setting, mode='r'))
        self.key = data['key'] if data['key'] else ''
        self.email = data['email'] if data['email'] else ''

    def getUserInfo(self):
        pass

    def __getHeader(self) -> Dict:
        return {
            "Content-Type: application/json",
            "X-Auth-Key: {apikey}".format(apikey=self.key),
            "X-Auth-Email: {email}".format(email=self.email)
        }

    def __post(self, url, data) -> Dict:
        payload = json.dumps(data)
        response = requests.post(
            self.__base_url + url,
            data=payload,
            headers=self.__getHeader
        )
        return json.loads(response.json)
