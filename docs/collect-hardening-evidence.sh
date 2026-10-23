#!/bin/bash
# Thu thập bằng chứng hardening vào docs/hardening-evidence.txt
cd "$(dirname "$0")/.."
OUT=docs/hardening-evidence.txt

dbq() { docker compose exec -T mysql sh -c 'mysql -u"$MYSQL_APP_USER" -p"$MYSQL_APP_PASSWORD" forum -e "$0" 2>&1 | grep -v Warning' "$1"; }

{
echo "===== $(date) ====="

echo; echo "## 1. Container chạy bằng user thường (non-root)"
for s in app nginx grafana prometheus; do printf "%-12s " "$s"; docker compose exec -T $s id 2>/dev/null | head -1; done

echo; echo "## 2. Bỏ capabilities và chặn leo thang đặc quyền"
docker inspect forum-app forum-nginx --format '{{.Name}}  CapDrop={{.HostConfig.CapDrop}}  SecurityOpt={{.HostConfig.SecurityOpt}}'

echo; echo "## 3. Cô lập mạng"
for n in frontend backend monitoring; do docker network inspect forum-devops_$n --format '{{.Name}}  internal={{.Internal}}'; done
echo "-- Cổng publish của MySQL (rỗng = không publish):"; docker port forum-mysql
echo "-- Các cổng đang lắng nghe trên máy chủ (chỉ 80, 443 và 127.0.0.1:9090):"
ss -tln | grep -E ':(80|443|9090|3306|3000)\s'
echo "-- MySQL (mạng internal) thử kết nối ra internet:"
docker compose exec -T mysql bash -c 'timeout 3 bash -c "</dev/tcp/1.1.1.1/53" 2>/dev/null && echo "RA ĐƯỢC INTERNET (sai)" || echo "BỊ CHẶN (đúng)"'

echo; echo "## 4. Mật khẩu mạnh, không commit lên GitHub"
awk -F= 'NF==2 && $1 ~ /PASSWORD/ {printf "%-26s %d ký tự\n", $1, length($2)}' .env
echo -n ".env có nằm trong git không? "; git ls-files --error-unmatch .env >/dev/null 2>&1 && echo "CÓ (sai)" || echo "KHÔNG (đúng)"
git check-ignore -v .env

echo; echo "## 5. Quyền DB tối thiểu (user ứng dụng)"
dbq "SHOW GRANTS"
echo "> DELETE FROM comments WHERE id=0"; dbq "DELETE FROM comments WHERE id=0"
echo "> DROP TABLE comments";             dbq "DROP TABLE comments"

echo; echo "## 6. Security headers và TLS của Nginx"
echo "-- HTTP chuyển hướng sang HTTPS:"; curl -sI http://forum.local | head -3
echo "-- Header phản hồi:"
curl -ksI https://forum.local | grep -iE '^(HTTP|server|strict-transport|x-frame|x-content|content-security|referrer|permissions)'
echo "-- Giao thức TLS thương lượng:"
echo | openssl s_client -connect 127.0.0.1:443 -servername forum.local 2>/dev/null | grep -E "Protocol|Cipher is"
echo "-- phpMyAdmin và Grafana chỉ cho mạng nội bộ:"
grep -nE "^\s*(allow|deny)" nginx/conf.d/forum.conf nginx/conf.d/grafana.conf

echo; echo "## 7. Thử SQL Injection vào form đăng nhập"
jar=$(mktemp)
tok=$(curl -ks -c $jar https://forum.local/login.php | grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//; s/"//')
code=$(curl -ks -b $jar -o /dev/null -w "%{http_code}" --data-urlencode "csrf=$tok" --data-urlencode "username=' OR '1'='1" --data-urlencode "password=x" https://forum.local/login.php)
echo "Chuỗi tấn công: ' OR '1'='1  ->  HTTP $code  (200 = bị từ chối, hiện lại form; 302 = đăng nhập được = SAI)"
[ "$code" = "429" ] && echo "(đang bị giới hạn tần suất, hãy chờ 1 phút rồi chạy lại script)"

echo; echo "## 8. Giới hạn tần suất đăng nhập (kỳ vọng xuất hiện mã 429)"
for i in $(seq 1 12); do curl -ks -o /dev/null -w "%{http_code} " https://forum.local/login.php; done; echo

echo; echo "## 9. Image được ghim phiên bản (không dùng latest)"
grep -n "image:" docker-compose.yml
} 2>&1 | tee "$OUT"
