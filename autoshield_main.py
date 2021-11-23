#!/usr/bin/python
# coding: utf-8

# @Descript cloudflare_autoshield <cloudflare自动开盾>
# @Version 2.0.0_Python_rebuild
# @Author xcsoft<contact@xcsoft.top>
# @Date 2021 11 23

import public
import json

PLUGINNAME = 'autoshield'


class autoshield_main:
    # 获取服务运行状态
    def get_status(self, args):
        result = public.ExecShell('ps -C btpython -f')
        runStatus = PLUGINNAME in result[0]

        return {
            'runStatus': runStatus
        }

    # 获取cloudflare key & email
    def get_setting(self, args):
        return {}

    # 设置cloudflare key & email
    def do_setting(self, args):
        return args
