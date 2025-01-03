# Getting Started

## Setup SSL on Local Nginx

### Prerequisites

1. **Create a Certificate Authority (CA):**
   ```bash
   openssl genrsa -des3 -out rootCA.key 2048
   ```

2. **Generate the Root Certificate:**
   ```bash
   openssl req -x509 -new -nodes -key rootCA.key -sha256 -days 1825 -out rootCA.pem
   ```

3. **Install the Root Certificate in Browsers:**
   - **Google Chrome:**
     1. Open Chrome and navigate to: `chrome://settings/certificates`.
     2. Go to the **Authorities** tab and click **IMPORT**.
     3. Select the `rootCA.pem` file you just created.

   After completing these steps, you will have two files:
   - `rootCA.key`
   - `rootCA.pem`

   Congratulations! You are now a Certificate Authority and can create SSL certificates for any local project.

---

### Steps to Setup HTTPS for a Local Site

1. **Update Paths in `generate_ssl.php`:**
   - Locate the section for **CA key and CA cert paths** in `generate_ssl.php`.
   - Replace the placeholders with the paths to the `rootCA.key` and `rootCA.pem` files you generated earlier.

2. **Generate SSL for Your Local Site:**
   - Run the following command, replacing `<path-to-site>` and `<domain>` with your project's path and domain:
     ```bash
     php generate_ssl.php <path-to-site> <domain>
     ```
   - You may be prompted to enter the passphrase for the `rootCA.pem` key during this process.

3. **Configure Nginx:**
   - After running the script, locate the `ssl_config.txt` file in your project directory.
   - Copy its contents and paste them into the Nginx configuration for your server.

4. **Restart Nginx:**
   ```bash
   systemctl restart nginx
   ```

---

### Troubleshooting

- If you encounter errors, double-check the paths and ensure the passphrase for the root key is correct.

---

### Enjoy!
You have successfully set up SSL for your local Nginx server. Test your site and bask in the glory of HTTPS!
