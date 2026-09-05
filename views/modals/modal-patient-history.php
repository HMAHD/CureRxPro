  <!-- MODAL 4: Patient Medical History Timeline -->
  <div class="modal-overlay" id="modal-patient-history" style="z-index: 1050;">
    <div class="modal-content" style="max-width: 800px; width: 95%;">
      <div class="modal-header">
        <h3><i class="fa-solid fa-clock-rotate-left"></i> <span data-i18n="title_patient_history">Patient Medical History Timeline</span></h3>
        <button type="button" class="modal-close">&times;</button>
      </div>

      <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding: 15px;">
        <!-- Patient Summary Box -->
        <div class="glass-card" style="padding: 12px 18px; margin-bottom: 20px; background: var(--bg-card); display: flex; flex-wrap: wrap; gap: 15px; justify-content: space-between; border-color: var(--teal-600);">
          <div>
            <h4 style="margin: 0; color: var(--teal-700); font-weight: 800; font-size:16px;" id="hist-pt-name">--</h4>
            <p style="margin: 4px 0 0 0; font-size: 13px; font-weight: 700; color: var(--text-heading);" id="hist-pt-code-gender">--</p>
          </div>
          <div>
            <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Blood Group</span>
            <p style="margin: 2px 0 0 0; font-weight: 800; color: var(--text-heading);" id="hist-pt-blood">--</p>
          </div>
          <div>
            <span style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Allergies / Conditions</span>
            <p style="margin: 2px 0 0 0; font-weight: 800; color: var(--rose); font-size: 13px;" id="hist-pt-allergies">--</p>
          </div>
        </div>

        <!-- Timeline Items Container -->
        <div id="history-timeline-container" style="position: relative; padding-left: 24px; border-left: 2.5px solid var(--teal-100); margin-left: 10px;">
          <!-- Dynamic Timeline items go here -->
        </div>
      </div>

      <div style="margin-top: 20px; text-align: right;">
        <button type="button" class="btn btn-outline btn-modal-close" data-i18n="btn_close">Close</button>
      </div>
    </div>
  </div>
