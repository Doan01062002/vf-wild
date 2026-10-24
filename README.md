# vfwild.vn

Website landing VinFast VF Wild của ChungAuto, kèm blog và trang quản trị `/quan-tri/` (PHP 8.1, không dùng framework, không cơ sở dữ liệu).

## Cấu trúc

| Thư mục | Nội dung |
|---|---|
| `vfwild/` | Toàn bộ mã nguồn web đẩy lên VPS (`/var/www/vfwild`): các trang HTML tĩnh, `css/`, `js/`, `img/`, `lead.php`, `tracking.php` |
| `vfwild/quan-tri/` | Trang quản trị: bài viết, chuyên mục, thư viện ảnh, người dùng, tự động viết bài theo Google Sheet (`blog-auto*.php`), kiểm tra nội dung, review và học |
| `vfwild/blog/` | Blog HTML tĩnh, được dựng lại từ dữ liệu bài viết (`php quan-tri/index.php --rebuild-blog`) |
| `tools/` | Script dựng trang và video hướng dẫn (`tools/video/`) |
| `blog_src/` | Bản nháp Markdown đầu tiên của các bài blog |
| `Huong-dan-su-dung-vfwild.docx`, `Video-huong-dan-quan-tri-vfwild.mp4` | Tài liệu hướng dẫn sử dụng |
| `Bao-cao-bai-chungauto.xlsx` | Báo cáo rà soát các bài trên chungauto.vn |

## Không có trong kho này

Dữ liệu thật và khóa bí mật nằm ngoài thư mục web, ở `/var/www/vfwild-private` trên VPS (biến môi trường `VFW_PRIVATE`): bài viết Markdown, leads, tài khoản admin, khóa robot Google, khóa API AI, cài đặt tự động viết bài. Các thư mục `secrets/`, `private-local/` và `backup/` ở máy local cũng bị loại qua `.gitignore`.

## Chạy thử ở máy local

```bash
VFW_PRIVATE=D:/Chungauto/vfwild/private-local php -S 127.0.0.1:8090 -t vfwild
```

Ảnh cần PHP có GD (`php -d extension=gd`).
