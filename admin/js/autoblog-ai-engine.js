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
    var $aiProvider = $("#autoblog_ai_provider");
    if ($aiProvider.length === 0) return;

    // ================================================================
    // HELPER: Update dropdown model berdasarkan provider yang dipilih
    // ================================================================
    function updateAIModelDropdown() {
      var provider    = $aiProvider.val();
      var $modelSelect = $("#autoblog_ai_model");
      if ($modelSelect.length === 0) return;

      $modelSelect.empty();

      // Mapping alias provider ke kunci katalog
      var devKey = provider;
      if (provider === "gemini") {
        devKey = "google";
      } else if (provider === "huggingface" || provider === "hf") {
        devKey = "huggingface";
      }

      var catalog       = autoblog_ajax.catalog_models || {};
      var models        = catalog[devKey] || {};
      var selectedModel = autoblog_ajax.selected_model;
      var foundSelected = false;

      $.each(models, function (m_id, m_name) {
        var isSelected = m_id === selectedModel;
        if (isSelected) { foundSelected = true; }
        $modelSelect.append(
          $("<option></option>").val(m_id).text(m_name).prop("selected", isSelected)
        );
      });

      // Jika model tersimpan tidak ada di katalog (custom/lama), tambahkan sebagai opsi dinamis
      if (selectedModel && !foundSelected && provider !== "hf") {
        $modelSelect.append(
          $("<option></option>").val(selectedModel).text(selectedModel).prop("selected", true)
        );
      }

      // Tampil/sembunyi baris Gemini Grounding
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
    // HELPER: Cek apakah API key untuk Active Provider terisi
    // ================================================================
    function checkActiveKey() {
      var provider = $aiProvider.val();
      if (!provider) return;

      var checkKey = provider;
      if (provider === "gemini") {
        checkKey = "google";
      } else if (provider === "hf") {
        checkKey = "huggingface";
      }

      var $row = $('.custom-key-row[data-provider="' + checkKey + '"]');
      var $warning = $("#active_key_warning");

      if ($row.length === 0 || !$row.find("textarea[name*='key']").val()) {
        var provName = $aiProvider.find("option:selected").text();
        $warning.html('⚠️ API Key untuk provider aktif (' + provName + ') belum ditambahkan atau masih kosong di bawah. Silakan tambahkan/isi di bagian "Custom LLM Provider Keys".').show();
      } else {
        $warning.hide();
      }
    }

    // Bind event
    $aiProvider.on("change", function() {
      updateAIModelDropdown();
      checkActiveKey();
    });
    $("#autoblog_embedding_provider").on("change", checkRAGKey);

    // Monitoring input password custom keys
    $(document).on("input", ".custom-key-row textarea", checkActiveKey);
    $(document).on("click", "#btn-add-custom-key, .remove-custom-key", function() {
      setTimeout(checkActiveKey, 50); // delay agar DOM selesai terupdate
    });

    // Inisialisasi awal
    updateAIModelDropdown();
    checkRAGKey();
    checkActiveKey();

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
