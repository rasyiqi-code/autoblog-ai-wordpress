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

      // Buat baris input baru dengan input Base URL
      var newRow =
        '<tr valign="top" class="custom-key-row" data-provider="' + provId + '">' +
        '  <th scope="row">' + provName + ' API Key</th>' +
        '  <td>' +
        '    <div style="display:flex; flex-direction:column; gap:8px;">' +
        '      <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">' +
        '        <label style="font-weight:600; min-width:80px; font-size:11px; margin-top:4px;">API Key(s):</label>' +
        '        <textarea name="autoblog_custom_api_keys[' + provId + ']" style="flex-grow:1; max-width:400px; height:60px; -webkit-text-security: disc; font-family: monospace; resize: vertical;" placeholder="Masukkan satu atau lebih API key (satu per baris)..."></textarea>' +
        '        <button type="button" class="button test-connection-btn" data-provider="' + provId + '" style="margin-top:2px;">Test Connection</button>' +
        '        <button type="button" class="button remove-custom-key" style="color:#d63638; border-color:#d63638; margin-top:2px;">Remove</button>' +
        '        <span class="test-connection-status" style="font-weight:600; font-size:11px; margin-top:6px;"></span>' +
        '      </div>' +
        '      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">' +
        '        <label style="font-weight:600; min-width:80px; font-size:11px;">Base URL:</label>' +
        '        <input type="text" name="autoblog_custom_api_endpoints[' + provId + ']" value="' + defaultApi + '" placeholder="e.g. https://api.openai.com/v1" style="flex-grow:1; max-width:400px;" />' +
        '        <p class="description" style="margin:0; font-size:11px; color:#64748b;">Kosongkan jika ingin menggunakan default models.dev.</p>' +
        '      </div>' +
        '    </div>' +
        '  </td>' +
        '</tr>';

      $("#custom-keys-table").append(newRow);

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
      var provName = $row.find("th").text().trim().replace(" API Key", "");

      $row.remove();

      // Kembalikan provider ke dropdown select
      var $select = $("#new-custom-provider-select");
      $select.append($("<option></option>").val(provId).text(provName));

      // Jika tabel kosong, tampilkan baris placeholder
      if ($("#custom-keys-table tr.custom-key-row").length === 0) {
        $("#custom-keys-table").append(
          '<tr id="no-custom-keys-row">' +
          '  <td colspan="2" style="padding:10px 0; color:#646970; font-style:italic;">Belum ada custom provider key yang ditambahkan. Gunakan menu di bawah untuk menambahkannya.</td>' +
          "</tr>"
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
      var apiKey      = $row.find("input[name*='key']").val();
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
