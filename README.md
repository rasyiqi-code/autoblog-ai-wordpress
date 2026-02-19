# AutoBlog AI Plugin

**AutoBlog AI** adalah plugin WordPress canggih yang mengotomatiskan pembuatan konten blog menggunakan kekuatan Artificial Intelligence (AI). Plugin ini mampu mencari topik terkini, meriset konten dari berbagai sumber (RSS, Web, Search Engine), dan menulis artikel berkualitas tinggi yang lengkap dengan gambar *thumbnail* secara otomatis.

## 🚀 Fitur Utama

### 1. **Multi-Provider AI Core**
Plugin ini mendukung berbagai penyedia AI terkemuka untuk fleksibilitas maksimal:
*   **Google Gemini**: Mendukung model terbaru (Gemini 2.0 Flash, Pro, dll).
*   **OpenAI**: GPT-4o, GPT-4 Turbo.
*   **Anthropic (Claude)**: Claude 3.5 Sonnet, Haiku.
*   **DeepSeek**: DeepSeek Chat/Coder (via OpenRouter/Direct).
*   **OpenRouter**: Akses ke ratusan model open-source (Llama 3, Mistral, dll).
*   **Smart Fallback System**: Jika satu provider/model gagal atau down, plugin otomatis beralih ke model cadangan tanpa menghentikan proses.

### 2. **Advanced Content Sourcing (Sumber Konten)**
Plugin tidak hanya menulis, tapi juga **meriset** data dari internet:
*   **Web Search (SerpApi & Brave)**:
    *   **Context Aggregation**: Menggabungkan data dari *Google AI Overview*, *Google AI Mode*, *Bing Copilot*, *Brave AI Summarizer*, *Answers/FAQ*, dan *Knowledge Graph*.
    *   **Organic Results**: Mengambil 5 artikel teratas dari hasil pencarian untuk konteks yang mendalam.
    *   **Auto-Switching**: Otomatis beralih antara SerpApi dan Brave jika salah satu limit/error.
*   **RSS Feeds**:
    *   **Smart Filtering**: Filter artikel berdasarkan *Match Keywords* (Wajib ada) dan *Negative Keywords* (Dilarang ada).
    *   **Full Content Fetch**: Otomatis mengambil isi artikel penuh dari link RSS (bukan cuma ringkasan) menggunakan teknologi *Readability*.
*   **Web Scraper**:
    *   Scraping halaman web spesifik menggunakan CSS Selector.
    *   **Auto-Readability**: Jika selektor kosong, otomatis mendeteksi konten utama artikel.

### 3. **Intelligent Content Generation**
*   **Human-Like Writing**: Menggunakan teknik *Angle Injection* untuk memberi sudut pandang "manusia" pada setiap artikel.
*   **SEO Optimized**: Output HTML terstruktur (H1, H2, H3, Lists) yang ramah SEO.
*   **AI Thumbnails**: Membuat gambar unggulan (Featured Image) unik untuk setiap artikel menggunakan AI (DALL-E 3 / FLUX via API).

### 4. **Automation & Management**
*   **Cron Scheduler**: Berjalan otomatis di latar belakang (Hourly/Daily).
*   **Duplicate Prevention**: Sistem *hashing* pintar untuk mencegah posting konten yang sama berulang kali.
*   **Comprehensive Logging**: Log aktivitas detil untuk memantau kinerja dan debugging.

## 🛠️ Persyaratan Sistem
*   WordPress versi 5.8+
*   PHP 7.4+
*   API Key dari provider AI yang dipilih (Google, OpenAI, dll).
*   (Opsional) SerpApi / Brave Search API Key untuk fitur Web Search.

## ⚙️ Cara Penggunaan

1.  **Instalasi**: Upload folder `autoblog` ke direktori `/wp-content/plugins/` dan aktifkan.
2.  **Konfigurasi AI**:
    *   Masuk ke menu **AutoBlog AI > Settings**.
    *   Pilih **Active AI Provider** dan masukkan API Key.
    *   Aktifkan **Smart Fallback** untuk keandalan ekstra.
3.  **Konfigurasi Search (Opsional)**:
    *   Masukkan **SerpApi Key** atau **Brave Search Key** untuk mengaktifkan fitur pencarian web canggih.
4.  **Tambah Sumber (Sources)**:
    *   Masuk ke menu **Sources**.
    *   Pilih tipe: *RSS Feed*, *Web Scraper*, atau *Web Search*.
    *   Contoh *Web Search*: Masukkan query "Tren Teknologi AI 2025". Plugin akan otomatis meriset dan menulis artikel tentang topik itu setiap hari.

## 📄 Struktur File Penting
*   `includes/Core/Runner.php`: Otak utama yang menjalankan orkestrasi (Sourcing -> Intelligence -> Generation -> Publishing).
*   `includes/Sources/SearchSource.php`: Logika *Advanced Search* dengan agregasi data dan fallback.
*   `includes/Generators/ArticleWriter.php`: Generator artikel dengan prompt engineering.
*   `includes/Utils/AIClient.php`: Wrapper untuk koneksi ke berbagai API AI.

---
**Dibuat untuk Efisiensi & Kualitas.**
Plugin ini dirancang agar Anda bisa memiliki blog yang selalu *up-to-date* dengan konten berkualitas tinggi tanpa intervensi manual.
