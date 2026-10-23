# Forum DevOps (Đề 15: Diễn đàn)

Đề tài thực hành môn Triển khai và Quản trị Hệ thống Phần mềm.
Toàn bộ hệ thống triển khai bằng Docker Compose.

- **Sinh viên:** <Họ tên> - MSSV: dtc245200076
- **Chủ đề:** Diễn đàn / Cộng đồng (chủ đề, bài viết, bình luận, người dùng)

## Kiến trúc

| Thành phần | Công nghệ | Mạng |
|---|---|---|
| Reverse proxy (cổng vào duy nhất, 80/443) | Nginx (unprivileged) | frontend |
| Ứng dụng diễn đàn | PHP 8.3 + Apache (non-root, cổng 8080) | frontend, backend |
| Cơ sở dữ liệu | MySQL 8.0 | backend (internal) |
| Công cụ quản lý DB | phpMyAdmin | frontend, backend |

- `frontend`: mạng bridge thường, Nginx nói chuyện với app và phpMyAdmin.
- `backend`: mạng `internal: true`, MySQL không ra được internet và không publish cổng nào.

## Cấu trúc thư mục

## Hướng dẫn chạy

Yêu cầu: Docker và Docker Compose v2.

```bash
git clone git@github.com:hadeptrai1709/dtc245200076-forum-devops.git
cd dtc245200076-forum-devops

# 1. Tạo file mật khẩu (sinh mật khẩu ngẫu nhiên)
cp .env.example .env
# Cách nhanh: tạo mật khẩu ngẫu nhiên cho 3 biến
for v in MYSQL_ROOT_PASSWORD MYSQL_APP_PASSWORD MYSQL_EXPORTER_PASSWORD; do
  sed -i "s/^$v=.*/$v=$(openssl rand -hex 16)/" .env
done

# 2. Tạo chứng chỉ HTTPS tự ký
./nginx/gen-cert.sh

# 3. Khai báo tên miền (trên máy chạy trình duyệt)
echo "127.0.0.1 forum.local pma.forum.local grafana.forum.local" | sudo tee -a /etc/hosts

# 4. Chạy hệ thống
docker compose up -d --build
docker compose ps
```

Nếu mở trình duyệt từ máy khác (ví dụ Windows), thay `127.0.0.1` bằng IP của máy chạy Docker.

## Địa chỉ truy cập

| Dịch vụ | URL | Ghi chú |
|---|---|---|
| Diễn đàn | https://forum.local | chứng chỉ tự ký, trình duyệt sẽ cảnh báo, chọn tiếp tục |
| phpMyAdmin | https://pma.forum.local | đăng nhập bằng `MYSQL_APP_USER` / `MYSQL_APP_PASSWORD` trong `.env`, chỉ cho phép từ mạng nội bộ |

## Hardening đã áp dụng

- Container app và Nginx chạy bằng user thường (www-data, nginx), `cap_drop: ALL`, `no-new-privileges`.
- Network isolation: MySQL nằm trong mạng `internal`, không publish cổng.
- Mật khẩu ngẫu nhiên 32 ký tự trong `.env` (không commit), có `.env.example`.
- Quyền DB tối thiểu: user ứng dụng chỉ có SELECT/INSERT/UPDATE, không có DELETE/DROP.
- Security headers Nginx (CSP, X-Frame-Options, nosniff, Referrer-Policy, HSTS), ẩn phiên bản, TLS 1.2+.
- Giới hạn tần suất đăng nhập (rate limit) ở Nginx.
- Mã nguồn: prepared statements, `password_hash`, token CSRF, escape đầu ra (chống XSS).

## Lộ trình commit

1. Forum + MySQL + phpMyAdmin + Nginx reverse proxy HTTPS (commit này)
2. Prometheus + Grafana giám sát
3. Loki + Promtail log tập trung
