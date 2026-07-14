/**
 * Autoblog Admin - AI Engine Settings
 *
 * Menangani:
 * - Dropdown provider ↔ model sinkron dinamis
 * - Tampil/sembunyi baris Gemini Grounding
 * - Cek ketersediaan API key untuk Embedding Provider
 *
 * Data provider diterima dari wp_localize_script via objek `autoblog_ajax`.
 *
 * @package Autoblog
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // ================================================================
    // HELPER: Update dropdown model berdasarkan provider yang dipilih
    // ================================================================
    function toggleGeminiGroundingRow() {
      var provider = $(".active-provider-radio:checked").val();
      if (provider === "gemini") {
        $("#row_gemini_grounding").show();
      } else {
        $("#row_gemini_grounding").hide();
      }
    }

    // ================================================================
    // HELPER: Cek apakah API key untuk Embedding Provider tersedia
    // ================================================================
    function checkRAGKey() {
      var $embeddingSelect = $("#autoblog_embedding_provider");
      if ($embeddingSelect.length === 0) return;

      var provider  = $embeddingSelect.val();
      var filledKeys = autoblog_ajax.keys_filled || {};
      if (!filledKeys[provider]) {
        $("#rag_key_warning").show();
      } else {
        $("#rag_key_warning").hide();
      }
    }

    // ================================================================
    // HELPER: Cek active provider key, toggle warning, update badge
    // ================================================================
    function checkActiveKey() {
      var provider = $(".active-provider-radio:checked").val();
      var $warning = $("#active_key_warning");

      if (!provider) {
        $warning.html('⚠️ Belum ada provider aktif. Silakan pilih / tambahkan provider di atas dan klik "Set Aktif".').show();
        return;
      }

      var checkKey = provider;
      if (provider === "gemini") {
        checkKey = "google";
      } else if (provider === "hf") {
        checkKey = "huggingface";
      }

      // Reset semua badge LLM provider di bawah menjadi CADANGAN
      $(".custom-key-row").each(function () {
        var $badgeContainer = $(this).find(".provider-badge-container");
        var provId = $(this).data("provider");
        
        if (provId === checkKey) {
          $badgeContainer.html('<span class="autoblog-badge autoblog-badge-active">AKTIF</span>');
        } else {
          $badgeContainer.html('<span class="autoblog-badge autoblog-badge-secondary">CADANGAN</span>');
        }
      });

      // Validasi apakah key-nya ada dan tidak kosong
      var $row = $('.custom-key-row[data-provider="' + checkKey + '"]');

      if ($row.length === 0 || !$row.find("textarea").val()) {
        var provLabelName = $row.find(".provider-label-text").text() || provider;
        $warning.html('⚠️ API Key untuk provider aktif (' + provLabelName + ') belum ditambahkan atau masih kosong di atas. Silakan isi kuncinya.').show();
      } else {
        $warning.hide();
      }
    }

    // ================================================================
    // HELPER: Sembunyikan Bawaan Base URL jika nilainya sama dengan default
    // ================================================================
    function updateDefaultUrlVisibility() {
      $(".custom-key-row").each(function() {
        var $row = $(this);
        var $input = $row.find("input[name^='autoblog_custom_api_endpoints']");
        if ($input.length === 0) return;
        
        var currentVal = $input.val().trim();
        var defaultVal = $input.data("default") ? $input.data("default").toString().trim() : "";
        var $span = $row.find(".default-url-info");

        if (defaultVal && currentVal !== defaultVal && currentVal !== "") {
          $span.html('Bawaan: <code>' + defaultVal + '</code>').show();
        } else {
          $span.empty().hide();
        }
      });
    }

    // Bind event
    $(document).on("change", ".active-provider-radio", function() {
      toggleGeminiGroundingRow();
      checkActiveKey();
    });
    $("#autoblog_embedding_provider").on("change", checkRAGKey);

    // Monitoring input password custom keys & default URL visibility
    $(document).on("input", ".custom-key-row textarea", checkActiveKey);
    $(document).on("input change", ".custom-key-row input[type='text']", updateDefaultUrlVisibility);
    
    $(document).on("click", "#btn-add-custom-key, .remove-custom-key", function() {
      setTimeout(function() {
        // Jika provider aktif saat ini di-remove, aktifkan radio pertama yang tersisa
        if ($(".active-provider-radio:checked").length === 0) {
          $(".active-provider-radio").first().prop("checked", true);
        }
        toggleGeminiGroundingRow();
        checkActiveKey();
        updateDefaultUrlVisibility();
      }, 50); // delay agar DOM selesai terupdate
    });

    // Inisialisasi awal
    toggleGeminiGroundingRow();
    checkRAGKey();
    checkActiveKey();
    updateDefaultUrlVisibility();

    // ================================================================
    // AJAX: Test Gemini Grounding
    // ================================================================
    $("#btn_test_grounding").on("click", function (e) {
      e.preventDefault();
      var $btn        = $(this);
      var prompt      = $("#gemini_test_prompt").val();
      var model       = $("#gemini_test_model").val();
      var $resultArea = $("#gemini_test_result");

      if (!prompt) {
        alert("Harap masukkan prompt pertanyaan riset.");
        return;
      }

      $btn.prop("disabled", true).text("⏳ Testing...");
      $resultArea.hide().html("").css("border-left-color", "#72aee6");

      $.ajax({
        url:  autoblog_ajax.ajax_url,
        type: "POST",
        data: {
          action: "autoblog_test_gemini_grounding",
          nonce:  autoblog_ajax.nonce,
          prompt: prompt,
          model:  model,
        },
        success: function (response) {
          $resultArea.show();
          if (response.success) {
            $resultArea.html("<strong>Gemini Answer:</strong>\n\n" + response.data.answer);
            $resultArea.css("border-left-color", "#46b450");
          } else {
            $resultArea.html("<strong>Error:</strong>\n\n" + (response.data.message || "Gagal mendapatkan respon."));
            $resultArea.css("border-left-color", "#d63638");
          }
        },
        error: function () {
          $resultArea.show().html("<strong>Error:</strong>\n\nNetwork or Server Error.");
          $resultArea.css("border-left-color", "#d63638");
        },
        complete: function () {
          $btn.prop("disabled", false).text("Run Test");
        },
      });
    });
  });
})(jQuery);
