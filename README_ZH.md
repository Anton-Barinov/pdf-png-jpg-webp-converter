# Universal Offline File Converter & Optimizer (通用离线文件转换与优化工具)

<p align="center">
  <strong>100% 隐私安全、自托管、纯本地离线运行的文件格式转换与 PDF 压缩工具。</strong><br>
  无需外部 API • 绝无数据回传 • 5 分钟后自动彻底销毁 • 移动优先极简设计
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

## ⚡ 项目简介

**Universal Converter** 是一款轻量级、开源且高度重视隐私的自托管文件转换服务。所有处理均在您自己的服务器或本地机器上完成。

与商业云转换工具（如 CloudConvert、iLovePDF 等）不同，您的文档**永远不会离开您的服务器**，绝不调用任何第三方接口，并且**在 5 分钟后完全自动粉碎删除**。

---

## 🚀 核心功能

### 🖼️ 图像格式转换与无损压缩
- **PNG $\leftrightarrow$ JPG / JPEG $\leftrightarrow$ WebP：** 相互快速转换，支持自定义输出质量。
- **无损压缩（Lossless）：** 去除图像中敏感的 EXIF、GPS 定位及拍摄元数据，大幅缩减体积而不损画质。
- **图片合并为 PDF：** 一键将多张照片合并为一个精美的多页 PDF 文档。

### 📄 PDF 文档处理工具
- **PDF 体积压缩：** 基于 Ghostscript (`eBook` 150 DPI / `Screen` 72 DPI) 与 QPDF 线性化，深度压缩扫描件与厚重文档。
- **PDF 合并（Merge）：** 将多个 PDF 文件合并为一个文档，支持通过按钮（↑ / ↓）轻松调整页面顺序。
- **PDF 页面拆分（Split）：** 将多页 PDF 拆解为单页文件并打包为 ZIP 下载。
- **PDF 导出为图片：** 将文档各页渲染提取为高清 PNG 或 JPG 图像。

### 🛡️ 顶尖的隐私与安全架构
- **严格 5 分钟生命周期（Zero Retention）：** 4 重自动销毁保障（定时 Cron、PHP 垃圾回收以及即时手动“清空”按钮）。
- **Web 根目录外隔离存储：** 临时文件存放在 Nginx 无法直接读取的 `private/` 目录中，杜绝任何越权下载。
- **Magic Bytes 真实特征码校验：** 仅放行有效合规的图像与 PDF，伪装成图片的恶意脚本（如 `.php`、`.sh`、`.exe`）会被立即物理删除。
- **进程硬化隔离：** Ghostscript 强制开启 `-dSAFER` 安全沙箱，ImageMagick 禁用不安全脚本解析器，所有 CLI 命令均由 `/usr/bin/timeout 60s` 守护执行以防拒绝服务。

### 📱 纯净响应式现代界面
- 界面清爽优雅，告别多余视觉杂乱。
- 原生轻量化 JS/CSS 编写，无冗余框架，手机与电脑皆可秒开秒用。
- 完善的多文件拖拽（Drag & Drop）排队与批量操作。

---

## 🛠️ 快速部署

### 方式 1：Docker Compose 一键启动（推荐 — 30 秒即刻就绪）

容器内已预装全部底层转换工具库（`poppler`、`ghostscript`、`webp`、`optipng`、`jpegoptim`、`qpdf`、`imagemagick`）：

```bash
# 1. 克隆代码库
git clone https://github.com/Anton-Barinov/pdf-png-jpg-webp-converter.git
cd pdf-png-jpg-webp-converter

# 2. 启动容器
docker compose up -d
```

在浏览器打开：**`http://localhost:8080`**

停止运行：
```bash
docker compose down
```

---

### 方式 2：纯净 Linux 脚本自动化安装（Ubuntu / Debian VPS）

如果您使用原生 VPS 机器且不想使用 Docker：

```bash
git clone https://github.com/Anton-Barinov/pdf-png-jpg-webp-converter.git
cd pdf-png-jpg-webp-converter
sudo bash install.sh
```

该安装脚本将自动：
1. 安装 Nginx、PHP-FPM 及所有底层工具包。
2. 配置专属 Nginx 虚拟主机站点。
3. 设立 `750` 安全私有目录权限。
4. 注册每分钟自动清理超期 5 分钟文件的 Cron 计划任务。

---

## ⚙️ 环境变量配置 (.env)

您可通过环境变量或 `.env` 文件定制运行参数：

| 变量名 | 默认值 | 作用说明 |
|---|---|---|
| `APP_NAME` | `Universal Converter` | 页面顶部导航栏显示的名称与网页标题 |
| `TTL_SECONDS` | `300` | 文件在服务器上的保留生存时间（秒，300 秒 = 5 分钟） |
| `MAX_FILE_SIZE_MB` | `100` | 单个文件的最大上传限制（MB） |
| `MAX_SESSION_FILES` | `50` | 单个转换任务会话的最大文件数限制 |
| `DEFAULT_QUALITY` | `90` | JPG 与 WebP 默认压缩输出质量参数（10-100） |

---

## 👤 作者

开发者：**[Anton Barinov](https://github.com/Anton-Barinov)**。

---

## 📄 开源许可证

本项目基于开放的 [MIT License](LICENSE) 授权许可。您可以完全免费地用于个人或商业用途，并享有自由修改与分发的权利。
