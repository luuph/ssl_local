## Getting Started
Hướng dẫn setup SSL trên local Nginx

### Prerequisites

1. Tạo Certificate Authority: openssl genrsa -des3 -out rootCA.key 2048
2. Tạo root certificate: openssl req -x509 -new -nodes -key rootCA.key -sha256 -days 1825 -out rootCA.pem
3. Cài đặt Root Certificate cho các trình duyệt
4. Đầu tiên, mở Google Chrome và truy cập vào đường dẫn sau: chrome://settings/certificates. Sau đó, bạn chọn tab Authorities và nhấp vào IMPORT rồi chọn file rootCA.pem mà chúng ta vừa tạo ban nãy.
Sau khi thực hiện xong, bạn sẽ có được hai files là rootCA.key và rootCA.pem. Từ bây giờ, bạn đã trở thành một Certificate Authority và tạo cho SSL local cho bất cứ project nào trên môi trường local của bạn
5. Vào file generate_ssl.php tìm 'Đường dẫn cố định đến CA key và CA cert' và thay thế 2 giá trị path đến 2 file key của bạn đã tạo bên trên
6. Tạo HTTPS cho local site
- Chạy 'php generate_ssl.php path/to/site domain' (Thay thế  bằng path đến project và domain của site)
- Khi chạy có thể sẽ cần nhập lại pass cho key rootCA.pem
- Sau khi chạy xong ta đã có các file cần thiết, vào path/to/site/ssl_config.txt, copy nội dung và đưa vào config nginx của server
- Restart nginx 'systemctl restart nginx'
7. Tận hưởng thành quả hoặc fix lỗi