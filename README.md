# Universal Offline File Converter & Optimizer

<p align="center">
  <strong>100% Private, Self-Hosted, Offline File Converter & Optimizer.</strong><br>
  Zero external APIs • Zero Telemetry • Strict 5-Minute Auto-Purge • Clean & Responsive UI
</p>

<p align="center">
  <a href="README.md">English</a> •
  <a href="README_RU.md">Русский</a> •
  <a href="README_ZH.md">中文</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED?logo=docker&logoColor=white" alt="Docker Ready">
  <img src="https://img.shields.io/badge/License-MIT-green.svg" alt="License: MIT">
  <img src="https://img.shields.io/badge/Privacy-100%25%20Offline-blue" alt="100% Offline">
</p>

---

## ⚡ Overview

**Universal Converter** is a self-hosted, lightweight web application designed for converting and optimizing images and PDF documents directly on your server or personal computer. 

Unlike commercial cloud converters (like CloudConvert, iLovePDF, or TinyPNG), your files **never leave your server**, are never sent to third-party APIs, and are **permanently wiped after 5 minutes**.

---

## 🚀 Key Features

### 🖼️ Image Conversion & Optimization
- **PNG $\leftrightarrow$ JPG / JPEG $\leftrightarrow$ WebP:** Fast cross-format conversion with customizable quality.
- **Lossless Compression:** Shrink PNG, JPG, and WebP images without visual loss (`optipng`, `jpegoptim`, `cwebp -lossless`), stripping privacy-sensitive EXIF and GPS metadata.
- **Photos to PDF:** Merge any group of images into a single multi-page PDF document in one click.

### 📄 PDF Document Tools
- **PDF Compression & Optimization:** Reduce file size of scanned and heavy PDF documents using Ghostscript presets (`eBook` 150 DPI, `Screen` 72 DPI) with automatic QPDF linearization.
- **Merge PDFs:** Combine multiple PDF documents into a single document with interactive page reordering (↑ / ↓ buttons).
- **Split PDF:** Break multi-page PDFs into separate single-page documents packed into a ZIP archive.
- **Export PDF to Images:** Extract PDF pages into high-resolution PNG or JPG images.

### 🛡️ Privacy & Security First
- **Zero Retention (Strict 5-Minute TTL):** Uploaded files and conversion results are permanently shredded after 5 minutes by a multi-tier cleanup system (Cron worker, background GC, and an instant manual "Clear" button).
- **Storage Outside Webroot:** Temporary files are stored in an isolated directory unreachable by direct HTTP requests.
- **Magic Bytes Validation:** Every upload is verified by binary magic bytes (`finfo`). Renamed executables (`.php`, `.sh`, `.exe`) or spoofed files are instantly deleted from disk.
- **Execution Hardening:** Ghostscript is locked with `-dSAFER`. ImageMagick vector scripts (`MVG`, `MSL`) and remote protocols (`HTTP`, `FTP`, `HTTPS`) are disabled. All CLI tasks run under `/usr/bin/timeout 60s`.

### 📱 Clean & Responsive Interface
- Lightweight, distraction-free interface built with pure vanilla JS/CSS (zero heavy frontend frameworks).
- Instant multi-file Drag & Drop queue.
- Tab-filtered workspaces (`All`, `Images`, `PDF`, `Compression`).
- Synchronized global and per-file format selectors.

---

## 🛠️ Quick Start

### Method 1: Docker Compose (Recommended — Ready in 30 Seconds)

Docker is the easiest way to run the service with all Linux dependencies (`poppler`, `ghostscript`, `webp`, `optipng`, `jpegoptim`, `qpdf`, `imagemagick`) pre-configured inside a secure container:

```bash
# 1. Clone repository
git clone https://github.com/Anton-Barinov/pdf-png-jpg-webp-converter.git
cd pdf-png-jpg-webp-converter

# 2. Start container
docker compose up -d
```

Open your browser at: **`http://localhost:8080`**

To stop the container:
```bash
docker compose down
```

---

### Method 2: Automated Bare-Metal Install (Ubuntu / Debian VPS)

If you have a Linux server (Ubuntu 20.04/22.04/24.04 or Debian 11/12) and want to run directly without Docker:

```bash
git clone https://github.com/Anton-Barinov/pdf-png-jpg-webp-converter.git
cd pdf-png-jpg-webp-converter
sudo bash install.sh
```

The installer automatically:
1. Installs all packages (`nginx`, `php-fpm`, `poppler-utils`, `webp`, `optipng`, `jpegoptim`, `qpdf`, `ghostscript`, `imagemagick`).
2. Configures Nginx virtual host with your custom domain or IP.
3. Sets up directory permissions (`750` private storage).
4. Configures a 1-minute system Cron job to purge files older than 5 minutes.

---

### Method 3: Manual Installation

<details>
<summary>Click to view manual installation steps</summary>

1. **Install dependencies:**
   ```bash
   sudo apt-get update
   sudo apt-get install -y nginx php-fpm php-zip php-mbstring \
       poppler-utils webp optipng jpegoptim qpdf ghostscript imagemagick timeout
   ```

2. **Copy project files:**
   ```bash
   sudo mkdir -p /var/www/converter/public_html
   sudo mkdir -p /var/www/converter/private/storage/sessions
   sudo cp -r src/index.php src/api.php src/config.php src/assets /var/www/converter/public_html/
   sudo cp -r src/lib /var/www/converter/private/
   sudo chown -R www-data:www-data /var/www/converter
   sudo chmod -R 750 /var/www/converter/private
   ```

3. **Configure Nginx:**
   Point your Nginx server root to `/var/www/converter/public_html`. Ensure PHP-FPM socket is linked.

4. **Add 5-minute purge Cron:**
   ```bash
   echo "* * * * * root find /var/www/converter/private/storage/sessions/ -mindepth 1 -maxdepth 1 -mmin +5 -exec rm -rf {} + >/dev/null 2>&1" | sudo tee /etc/cron.d/converter-cleanup
   ```
</details>

---

## ⚙️ Configuration

Settings can be customized via environment variables or a `.env` file:

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `Universal Converter` | Custom name shown in header and page title |
| `TTL_SECONDS` | `300` | Lifetime of files in seconds (300s = 5 minutes) |
| `MAX_FILE_SIZE_MB` | `100` | Maximum allowed file size per upload in MB |
| `MAX_SESSION_FILES` | `50` | Maximum number of files in a single batch |
| `DEFAULT_QUALITY` | `90` | Default compression quality for JPG and WebP |

---

## 🔒 Security Architecture

1. **Zero External Communication:** The app does not ping any third-party telemetry, CDNs, or external APIs.
2. **Optional Kernel Air-Gap:** On a dedicated server, you can strictly block all outbound traffic for the web server user:
   ```bash
   sudo iptables -I OUTPUT 1 -m owner --uid-owner www-data ! -d 127.0.0.1 -j REJECT
   ```
3. **MIME Verification:** Uploads are checked via binary signatures (`FILEINFO_MIME_TYPE`). Only `image/png`, `image/jpeg`, `image/webp`, and `application/pdf` are accepted.
4. **Path Traversal Protection:** Session tokens are validated with `/^[a-f0-9]{32}$/` and file download paths are resolved with `realpath()` with boundary verification.
5. **CLI Process Timeout:** Every shell execution is wrapped with `/usr/bin/timeout 60s` to prevent decompression bombs or infinite loops.

---

## 👤 Author

Developed by **[Anton Barinov](https://github.com/Anton-Barinov)**.

---

## 📄 License

This project is licensed under the [MIT License](LICENSE). You are free to use, modify, and distribute it for both personal and commercial purposes.
