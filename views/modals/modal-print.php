  <!-- MODAL 1: Print Preview Modal -->
  <div class="modal-overlay" id="modal-print-preview">
    <div class="modal-content" style="max-width: 900px;">
      <div class="modal-header">
        <h3><i class="fa-solid fa-print"></i> <span data-i18n="title_print_preview">Print Preview</span></h3>

        <!-- Live Print Mode (Rx Only vs Rx + Bill) and Paper Size Selector -->
        <div class="paper-selector-toolbar" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
          <button type="button" class="btn btn-outline btn-sm btn-print-mode" id="btn-mode-rx-only" data-mode="rx_only"><i class="fa-solid fa-file-prescription"></i> <span data-i18n="btn_mode_rx_only">Prescription Only</span></button>
          <button type="button" class="btn btn-primary btn-sm btn-print-mode" id="btn-mode-rx-bill" data-mode="rx_bill"><i class="fa-solid fa-receipt"></i> <span data-i18n="btn_mode_rx_bill">Rx + Bill</span></button>
          <span style="border-left:1px solid #cbd5e1; height:20px; margin:0 4px;"></span>
          <button type="button" class="btn btn-outline btn-sm btn-paper-size" data-size="A5"><i class="fa-solid fa-file-lines"></i> A5</button>
          <button type="button" class="btn btn-outline btn-sm btn-paper-size" data-size="A4"><i class="fa-solid fa-file"></i> A4</button>
          <button type="button" class="btn btn-outline btn-sm btn-paper-size" data-size="Thermal"><i class="fa-solid fa-receipt"></i> Thermal 80mm</button>
        </div>

        <button type="button" class="modal-close">&times;</button>
      </div>

      <div id="print-modal-body" class="print-modal-body">
        <!-- Dynamic Print HTML rendered here -->
      </div>

      <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;" class="no-print">
        <button type="button" class="btn btn-success" id="btn-share-whatsapp" style="background:#25D366; border-color:#25D366; color:#ffffff;">
          <i class="fa-brands fa-whatsapp" style="font-size:16px;"></i> <span data-i18n="btn_send_whatsapp">Send via WhatsApp</span>
        </button>

        <div style="display:flex; gap:12px;">
          <button type="button" class="btn btn-outline btn-modal-close" data-i18n="btn_close">Close</button>
          <button type="button" class="btn btn-primary" id="btn-trigger-print">
            <i class="fa-solid fa-print"></i> <span data-i18n="btn_print_now">Print Now</span>
          </button>
        </div>
      </div>
    </div>
  </div>
