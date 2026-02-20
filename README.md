# AutoBlog AI Plugin

**AutoBlog AI** adalah plugin WordPress canggih yang mengotomatiskan pembuatan konten blog menggunakan kekuatan Artificial Intelligence (AI). Plugin ini mampu mencari topik terkini, meriset konten dari berbagai sumber (RSS, Web, Search Engine), dan menulis artikel berkualitas tinggi yang lengkap dengan gambar *thumbnail* secara otomatis.

## 🚀 Fitur Utama

### 1. **Multi-Provider AI Core**
Plugin ini mendukung berbagai penyedia AI terkemuka untuk fleksibilitas maksimal:
*   **Google Gemini**: Mendukung model terbaru (Gemini 3.1 Pro, 2.5 Flash, 2.0 Flash, 1.5 Flash).
*   **Groq**: Llama 3.3 70B, Llama 3 70B, Mixtral 8x7B.
*   **OpenRouter**: Akses ke ratusan model open-source.
*   **Smart Fallback System**: Jika satu provider/model gagal, plugin otomatis beralih ke model cadangan.

### 🛡️ Robust AI Infrastructure (Reliability)
Sistem dirancang untuk kegagalan minimum:
*   **Intra-Provider Model Pooling**: Jika model utama gagal (misal Gemini 3.1), sistem tidak langsung menyerah; ia akan mencoba model lain di provider yang sama (3.1 -> 2.5 -> 2.0 -> 1.5) terlebih dahulu.
*   **Cross-Provider Scaling**: Jika seluruh pool provider utama gagal, sistem akan melompat ke provider cadangan (Groq/OpenAI) secara otomatis.
*   **Circuit Breaker**: Mencegah infinite loop saat melakukan fallback berantai.

### 2. **🧠 Advanced Agentic Features (New)**
Plugin ini dilengkapi dengan agen cerdas yang bekerja secara modular dalam 3 fase utama (**Agentic Pipeline**):
*   **Stage 1: Ingestion Agent (Collector)**:
    - Mengumpulkan data mentah dari berbagai sumber secara independen.
    - Membersihkan teks dan menyimpannya ke **Vector Store (Knowledge Base)** untuk RAG.
*   **Stage 2: Ideator Agent (The Brain)**:
    - AI berfungsi sebagai Editor-in-Chief yang meriset Knowledge Base secara berkala.
    - Menghasilkan daftar **Topik + Angle** yang unik dan cerdas, bukan sekadar ringkasan berita.
*   **Stage 3: Production Agent (The Writer)**:
    - AI Penulis yang mengeksekusi topik dari Ideator.
    - Menggunakan konteks mendalam dari KB (RAG) untuk menghasilkan artikel yang kaya informasi.
*   **Deep Research (Multi-Hop)**:
    - Agen melakukan riset bertahap untuk memastikan konten memiliki kedalaman fakta.

### 3. **Flexible Pipeline Modes**
Pilih cara kerja yang sesuai dengan kebutuhan Anda:
*   **Hybrid Mode (Default)**: Menggabungkan Triggers eksternal dengan konteks dari Knowledge Base.
*   **KB Only Mode**: Membuat konten murni dari Knowledge Base Anda (RAG). Aman dari halusinasi dan sangat spesifik.
*   **Triggers Only Mode**: Berita/Artikel terkini dari RSS atau Search tanpa konteks KB.

### 4. **Advanced Content Sourcing**
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

### 5. **Intelligent Content Generation**
*   **Human-Like Writing**: Menggunakan teknik *Angle Injection* untuk memberi sudut pandang "manusia" pada setiap artikel.
*   **SEO Optimized**: Output HTML terstruktur (H1, H2, H3, Lists) yang ramah SEO.
*   **AI Thumbnails**: Membuat gambar unggulan (Featured Image) unik untuk setiap artikel menggunakan AI (DALL-E 3 / FLUX via API).

### 6. **Advanced Content Features (New)**
*   **Intelligent Taxonomy Management**: AI secara otomatis memilih kategori yang paling relevan dari daftar kategori blog Anda dan menyarankan tag cerdas untuk setiap artikel.
*   **Multi-Modal Median Injection**: Menempatkan visualisasi data (Charts) dan media eksternal (YouTube/X) secara strategis di tengah konten untuk keterlibatan pembaca yang lebih tinggi.

### 7. **Automation & Management**
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
