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

    if ($runBtn.length === 0) return;

    $runBtn.on("click", function (e) {
      e.preventDefault();

      // UI: disable tombol dan tampilkan status
      $runBtn.prop("disabled", true).val("⏳ Running...");
      $statusArea
        .html(
          '<div class="notice notice-info"><p>🔄 Pipeline sedang berjalan, harap tunggu...</p></div>'
        )
        .show();

      // Mulai polling log setiap 2 detik
      startLogPolling();

      $.ajax({
        url: autoblog_ajax.ajax_url,
        type: "POST",
        data: {
          action: "autoblog_run_pipeline",
          nonce: autoblog_ajax.nonce,
        },
        // Timeout 10 menit (pipeline bisa lama)
        timeout: 600000,
        success: function (response) {
          if (response.success) {
            $statusArea.html(
              '<div class="notice notice-success is-dismissible">' +
              "<p>✅ " +
              response.data.message +
              "</p></div>"
            );
          } else {
            $statusArea.html(
              '<div class="notice notice-error is-dismissible">' +
              "<p>❌ " +
              (response.data.message || "Pipeline gagal.") +
              "</p></div>"
            );
          }
        },
        error: function (xhr, status, error) {
          var msg =
            status === "timeout"
              ? "Request timeout (>10 menit). Cek log untuk status pipeline."
              : "Network error: " + error;

          $statusArea.html(
            '<div class="notice notice-error is-dismissible">' +
            "<p>❌ " +
            msg +
            "</p></div>"
          );
        },
        complete: function () {
          // Hentikan polling log
          stopLogPolling();
          // Sekali lagi refresh log terakhir
          refreshLogs();
          // Re-enable tombol
          $runBtn.prop("disabled", false).val("Running Finished");
          setTimeout(function () {
            $runBtn.val("▶ Run Now");
          }, 3000);
        },
      });
    });

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
          if (response.success && response.data.html) {
            var previousHtml = $logArea.val();
            // Hanya update jika ada perubahan konten (hindari kedip)
            if (previousHtml !== response.data.html) {
              $logArea.val(response.data.html);
              // Auto scroll ke bawah
              $logArea.scrollTop($logArea[0].scrollHeight);
            }
          }
        },
        global: false // Jangan trigger global ajax events
      });
    }

  });
})(jQuery);
