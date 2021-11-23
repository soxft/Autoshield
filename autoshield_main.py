#!/usr/bin/python
# coding: utf-8

# @Descript cloudflare_autoshield <cloudflare自动开盾>
# @Version 2.0.0_Python_rebuild
# @Author xcsoft<contact@xcsoft.top>
# @Date 2021 11 23

import public
import os
import json


class autoshield_main:
    __plugin_name = 'autoshield'
    __firewall_filename = 'autoshield.py'
    __plugin_path = "/www/server/panel/plugin/{}/".format(__plugin_name)

    __setting = __plugin_path + 'config/setting.json'  # setting文件路径

    # 构造方法
    def __init__(self):
        config_path = self.__plugin_path + 'config/'
        dns_path = self.__plugin_path + 'config/dns/'
        if not os.path.isdir(config_path):
            os.makedirs(config_path, 755)
        if not os.path.isdir(dns_path):
            os.makedirs(dns_path, 755)
        pass

    # 获取服务运行状态
    def get_status(self, args):
        result = public.ExecShell('ps -C btpython -f')
        runStatus = self.__firewall_filename in result[0]
        return {'runStatus': runStatus}

    # 获取cloudflare key & email
    def get_setting(self, args):
        return {}

    # 设置cloudflare key & email
    def do_setting(self, args):
        cfemail = args['cfemail']
        cfkey = args['cfkey']
        if not cfemail:
            return {'code': -1, 'msg': '必填项不能为空'}
        if not cfkey:
            return {'code': -1, 'msg': '必填项不能为空'}

        res = public.WriteFile(self.__setting, json.dumps({
            'email': cfemail,
            'cfkey': cfkey
        }), mode='w+')
        return {'1': res}
