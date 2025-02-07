<?php
function createExtensionFile($outputDir, $domainNames)
{
    // Đặt đường dẫn cho file test-ssl.local.ext
    $extensionFile = rtrim($outputDir, '/') . '/test-ssl.local.ext';

    // Nội dung chung của file extension
    $extensionContent = <<<EOL
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment, dataEncipherment
subjectAltName = @alt_names

[alt_names]

EOL;

    // Thêm danh sách các domain
    $i = 1;
    foreach ($domainNames as $domain) {
        $extensionContent .= "DNS.$i = $domain\n";
        $i++;
    }

    // Ghi nội dung vào file
    file_put_contents($extensionFile, $extensionContent);

    echo "Extension file updated successfully at: $extensionFile" . PHP_EOL;
    return $extensionFile;
}

function updateServerCert($caKey, $caCert, $outputDir, $newDomain)
{
    $serverKeyPath = rtrim($outputDir, '/') . '/server.key';
    $serverCsrPath = rtrim($outputDir, '/') . '/server.csr';
    $serverCertPath = rtrim($outputDir, '/') . '/server.crt';
    $extensionFile = rtrim($outputDir, '/') . '/test-ssl.local.ext';

    // Kiểm tra xem chứng chỉ hiện tại có tồn tại không
    if (!file_exists($serverKeyPath) || !file_exists($serverCertPath)) {
        echo "Error: Existing SSL certificate not found. Please generate a certificate first." . PHP_EOL;
        exit(1);
    }

    echo "Updating SSL certificate to add domain: $newDomain" . PHP_EOL;

    // Đọc danh sách domain hiện có từ file extension
    $existingDomains = [];
    if (file_exists($extensionFile)) {
        $content = file_get_contents($extensionFile);
        preg_match_all('/DNS\.\d+ = (.+)/', $content, $matches);
        if (!empty($matches[1])) {
            $existingDomains = $matches[1];
        }
    }

    // Kiểm tra nếu domain đã có trong chứng chỉ
    if (in_array($newDomain, $existingDomains)) {
        echo "Domain $newDomain already exists in the certificate." . PHP_EOL;
        exit(0);
    }

    // Thêm domain mới vào danh sách
    $existingDomains[] = $newDomain;

    // Cập nhật file extension với danh sách domain mới
    createExtensionFile($outputDir, $existingDomains);

    // Tạo CSR mới dựa trên key hiện có
    runCommand(
        "openssl req -new -key $serverKeyPath -out $serverCsrPath -subj \"/C=VN/ST=YourState/L=YourCity/O=YourOrg/CN={$existingDomains[0]}\"",
        "Generating new CSR with updated domains"
    );

    // Ký lại chứng chỉ với danh sách domain mới
    runCommand(
        "openssl x509 -req -in $serverCsrPath -CA $caCert -CAkey $caKey -CAcreateserial -out $serverCertPath -days 3650 -sha256 -extfile $extensionFile",
        "Re-signing certificate with new domain"
    );

    echo PHP_EOL . "SSL certificate updated successfully!" . PHP_EOL;
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
    echo "Starting SSL Certificate and Nginx Config Tool..." . PHP_EOL;

    $caKey = "/var/public/magento/liu/rootCA.key";
    $caCert = "/var/public/magento/liu/rootCA.pem";

    if (!file_exists($caKey) || !file_exists($caCert)) {
        echo "Error: CA files not found." . PHP_EOL;
        exit(1);
    }

    if (php_sapi_name() === 'cli') {
        global $argc, $argv;
        if ($argc < 3) {
            echo "Usage: php generate_ssl_and_nginx.php <mode> <output dir> <domain1> [domain2] ..." . PHP_EOL;
            echo "Modes: generate (create new), update (add domain)" . PHP_EOL;
            exit(1);
        }
        $mode = $argv[1]; 
        $outputDir = $argv[2]; 

        if ($mode === "generate") {
            $domainNames = array_slice($argv, 3);
            list($serverKeyPath, $serverCertPath) = createServerCert($caKey, $caCert, $outputDir, $domainNames);
            createNginxSSLConfig($serverCertPath, $serverKeyPath, $outputDir);
        } elseif ($mode === "update") {
            if ($argc < 4) {
                echo "Usage: php generate_ssl_and_nginx.php update <output dir> <new domain>" . PHP_EOL;
                exit(1);
            }
            $newDomain = $argv[3];
            updateServerCert($caKey, $caCert, $outputDir, $newDomain);
        } else {
            echo "Invalid mode. Use 'generate' or 'update'." . PHP_EOL;
            exit(1);
        }
    } else {
        echo "Please enter mode (generate/update): ";
        $mode = trim(fgets(STDIN));

        echo "Please enter the output directory path: ";
        $outputDir = trim(fgets(STDIN));

        if ($mode === "generate") {
            echo "Enter domain names (comma-separated, e.g., test-ssl.local,example.com): ";
            $domainInput = trim(fgets(STDIN));
            $domainNames = array_map('trim', explode(',', $domainInput));
            list($serverKeyPath, $serverCertPath) = createServerCert($caKey, $caCert, $outputDir, $domainNames);
            createNginxSSLConfig($serverCertPath, $serverKeyPath, $outputDir);
        } elseif ($mode === "update") {
            echo "Enter the new domain to add: ";
            $newDomain = trim(fgets(STDIN));
            updateServerCert($caKey, $caCert, $outputDir, $newDomain);
        } else {
            echo "Invalid mode. Use 'generate' or 'update'." . PHP_EOL;
            exit(1);
        }
    }

    echo PHP_EOL . "Process completed." . PHP_EOL;
}


main();

?>
