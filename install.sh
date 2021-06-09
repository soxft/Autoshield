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
	#yum update -y
	yum install procps -y
	#centos

	#apt-get update -y
	apt-get install procps -y
	#ubuntu / debian / ETC.

	#创建初始文件
	mkdir ./config
	mkdir ./config/domaindns
	touch ./config/error.log
	touch ./config/run.log
	touch ./config/temp.json
	touch ./config/domain.json
	echo '{"waittime":"300","sleeptime":"5","checktime":"30","safeload": ""}' >> ./config/safe.json
	touch ./config/setting.json

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
