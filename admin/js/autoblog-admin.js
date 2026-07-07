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

              // Collector Agent
              if (s.collector) {
                $("#autoblog-collector-status-container").html(s.collector.badge);
                $("#autoblog-collector-last-sync").text(s.collector.last_sync);
                $("#autoblog-collector-ingested").text(s.collector.ingested);
                if (s.collector.sources) {
                  $("#autoblog-collector-sources-container").html(s.collector.sources);
                } else {
                  $("#autoblog-collector-sources-container").html('');
                }
              }

              // Ideator Agent
              if (s.ideator) {
                $("#autoblog-ideator-status-container").html(s.ideator.badge);
                $("#autoblog-ideator-last-brainstorm").text(s.ideator.last_brainstorm);
                $("#autoblog-ideator-topic").html(s.ideator.topic);
              }

              // Writer Agent
              if (s.writer) {
                $("#autoblog-writer-status-container").html(s.writer.badge);
                $("#autoblog-writer-last-published").text(s.writer.last_published);
                $("#autoblog-writer-topic").text(s.writer.topic);
                $("#autoblog-writer-topic-container").attr('title', s.writer.topic_attr);
                $("#autoblog-writer-result").html(s.writer.result);
              }
            }
          }
        },
        global: false // Jangan trigger global ajax events
      });
    }

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

    // Pemuatan log pertama kali secara instan saat document ready
    refreshLogs();
  });

  });
})(jQuery);
