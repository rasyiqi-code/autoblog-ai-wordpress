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
    // ================================================================
    // STATE: Caching keys & endpoints untuk Active Provider Sync
    // ================================================================
    var activeProviderState = {
      keys: {},
      endpoints: {}
    };

    function initActiveProviderState() {
      // Inisialisasi cache dari data yang dilewatkan oleh PHP (autoblog_ajax)
      activeProviderState.keys = autoblog_ajax.custom_keys || {};
      activeProviderState.endpoints = autoblog_ajax.custom_endpoints || {};

      // Tambahkan key aktif dari input (jika user mengetik sesuatu sebelum ganti provider)
      var activeProv = $aiProvider.val();
      if (activeProv) {
        var activeKeyId = activeProv === "gemini" ? "google" : (activeProv === "hf" ? "huggingface" : activeProv);
        activeProviderState.keys[activeKeyId] = $("#active_provider_api_key").val();
        activeProviderState.endpoints[activeKeyId] = $("#active_provider_api_endpoint").val();
      }
    }

    function syncActiveProviderFields() {
      var newProv = $aiProvider.val();
      if (!newProv) return;
      var newKeyId = newProv === "gemini" ? "google" : (newProv === "hf" ? "huggingface" : newProv);

      // Simpan input aktif lama ke state
      var nameAttr = $("#active_provider_api_key").attr("name") || "";
      var match = nameAttr.match(/\[(.*?)\]/);
      if (match && match[1]) {
        var oldKeyId = match[1];
        activeProviderState.keys[oldKeyId] = $("#active_provider_api_key").val();
        activeProviderState.endpoints[oldKeyId] = $("#active_provider_api_endpoint").val();
      }

      // Update input aktif dengan data baru dari cache
      var newKeyVal = activeProviderState.keys[newKeyId] || "";
      var newApiVal = activeProviderState.endpoints[newKeyId] || "";

      if (!newApiVal && autoblog_ajax.dynamic_providers && autoblog_ajax.dynamic_providers[newKeyId]) {
        newApiVal = autoblog_ajax.dynamic_providers[newKeyId].api || "";
      }

      $("#active_provider_api_key")
        .attr("name", "autoblog_custom_api_keys[" + newKeyId + "]")
        .val(newKeyVal);

      $("#active_provider_api_endpoint")
        .attr("name", "autoblog_custom_api_endpoints[" + newKeyId + "]")
        .val(newApiVal);

      $("#active_test_connection_btn").attr("data-provider", newKeyId);
      $("#active_connection_status").text("");
    }

    // Bind event
    $aiProvider.on("change", function() {
      updateAIModelDropdown();
      syncActiveProviderFields();
    });
    $("#autoblog_embedding_provider").on("change", checkRAGKey);

    // Monitoring input password custom keys
    $(document).on("input", ".custom-key-row textarea", function () {
      var prov = $(this).closest("tr").data("provider");
      if (prov) {
        activeProviderState.keys[prov] = $(this).val();
      }
    });

    $(document).on("input", "#active_provider_api_key", function () {
      var nameAttr = $(this).attr("name") || "";
      var match = nameAttr.match(/\[(.*?)\]/);
      if (match && match[1]) {
        activeProviderState.keys[match[1]] = $(this).val();
      }
    });

    $(document).on("input", "#active_provider_api_endpoint", function () {
      var nameAttr = $(this).attr("name") || "";
      var match = nameAttr.match(/\[(.*?)\]/);
      if (match && match[1]) {
        activeProviderState.endpoints[match[1]] = $(this).val();
      }
    });

    // Inisialisasi awal
    updateAIModelDropdown();
    checkRAGKey();
    initActiveProviderState();
    syncActiveProviderFields();

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
