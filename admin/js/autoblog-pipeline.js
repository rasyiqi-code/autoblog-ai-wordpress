/**
 * Autoblog Admin - Pipeline & Log Monitoring
 *
 * Menangani:
 * - Tombol Run Pipeline (full + granular per agent)
 * - Polling log asinkron & colorizer log
 * - Update flow diagram node status secara real-time
 *
 * @package Autoblog
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // ================================================================
    // STATE: Shared variables
    // ================================================================
    var $runBtn    = $("#autoblog-run-now-btn");
    var $statusArea = $("#autoblog-run-status");
    var logInterval;

    // ================================================================
    // AJAX: Tombol Run Pipeline (full)
    // ================================================================
    if ($runBtn.length > 0) {
      $runBtn.on("click", function (e) {
        e.preventDefault();
        runPipelineAction("autoblog_run_pipeline", $runBtn);
      });
    }

    // ================================================================
    // AJAX: Run Specific Agent (Collector, Ideator, Writer) — tombol run-agent
    // ================================================================
    $(document).on("click", ".run-agent", function (e) {
      e.preventDefault();
      var $btn  = $(this);
      var agent = $btn.data("agent");
      runPipelineAction("autoblog_run_" + agent, $btn);
    });

    // ================================================================
    // INTERACTION: Click Agent Node to Run Granularly
    // ================================================================
    $(document).on("click", ".agent-node", function (e) {
      e.preventDefault();
      var agent = $(this).data("agent");
      if (!confirm("Jalankan " + agent.toUpperCase() + " AGENT secara mandiri sekarang?")) return;
      runPipelineAction("autoblog_run_" + agent, $runBtn);
    });

    // ================================================================
    // HELPER: Run Pipeline Action (full atau granular)
    // ================================================================
    function runPipelineAction(action, $button) {
      var originalText = $button.val() || $button.text();
      var isInput = $button.is("input");

      $button.prop("disabled", true);
      if (isInput) { $button.val("⏳ Running..."); }
      else         { $button.text("⏳ Running..."); }

      $statusArea
        .html('<div class="notice notice-info"><p>🔄 Proses sedang berjalan, harap tunggu...</p></div>')
        .show();

      startLogPolling();

      // Kumpulkan nilai overrides dari checkbox
      var overrides = {};
      $(".autoblog-override").each(function () {
        overrides[$(this).data("feature")] = $(this).is(":checked") ? 1 : 0;
      });

      $.ajax({
        url:     autoblog_ajax.ajax_url,
        type:    "POST",
        timeout: 600000,
        data: {
          action:    action,
          nonce:     autoblog_ajax.nonce,
          overrides: overrides,
        },
        success: function (response) {
          if (response.success) {
            $statusArea.html(
              '<div class="notice notice-success is-dismissible"><p>✅ ' +
              (response.data.message || "Proses selesai!") +
              "</p></div>"
            );
          } else {
            $statusArea.html(
              '<div class="notice notice-error is-dismissible"><p>❌ ' +
              (response.data.message || "Proses gagal.") +
              "</p></div>"
            );
          }
        },
        error: function (xhr, status, error) {
          var msg = status === "timeout"
            ? "Request timeout (>10 menit). Cek log untuk status."
            : "Network error: " + error;
          $statusArea.html(
            '<div class="notice notice-error is-dismissible"><p>❌ ' + msg + "</p></div>"
          );
        },
        complete: function () {
          stopLogPolling();
          refreshLogs();
          $button.prop("disabled", false);
          if (isInput) { $button.val(originalText); }
          else         { $button.text(originalText); }
        },
      });
    }

    // ================================================================
    // LOG POLLING: Start & Stop
    // ================================================================
    function startLogPolling() {
      refreshLogs();
      logInterval = setInterval(refreshLogs, 2000);
    }

    function stopLogPolling() {
      if (logInterval) {
        clearInterval(logInterval);
        logInterval = null;
      }
    }

    // ================================================================
    // LOG COLORIZER: Beri warna berdasarkan severity level
    // ================================================================
    function colorizeLogs(rawLogText) {
      if (!rawLogText) return "";
      var escaped = rawLogText
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

      return escaped
        .replace(/(\[ERROR\]|\[error\]|ERROR:)/g,   '<span style="color:#d63638;font-weight:bold;">$1</span>')
        .replace(/(\[WARNING\]|\[warning\]|WARNING:)/g, '<span style="color:#dba617;font-weight:bold;">$1</span>')
        .replace(/(\[INFO\]|\[info\]|INFO:)/g,       '<span style="color:#46b450;">$1</span>')
        .replace(/(\[DEBUG\]|\[debug\]|DEBUG:)/g,    '<span style="color:#2271b1;">$1</span>');
    }

    // ================================================================
    // LOG REFRESH: Ambil log terbaru & update flow diagram node
    // ================================================================
    function refreshLogs() {
      var $logArea = $(".autoblog-log-viewer");
      if ($logArea.length === 0) return;

      $.ajax({
        url:    autoblog_ajax.ajax_url,
        type:   "POST",
        data: {
          action: "autoblog_get_logs",
          nonce:  autoblog_ajax.nonce,
        },
        success: function (response) {
          if (!response.success) return;

          // 1. Render log sebagai HTML berwarna
          if (response.data.html) {
            var coloredHtml = colorizeLogs(response.data.html);
            if ($logArea.html() !== coloredHtml) {
              $logArea.html(coloredHtml);
              $logArea.scrollTop($logArea[0].scrollHeight);
            }
          }

          // 2. Perbarui node flow diagram secara real-time
          if (!response.data.statuses) return;
          var s = response.data.statuses;

          // Hitung fill % progress line
          var progress = 0;
          if      (s.collector && s.collector.status === "running")   { progress = 15; }
          else if (s.ideator   && s.ideator.status   === "running")   { progress = 50; }
          else if (s.writer    && s.writer.status    === "running")   { progress = 85; }
          else if (s.writer    && s.writer.status    === "completed") { progress = 100; }
          else if (s.collector && s.collector.status === "completed") { progress = 33; }
          else if (s.ideator   && s.ideator.status   === "completed") { progress = 66; }
          $("#autoblog-flow-line-fill").css("width", progress + "%");

          // Update node: Collector
          if (s.collector) {
            $("#node-collector .status-dot").attr("class", "status-dot " + s.collector.status);
            $("#node-collector .lbl-text").text(s.collector.status.toUpperCase());
            $("#node-collector-count").text(s.collector.ingested);
          }
          // Update node: Ideator
          if (s.ideator) {
            $("#node-ideator .status-dot").attr("class", "status-dot " + s.ideator.status);
            $("#node-ideator .lbl-text").text(s.ideator.status.toUpperCase());
            var topicTitle = s.ideator.topic_plain || "No topic selected";
            $("#node-ideator-title").text(topicTitle).attr("title", topicTitle);
          }
          // Update node: Writer
          if (s.writer) {
            $("#node-writer .status-dot").attr("class", "status-dot " + s.writer.status);
            $("#node-writer .lbl-text").text(s.writer.status.toUpperCase());
            $("#node-writer-postid").text(s.writer.post_id || "-");
          }
        },
        global: false,
      });
    }

    // Inisialisasi awal: muat log sekali saat halaman dibuka
    refreshLogs();
  });
})(jQuery);
