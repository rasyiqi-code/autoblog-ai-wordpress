/**
 * Autoblog Admin - Data Sources Toggle
 *
 * Menangani:
 * - Toggle label, placeholder, dan CSS Selector row
 *   berdasarkan tipe sumber data yang dipilih (RSS / Web Scraper / Web Search)
 *
 * @package Autoblog
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    var $sourceType = $("#autoblog_source_type");
    if ($sourceType.length === 0) return;

    // ================================================================
    // HELPER: Sinkronkan UI berdasarkan tipe source yang dipilih
    // ================================================================
    function handleSourceTypeChange() {
      var type        = $sourceType.val();
      var $rowSelector = $("#row_selector");
      var $labelUrl   = $("#label_url");
      var $inputUrl   = $("#input_url");
      var $descUrl    = $("#desc_url");

      if (type === "web") {
        $rowSelector.show();
        $labelUrl.text("URL");
        $inputUrl.attr("placeholder", "https://site1.com, https://site2.com");
        $descUrl.text("Masukkan URL untuk di-scrape.");
      } else if (type === "web_search") {
        $rowSelector.hide();
        $labelUrl.text("Search Query");
        $inputUrl.attr("placeholder", "latest AI trends, wordpress tips");
        $descUrl.text("Masukkan query pencarian (pisah koma). Menggunakan DuckDuckGo/SerpApi sesuai pengaturan AI Engine.");
      } else {
        // Default: RSS
        $rowSelector.hide();
        $labelUrl.text("URL");
        $inputUrl.attr("placeholder", "https://site1.com/feed, https://site2.com/feed");
        $descUrl.text("Masukkan URL RSS Feed.");
      }
    }

    $sourceType.on("change", handleSourceTypeChange);
    // Trigger inisialisasi awal agar sinkron saat halaman dimuat
    handleSourceTypeChange();
  });
})(jQuery);
