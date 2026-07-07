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
    // INTERACTION: Tambah Custom API Key
    // ================================================================
    $("#btn-add-custom-key").on("click", function () {
      var $select  = $("#new-custom-provider-select");
      var provId   = $select.val();
      var provName = $select.find("option:selected").text();

      if (!provId) return;

      // Hapus baris placeholder "belum ada key" jika ada
      $("#no-custom-keys-row").remove();

      // Dapatkan default API endpoint dari dynamic_providers
      var defaultApi = "";
      if (autoblog_ajax.dynamic_providers && autoblog_ajax.dynamic_providers[provId]) {
        defaultApi = autoblog_ajax.dynamic_providers[provId].api || "";
      }

      var placeholderVal = 'Default models.dev';

      // Buat baris input baru dengan input Base URL & Radio Set Aktif
      var isChecked = ($(".active-provider-radio:checked").length === 0) ? "checked" : "";
      var newRow =
        '<tr class="custom-key-row" data-provider="' + provId + '">' +
        '  <td style="text-align: center; vertical-align: middle; padding: 10px;">' +
        '    <input type="radio" class="active-provider-radio" name="autoblog_ai_provider" value="' + provId + '" ' + isChecked + ' style="margin: 0; cursor: pointer;" />' +
        '  </td>' +
        '  <td style="vertical-align: middle; padding: 10px;">' +
        '    <span class="provider-label-text" style="font-weight: 700; font-size: 13px; color: #1d2327;">' + provName + '</span>' +
        '    <div class="provider-badge-container" style="margin-top: 4px;"></div>' +
        '  </td>' +
        '  <td style="vertical-align: middle; padding: 10px;">' +
        '    <textarea name="autoblog_custom_api_keys[' + provId + ']" class="autoblog-textarea" style="width: 100%; -webkit-text-security: disc; font-family: monospace;" placeholder="Masukkan satu atau lebih API key (satu per baris)..."></textarea>' +
        '  </td>' +
        '  <td style="vertical-align: middle; padding: 10px;">' +
        '    <input type="text" name="autoblog_custom_api_endpoints[' + provId + ']" class="autoblog-input" value="' + defaultApi + '" data-default="' + defaultApi + '" placeholder="' + placeholderVal + '" style="width: 100%;" />' +
        '    <span class="default-url-info" style="font-size: 10px; color: #64748b; display: block; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="Bawaan: ' + defaultApi + '">' +
        '    </span>' +
        '  </td>' +
        '  <td style="vertical-align: middle; padding: 10px;">' +
        '    <div style="display: flex; gap: 6px; align-items: center;">' +
        '      <button type="button" class="autoblog-btn autoblog-btn-small test-connection-btn" data-provider="' + provId + '">Test</button>' +
        '      <button type="button" class="autoblog-btn autoblog-btn-small autoblog-btn-danger remove-custom-key">Remove</button>' +
        '    </div>' +
        '    <div class="test-connection-status" style="font-weight: 600; font-size: 11px; margin-top: 4px; display: block;"></div>' +
        '  </td>' +
        '</tr>';

      $("#custom-keys-tbody").append(newRow);

      // Hapus opsi ini dari select dropdown
      $select.find("option:selected").remove();
      $select.val("");
    });

    // ================================================================
    // INTERACTION: Hapus Custom API Key
    // ================================================================
    $(document).on("click", ".remove-custom-key", function () {
      var $row    = $(this).closest("tr");
      var provId  = $row.data("provider");
      var provName = $row.find("th .provider-label-text").text().trim();

      $row.remove();

      // Kembalikan provider ke dropdown select
      var $select = $("#new-custom-provider-select");
      $select.append($("<option></option>").val(provId).text(provName));

      // Jika tabel kosong, tampilkan baris placeholder
      if ($("#custom-keys-table tr.custom-key-row").length === 0) {
        $("#custom-keys-table").append(
          '<tr id="no-custom-keys-row">' +
          '  <td colspan="2" style="padding:10px 0; color:#64748b; font-style:italic; font-size:12px;">Belum ada custom provider key yang ditambahkan. Gunakan menu di bawah untuk menambahkannya.</td>' +
          '</tr>'
        );
      }
    });

    // ================================================================
    // AJAX: Test Connection API Key
    // ================================================================
    $(document).on("click", ".test-connection-btn", function () {
      var $btn     = $(this);
      var provider = $btn.data("provider");
      var $row     = $btn.closest("tr");
      var apiKey      = $row.find("textarea[name*='key'], input[name*='key']").val();
      var apiEndpoint = $row.find("input[name*='endpoints']").val();
      var $status     = $row.find(".test-connection-status");

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
