# OS
uname -a
cat /etc/os-release

# CPU
lscpu | grep -E "Model name|^CPU\(s\)|Thread|MHZ"

# RAM
free -h

# Disque
df -h