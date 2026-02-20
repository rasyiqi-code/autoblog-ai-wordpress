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

### 2. **🧠 Advanced Agentic Features**
Plugin ini dilengkapi dengan agen cerdas yang bekerja secara modular dalam 4 fase utama (**Agentic Pipeline**):
*   **Stage 1: Ingestion Agent (Collector)**: Mengumpulkan data mentah (RSS, Web, Search) dan menyimpannya ke **Vector Store**.
*   **Stage 2: Ideator Agent (The Brain)**: Meriset Knowledge Base dan menghasilkan ide **Topik + Angle** yang unik.
*   **Stage 3: Production Agent (The Writer)**: Menulis artikel kaya konteks menggunakan RAG.
*   **Stage 4: Maintenance Agent (Living Content)**: Secara otomatis mendeteksi artikel lama (*stale*) dan memperbaruinya dengan informasi terbaru agar konten tetap relevan (SEO Evergreen).

### 3. **🎨 Human-Like Content Refinement**
*   **Anti-AI Title Manifesto**: Menggunakan psikologi judul manusia untuk menghindari deteksi AI:
    - **Haramkan Titik Dua (:)**: Menghilangkan pola "Topik: Penjelasan".
    - **Emotional Diction (FOMO)**: Menggunakan kata pemicu emosi (*Bongkar, Rahasia, Nyesel*) dan mem-blacklist kata kaku AI (*Komprehensif, Strategi, Lanskap*).
    - **Irregular Numbers**: Menggunakan angka ganjil/acak (7, 13, 23) untuk listicle.
*   **Dynamic Design**: Penyisipan otomatis grafik (Charts), video, dan social media embeds.

### 4. **⚙️ Control & Flexible Scheduling**
*   **Granular Manual Trigger**: Kendali penuh saat klik "Run Now". Anda bisa memilih fitur mana yang ingin diaktifkan (misal: jalankan riset tapi matikan interlinking).
*   **Independent Refresh Schedule**: Jadwal "Living Content" kini bisa diatur terpisah dari jadwal artikel baru (Weekly, Monthly, Daily, hingga Hourly).

## 🛠️ Persyaratan Sistem
*   WordPress versi 5.8+
*   PHP 7.4+ (Direkomendasikan 8.1+ untuk performa AI)
*   API Key AI (Google Gemini, OpenAI, Groq, atau OpenRouter).

## ⚙️ Cara Penggunaan

1.  **Instalasi**: Upload folder `autoblog` ke `/wp-content/plugins/` dan aktifkan.
2.  **Konfigurasi**: Atur API Key di menu **Settings**.
3.  **Jadwal**: Di tab **Tools & Logs**, atur frekuensi pembuatan artikel baru dan jadwal pemeliharaan *Living Content*.
4.  **Manual Overrides**: Gunakan panel pemicu manual di Dashboard untuk menjalankan pipeline secara instan dengan opsi fitur yang diinginkan.

## 📄 Struktur File Penting
*   `includes/Core/Runner.php`: Orkestrator utama seluruh fase pipeline.
*   `includes/Core/ContentRefresher.php`: Pengelola fitur pembaruan konten otomatis (Living Content).
*   `includes/Intelligence/IdeationAgent.php`: Brainstorming ide dengan Manifesto Judul Anti-AI.
*   `includes/Generators/ArticleWriter.php`: Penulis artikel bertenaga RAG dengan gaya manusia.

---
**Dibuat untuk Efisiensi & Kualitas.**
Plugin ini dirancang agar blog Anda tetap hidup, relevan, dan memiliki sentuhan personal manusia meski diotomatisasi 100%.
