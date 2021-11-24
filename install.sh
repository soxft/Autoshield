#!/bin/bash
PATH=/www/server/panel/pyenv/bin:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin
export PATH

#配置插件安装目录
install_path=/www/server/panel/plugin/autoshield

#安装
Install()
{
	
	echo '正在安装...'
	#==================================================================
	#依赖安装开始

	yum install procps net-tools -y
	#centos

	apt-get install procps net-tools -y
	#ubuntu / debian / ETC.

	btpython -m pip install requests

	#创建初始文件
	mkdir ./config
	mkdir ./config/domaindns
	mkdir ./log
	#依赖安装结束
	#==================================================================

	echo '================================================'
	echo '安装完成'
}

#卸载
Uninstall()
{
	rm -rf $install_path
}

#操作判断
if [ "${1}" == 'install' ];then
	Install
elif [ "${1}" == 'uninstall' ];then
	Uninstall
else
	echo 'Error!';
fi
