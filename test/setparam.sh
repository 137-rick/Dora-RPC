ulimit -n 65535
sysctl net.unix.max_dgram_qlen=100
sysctl net.core.wmem_default=8388608
sysctl net.core.rmem_default=8388608
sysctl net.core.rmem_max=16777216
sysctl net.core.wmem_max=16777216
sysctl kernel.msgmnb=65536


sysctl net.ipv4.tcp_syncookies=1

sysctl net.ipv4.tcp_max_syn_backlog=81920

sysctl net.ipv4.tcp_synack_retries=3

sysctl net.ipv4.tcp_syn_retries=3

sysctl net.ipv4.tcp_fin_timeout=30

sysctl net.ipv4.tcp_keepalive_time=300

sysctl net.ipv4.tcp_tw_reuse=1

sysctl net.ipv4.tcp_tw_recycle=1

sysctl net.ipv4.ip_local_port_range=20000 65000

sysctl net.ipv4.tcp_max_tw_buckets=200000

sysctl net.ipv4.route.max_size=5242880

