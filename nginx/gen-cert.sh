#!/bin/bash
set -e
cd "$(dirname "$0")"
mkdir -p certs
openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
  -keyout certs/forum.local.key -out certs/forum.local.crt \
  -subj "/C=VN/ST=ThaiNguyen/O=LabWebServer/CN=forum.local" \
  -addext "subjectAltName=DNS:forum.local,DNS:*.forum.local"
# Nginx chạy bằng user thường (uid 101) nên cần đọc được key; thư mục certs/ đã nằm trong .gitignore
chmod 644 certs/forum.local.key
echo "Đã tạo certs/forum.local.crt và certs/forum.local.key"
