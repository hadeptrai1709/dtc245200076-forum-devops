#!/bin/bash
# Sinh file cấu hình kết nối của mysqld-exporter từ .env (file kết quả không được commit)
set -e
cd "$(dirname "$0")/.."
set -a; . ./.env; set +a
cat > monitoring/mysqld-exporter.cnf << EOT
[client]
user=exporter
password=${MYSQL_EXPORTER_PASSWORD}
host=mysql
port=3306
EOT
chmod 644 monitoring/mysqld-exporter.cnf   # exporter chạy bằng user nobody nên cần đọc được
echo "Đã tạo monitoring/mysqld-exporter.cnf"
