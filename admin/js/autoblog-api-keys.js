/**
 * Autoblog Admin - Custom API Keys Management
 *
 * Menangani:
 * - Tambah baris input custom API key secara dinamis
 * - Hapus baris custom API key
 * - Test Connection setiap key
 *
 * @package Autoblog
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // ================================================================
    // AJAX: Test Connection API Key
    // ================================================================
    $(document).on("click", ".test-connection-btn", function () {
      var $btn     = $(this);
      var provider = $btn.data("provider");
      var apiKey, apiEndpoint, $status;

      if ($btn.attr("id") === "active_test_connection_btn") {
        apiKey      = $("#active_provider_api_key").val();
        apiEndpoint = $("#active_provider_api_endpoint").val();
        $status     = $("#active_connection_status");
      } else {
        var $row    = $btn.closest("tr");
        apiKey      = $row.find("textarea[name*='key'], input[name*='key']").val();
        apiEndpoint = $row.find("input[name*='endpoints']").val();
        $status     = $row.find(".test-connection-status");
      }

      if (!apiKey) {
        $status.css("color", "#d63638").text("⚠️ Masukkan API Key!");
        return;
      }

      $btn.prop("disabled", true).text("Testing...");
      $status.css("color", "#646970").text("⏳ Menghubungi API...");

      $.ajax({
        url:  autoblog_ajax.ajax_url,
        type: "POST",
        data: {
          action:       "autoblog_test_api_connection",
          nonce:        autoblog_ajax.nonce,
          provider:     provider,
          api_key:      apiKey,
          api_endpoint: apiEndpoint || "",
        },
        success: function (response) {
          if (response.success) {
            $status.css("color", "#46b450").html("✅ Sukses terhubung!");
          } else {
            $status.css("color", "#d63638").html(
              "❌ Gagal: " + (response.data.message || "Error tidak diketahui")
            );
          }
        },
        error: function () {
          $status.css("color", "#d63638").text("❌ Network/Server Error");
        },
        complete: function () {
          $btn.prop("disabled", false).text("Test Connection");
        },
      });
    });
  });
})(jQuery);
