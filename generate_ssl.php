<?php
function createExtensionFile($outputDir, $domainName)
{
    // Đặt đường dẫn cho file test-ssl.local.ext
    $extensionFile = rtrim($outputDir, '/') . '/test-ssl.local.ext';

    // Nội dung của file test-ssl.local.ext
    $extensionContent = <<<EOL
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
subjectAltName = @alt_names

[alt_names]
DNS.1 = $domainName
EOL;

    // Ghi nội dung vào file
    file_put_contents($extensionFile, $extensionContent);

    echo "Extension file created successfully at: $extensionFile" . PHP_EOL;
    return $extensionFile;
}
function runCommand($command, $description = "")
{
    echo "Running: " . ($description ? $description : $command) . PHP_EOL;
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        echo "Error executing command: $command" . PHP_EOL;
        exit($return_var);
    }
    echo implode(PHP_EOL, $output) . PHP_EOL;
}

function createServerCert($caKey, $caCert, $outputDir, $domainName, $serverKey = "server.key", $serverCsr = "server.csr", $serverCert = "server.crt", $serverExt= "server.ext")
{
    // Tạo đường dẫn đầy đủ cho các file output
    $serverKeyPath = rtrim($outputDir, '/') . '/' . $serverKey;
    $serverCsrPath = rtrim($outputDir, '/') . '/' . $serverCsr;
    $serverCertPath = rtrim($outputDir, '/') . '/' . $serverCert;

    // Đảm bảo thư mục output tồn tại
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Tạo khóa riêng cho server
    runCommand("openssl genrsa -out $serverKeyPath 2048", "Generating server private key");

    // Tạo CSR cho server
    runCommand(
        "openssl req -new -key $serverKeyPath -out $serverCsrPath -subj \"/C=VN/ST=YourState/L=YourCity/O=YourOrg/CN=localhost\"",
        "Generating server CSR"
    );

    // Ký chứng chỉ server bằng CA và sử dụng file extension
    $extensionFile = createExtensionFile($outputDir, $domainName); // Tạo file extension
    runCommand(
        "openssl x509 -req -in $serverCsrPath -CA $caCert -CAkey $caKey -CAcreateserial -out $serverCertPath -days 3650 -sha256 -extfile $extensionFile",
        "Signing server certificate with CA and using extension file"
    );

    echo PHP_EOL . "Server certificate and key generated successfully!" . PHP_EOL;
    echo "Server Key: $serverKeyPath" . PHP_EOL;
    echo "Server CSR: $serverCsrPath" . PHP_EOL;
    echo "Server Certificate: $serverCertPath" . PHP_EOL;
    
    return [$serverKeyPath, $serverCertPath];
}

function createNginxSSLConfig($certPath, $keyPath, $outputDir, $fileName = "ssl_config.txt") {
    // Đảm bảo thư mục output tồn tại
    if (!file_exists($outputDir)) {
        mkdir($outputDir, 0777, true);
    }

    // Tạo nội dung cấu hình Nginx
    $configContent = <<<EOL
listen 443 ssl;
ssl_certificate  $certPath;
ssl_certificate_key  $keyPath;
ssl_protocols     TLSv1 TLSv1.1 TLSv1.2;
ssl_ciphers       HIGH:!aNULL:!MD5;
EOL;

    // Đặt đường dẫn đầy đủ của file cấu hình
    $outputFilePath = rtrim($outputDir, '/') . '/' . $fileName;

    // Ghi nội dung vào file
    file_put_contents($outputFilePath, $configContent);

    echo "Nginx SSL config file created successfully at: $outputFilePath" . PHP_EOL;
}

function main()
{
    echo "Starting SSL Certificate and Nginx Config Tool with Existing CA..." . PHP_EOL;

    // Đường dẫn cố định đến CA key và CA cert
    $caKey = "/var/public/magento/liu/rootCA.key"; // Đường dẫn đến CA key (myCA.key)
    $caCert = "/var/public/magento/liu/rootCA.pem"; // Đường dẫn đến CA cert (myCA.pem)

    // Kiểm tra sự tồn tại của các file CA
    if (!file_exists($caKey) || !file_exists($caCert)) {
        echo "Error: CA files not found. Please ensure the CA key and certificate exist." . PHP_EOL;
        exit(1);
    }

    // Kiểm tra nếu chạy trong CLI
    if (php_sapi_name() === 'cli') {
        global $argc, $argv;
        if ($argc < 3) {
            echo "Usage: php generate_ssl_and_nginx.php <output dir> <domain name>" . PHP_EOL;
            exit(1);
        }
        $outputDir = $argv[1]; // Đường dẫn thư mục output từ tham số dòng lệnh
        $domainName = $argv[2]; // Tên miền từ tham số dòng lệnh
    } else {
        echo "Please enter the output directory path: ";
        $outputDir = trim(fgets(STDIN));

        echo "Please enter the domain name (e.g., test-ssl.local): ";
        $domainName = trim(fgets(STDIN));
    }

    // Tạo chứng chỉ server và lấy đường dẫn của chúng
    list($serverKeyPath, $serverCertPath) = createServerCert($caKey, $caCert, $outputDir, $domainName);

    // Tạo file cấu hình Nginx
    createNginxSSLConfig($serverCertPath, $serverKeyPath, $outputDir);

    echo PHP_EOL . "Process completed." . PHP_EOL;
}

main();

?>
