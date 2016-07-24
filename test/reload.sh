# 根据当前目录的pid，发送kill信号，重启task和worker
cat dorarpcmanager.pid|xargs kill -USR1