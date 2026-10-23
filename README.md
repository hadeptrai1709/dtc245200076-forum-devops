# Forum DevOps (Đề 15: Diễn đàn)

Đề tài thực hành môn Triển khai và Quản trị Hệ thống Phần mềm.
Toàn bộ hệ thống triển khai bằng Docker Compose.

- **Sinh viên:** Nông Tiến Hà - MSSV: dtc245200076
- **Chủ đề:** Diễn đàn / Cộng đồng (chủ đề, bài viết, bình luận, người dùng)

## Kiến trúc

| Thành phần | Công nghệ | Mạng |
|---|---|---|
| Reverse proxy (cổng vào duy nhất, 80/443) | Nginx (unprivileged) | frontend |
| Ứng dụng diễn đàn | PHP 8.3 + Apache (non-root, cổng 8080) | frontend, backend |
| Cơ sở dữ liệu | MySQL 8.0 | backend (internal) |
| Công cụ quản lý DB | phpMyAdmin | frontend, backend |
| Giám sát | Prometheus, Grafana, cAdvisor, node-exporter, mysqld-exporter, nginx-exporter | monitoring (+ cầu nối frontend/backend) |
| Log tập trung | Loki 3.6.0 + Promtail 3.6.0 | monitoring |

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
# Cách nhanh: tạo mật khẩu ngẫu nhiên cho 4 biến
for v in MYSQL_ROOT_PASSWORD MYSQL_APP_PASSWORD MYSQL_EXPORTER_PASSWORD GRAFANA_ADMIN_PASSWORD; do
  sed -i "s/^$v=.*/$v=$(openssl rand -hex 16)/" .env
done

# 2. Tạo chứng chỉ HTTPS tự ký
./nginx/gen-cert.sh
./monitoring/gen-exporter-cnf.sh   # sinh file cấu hình cho exporter MySQL

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
| Grafana | https://grafana.forum.local | user `admin`, mật khẩu `GRAFANA_ADMIN_PASSWORD` trong `.env`, chỉ cho phép từ mạng nội bộ |
| Prometheus | http://127.0.0.1:9090 | chỉ mở cho chính máy chạy Docker |

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

## Giám sát (Prometheus + Grafana)

| Đối tượng giám sát | Exporter | Job Prometheus |
|---|---|---|
| Máy chủ (host) | node-exporter | `node-exporter` |
| Container | cAdvisor 0.56.2 | `cadvisor` |
| Database | mysqld-exporter (user `exporter` quyền tối thiểu) | `mysql` |
| Web server | nginx-exporter (đọc `stub_status`) | `nginx` |

- Prometheus thu thập mỗi 15 giây và giữ dữ liệu 7 ngày.
- Grafana tự có nguồn dữ liệu Prometheus (provisioning). Cần nhập thủ công các dashboard
  (Dashboards → New → Import):

| ID | Dashboard |
|---|---|
| 1860 | Node Exporter Full |
| 14282 | cAdvisor / Docker |
| 7362 | MySQL Overview |
| 12708 | NGINX exporter |

Lưu ý: cAdvisor từ bản 0.56.2 mới chạy ổn với Docker 29 (bản 0.56.1 bị lỗi nil pointer khi khởi động).

## Log tập trung (Loki + Promtail)

- **Promtail** đọc log của mọi container thuộc dự án qua Docker socket và gắn nhãn `container`, `service`, `job`.
- **Loki** lưu log (chỉ đánh chỉ mục theo nhãn). Grafana có sẵn nguồn dữ liệu Loki (provisioning).
- Service `loki-init` chạy một lần để chỉnh quyền volume cho Loki (uid 10001); nếu thiếu bước này Loki sẽ lỗi `permission denied`.
- Ứng dụng ghi sự kiện `login_success` / `login_failed` ra `stderr`, Nginx ghi access log ra `stdout`.

Truy vấn LogQL mẫu (Grafana → Explore → Loki):

| Mục đích | LogQL |
|---|---|
| Đăng nhập sai | `{service="app"} \|= "login_failed"` |
| Request 404 | `{service="nginx"} \|= " 404 "` |
| Lượng log mỗi dịch vụ | `sum by (service) (count_over_time({job="docker"}[5m]))` |
| Bị giới hạn tần suất | `{service="nginx"} \|= " 429 "` |

Hạn chế đã biết: Promtail đã hết vòng đời (EOL) từ 2/3/2026, Grafana khuyến nghị chuyển sang Alloy.
Promtail cần mount `docker.sock` (quyền tương đương root trên máy chủ).

## Bằng chứng hardening

Chạy `./docs/collect-hardening-evidence.sh` để kiểm tra tự động và ghi kết quả vào `docs/hardening-evidence.txt`
(non-root, cap_drop, network internal, quyền DB tối thiểu, security headers, TLS, rate limit, thử SQL Injection).
