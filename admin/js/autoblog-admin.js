/**
 * Autoblog Admin JavaScript
 *
 * Menangani interaksi AJAX di halaman admin plugin,
 * terutama tombol "Run Now" agar tidak reload halaman.
 *
 * @package Autoblog
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // ================================================================
    // AJAX: Run Pipeline (tombol Run Now)
    // ================================================================
    var $runBtn = $("#autoblog-run-now-btn");
    var $statusArea = $("#autoblog-run-status");
    var logInterval; // Variabel untuk interval polling log

    if ($runBtn.length > 0) {
      $runBtn.on("click", function (e) {
        e.preventDefault();
        runPipelineAction("autoblog_run_pipeline", $runBtn);
      });
    }

    // ================================================================
    // AJAX: Run Specific Agent (Collector, Ideator, Writer)
    // ================================================================
    $(document).on("click", ".run-agent", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var agent = $btn.data("agent");
      runPipelineAction("autoblog_run_" + agent, $btn);
    });

    /**
     * Helper to run pipeline actions (Full or Granular)
     */
    function runPipelineAction(action, $button) {
      var originalText = $button.val() || $button.text();
      var isInput = $button.is("input");

      // UI: disable dan status
      $button.prop("disabled", true);
      if (isInput) {
        $button.val("⏳ Running...");
      } else {
        $button.text("⏳ Running...");
      }

      $statusArea
        .html(
          '<div class="notice notice-info"><p>🔄 Proses sedang berjalan, harap tunggu...</p></div>'
        )
        .show();

      startLogPolling();

      var overrides = {};
      $(".autoblog-override").each(function () {
        overrides[$(this).data("feature")] = $(this).is(":checked") ? 1 : 0;
      });

      $.ajax({
        url: autoblog_ajax.ajax_url,
        type: "POST",
        data: {
          action: action,
          nonce: autoblog_ajax.nonce,
          overrides: overrides,
        },
        timeout: 600000,
        success: function (response) {
          if (response.success) {
            $statusArea.html(
              '<div class="notice notice-success is-dismissible">' +
              "<p>✅ " +
              (response.data.message || "Proses selesai!") +
              "</p></div>"
            );
            // Dynamic updates are handled by log polling. No page reload.
          } else {
            $statusArea.html(
              '<div class="notice notice-error is-dismissible">' +
              "<p>❌ " +
              (response.data.message || "Proses gagal.") +
              "</p></div>"
            );
          }
        },
        error: function (xhr, status, error) {
          var msg =
            status === "timeout"
              ? "Request timeout (>10 menit). Cek log untuk status."
              : "Network error: " + error;

          $statusArea.html(
            '<div class="notice notice-error is-dismissible">' +
            "<p>❌ " +
            msg +
            "</p></div>"
          );
        },
        complete: function () {
          stopLogPolling();
          refreshLogs();
          $button.prop("disabled", false);
          if (isInput) {
            $button.val(originalText);
          } else {
            $button.text(originalText);
          }
        },
      });
    }

    /**
     * Mulai polling log
     */
    function startLogPolling() {
      // Refresh pertama kali langsung
      refreshLogs();
      // Set interval
      logInterval = setInterval(function () {
        refreshLogs();
      }, 2000); // 2 detik
    }

    /**
     * Hentikan polling log
     */
    function stopLogPolling() {
      if (logInterval) {
        clearInterval(logInterval);
        logInterval = null;
      }
    }

    /**
     * Parse raw log texts to format HTML color spans based on severity levels.
     */
    function colorizeLogs(rawLogText) {
      if (!rawLogText) return '';
      
      // Escape HTML tags to prevent XSS
      var escaped = rawLogText
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

      var colored = escaped
        .replace(/(\[ERROR\]|\[error\]|ERROR:)/g, '<span style="color: #ef4444; font-weight: bold;">$1</span>')
        .replace(/(\[WARNING\]|\[warning\]|WARNING:)/g, '<span style="color: #f59e0b; font-weight: bold;">$1</span>')
        .replace(/(\[INFO\]|\[info\]|INFO:)/g, '<span style="color: #10b981;">$1</span>')
        .replace(/(\[DEBUG\]|\[debug\]|DEBUG:)/g, '<span style="color: #3b82f6;">$1</span>');

      return colored;
    }

    /**
     * Refresh area log via AJAX
     */
    function refreshLogs() {
      var $logArea = $(".autoblog-log-viewer");
      if ($logArea.length === 0) return;

      $.ajax({
        url: autoblog_ajax.ajax_url,
        type: "POST",
        data: {
          action: "autoblog_get_logs",
          nonce: autoblog_ajax.nonce
        },
        success: function (response) {
          if (response.success) {
            // 1. Render logs as colored HTML
            if (response.data.html) {
              var previousRawHtml = $logArea.html();
              var coloredHtml = colorizeLogs(response.data.html);
              if (previousRawHtml !== coloredHtml) {
                $logArea.html(coloredHtml);
                // Auto scroll
                $logArea.scrollTop($logArea[0].scrollHeight);
              }
            }

            // 2. Real-time Status Badge & Metadata update
            if (response.data.statuses) {
              var s = response.data.statuses;

              // Hitung flow line fill percentage secara visual
              var progress = 0;
              if (s.collector && s.collector.status === 'running') {
                progress = 15;
              } else if (s.ideator && s.ideator.status === 'running') {
                progress = 50;
              } else if (s.writer && s.writer.status === 'running') {
                progress = 85;
              } else if (s.writer && s.writer.status === 'completed') {
                progress = 100;
              } else if (s.collector && s.collector.status === 'completed') {
                progress = 33;
              } else if (s.ideator && s.ideator.status === 'completed') {
                progress = 66;
              }
              $("#autoblog-flow-line-fill").css("width", progress + "%");

              // Collector Agent Node
              if (s.collector) {
                var $cDot = $("#node-collector .status-dot");
                $cDot.attr("class", "status-dot " + s.collector.status);
                $("#node-collector .lbl-text").text(s.collector.status.toUpperCase());
                $("#node-collector-count").text(s.collector.ingested);
              }

              // Ideator Agent Node
              if (s.ideator) {
                var $iDot = $("#node-ideator .status-dot");
                $iDot.attr("class", "status-dot " + s.ideator.status);
                $("#node-ideator .lbl-text").text(s.ideator.status.toUpperCase());
                var topicTitle = s.ideator.topic_plain || "No topic selected";
                $("#node-ideator-title").text(topicTitle).attr("title", topicTitle);
              }

              // Writer Agent Node
              if (s.writer) {
                var $wDot = $("#node-writer .status-dot");
                $wDot.attr("class", "status-dot " + s.writer.status);
                $("#node-writer .lbl-text").text(s.writer.status.toUpperCase());
                $("#node-writer-postid").text(s.writer.post_id || '-');
              }
            }
          }
        },
        global: false
      });
    }

    // ================================================================
    // INTERACTION: Click Agent Node to Run Granularly
    // ================================================================
    $(document).on("click", ".agent-node", function (e) {
      e.preventDefault();
      var $node = $(this);
      var agent = $node.data("agent");
      
      var confirmRun = confirm("Jalankan " + agent.toUpperCase() + " AGENT secara mandiri sekarang?");
      if (!confirmRun) return;

      // Picu action pipeline parsial
      runPipelineAction("autoblog_run_" + agent, $runBtn);
    });

    // ================================================================
    // AJAX: Test Gemini Grounding
    // ================================================================
    $("#btn_test_grounding").on("click", function (e) {
      e.preventDefault();
      var $btn = $(this);
      var $promptInput = $("#gemini_test_prompt");
      var $resultArea = $("#gemini_test_result");
      var prompt = $promptInput.val();
      var model = $("#gemini_test_model").val();

      if (!prompt) {
        alert("Harap masukkan prompt pertanyaan riset.");
        return;
      }

      $btn.prop("disabled", true).text("⏳ Testing...");
      $resultArea.hide().html("").css("border-left-color", "#72aee6");

      $.ajax({
        url: autoblog_ajax.ajax_url,
        type: "POST",
        data: {
          action: "autoblog_test_gemini_grounding",
          nonce: autoblog_ajax.nonce,
          prompt: prompt,
          model: model
        },
        success: function (response) {
          $resultArea.show();
          if (response.success) {
            $resultArea.html("<strong>Gemini Answer:</strong>\n\n" + response.data.answer);
            $resultArea.css("border-left-color", "green");
          } else {
            $resultArea.html("<strong>Error:</strong>\n\n" + (response.data.message || "Gagal mendapatkan respon."));
            $resultArea.css("border-left-color", "red");
          }
        },
        error: function () {
          $resultArea.show().html("<strong>Error:</strong>\n\nNetwork or Server Error.");
          $resultArea.css("border-left-color", "red");
        },
        complete: function () {
          $btn.prop("disabled", false).text("Run Test");
        }
      });
    });

    // ================================================================
    // INTERACTION: Dynamic Custom API Keys
    // ================================================================
    $("#btn-add-custom-key").on("click", function () {
      var $select = $("#new-custom-provider-select");
      var provId = $select.val();
      var provName = $select.find("option:selected").text();

      if (!provId) return;

      // Hapus row placeholder "belum ada key" jika ada
      $("#no-custom-keys-row").remove();

      // Buat row input baru
      var newRow = 
        '<tr valign="top" class="custom-key-row" data-provider="' + provId + '">' +
        '  <th scope="row" style="width: 280px;">' +
        '    ' + provName + ' API Key' +
        '  </th>' +
        '  <td>' +
        '    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">' +
        '      <input type="password" name="autoblog_custom_api_keys[' + provId + ']" value="" class="regular-text" style="width:25em;" />' +
        '      <button type="button" class="button test-connection-btn" data-provider="' + provId + '">Test Connection</button>' +
        '      <button type="button" class="button remove-custom-key" style="color:#d63638; border-color:#d63638;">Remove</button>' +
        '      <span class="test-connection-status" style="font-weight:bold; font-size:12.5px;"></span>' +
        '    </div>' +
        '  </td>' +
        '</tr>';

      $("#custom-keys-table").append(newRow);

      // Hapus opsi ini dari select dropdown
      $select.find("option:selected").remove();
      $select.val("");
    });

    $(document).on("click", ".remove-custom-key", function () {
      var $row = $(this).closest("tr");
      var provId = $row.data("provider");
      // Ambil nama provider bersih dengan membuang kata " API Key" di akhir th
      var provName = $row.find("th").text().trim().replace(" API Key", "");

      // Hapus baris di tabel
      $row.remove();

      // Kembalikan ke dropdown select
      var $select = $("#new-custom-provider-select");
      $select.append($("<option></option>").val(provId).text(provName));

      // Jika tabel kosong, tampilkan kembali row placeholder
      if ($("#custom-keys-table tr.custom-key-row").length === 0) {
        $("#custom-keys-table").append(
          '<tr id="no-custom-keys-row">' +
          '  <td colspan="2" style="padding:10px 0; color:#64748b; font-style:italic;">Belum ada custom provider key yang ditambahkan. Gunakan menu di bawah untuk menambahkannya.</td>' +
          '</tr>'
        );
      }
    });

    // Event click untuk Test Connection API Key
    $(document).on("click", ".test-connection-btn", function () {
      var $btn = $(this);
      var provider = $btn.data("provider");
      var $row = $btn.closest("tr");
      var $input = $row.find("input[type=password], input[type=text]");
      var apiKey = $input.val();
      var $status = $row.find(".test-connection-status");

      if (!apiKey) {
        $status.css("color", "#d63638").text("⚠️ Masukkan API Key!");
        return;
      }

      $btn.prop("disabled", true).text("Testing...");
      $status.css("color", "#64748b").text("⏳ Menghubungi API...");

      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "autoblog_test_api_connection",
          nonce: autoblogAdmin.nonce,
          provider: provider,
          api_key: apiKey
        },
        success: function (response) {
          if (response.success) {
            $status.css("color", "#008a20").html("✅ Sukses terhubung!");
          } else {
            $status.css("color", "#d63638").html("❌ Gagal: " + (response.data.message || "Error tidak diketahui"));
          }
        },
        error: function () {
          $status.css("color", "#d63638").text("❌ Network/Server Error");
        },
        complete: function () {
          $btn.prop("disabled", false).text("Test Connection");
        }
      });
    });

    // Pemuatan log pertama kali secara instan saat document ready
    refreshLogs();
  });
})(jQuery);
