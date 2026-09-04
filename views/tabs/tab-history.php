    <!-- Tab 4: Prescription History -->
    <section class="tab-content" id="tab-history">
      <div class="glass-card">
        <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
          <span><i class="fa-solid fa-clock-rotate-left"></i> <span data-i18n="title_history">Prescription History Archive</span></span>
          <div style="display:flex; align-items:center; gap:8px;">
            <label style="font-size:12px; font-weight:700; color:var(--text-muted); margin:0;">Filter by Doctor:</label>
            <select id="history-doctor-filter" class="form-control" style="width:180px; font-size:12px; padding:4px 8px; height:auto; margin:0;">
              <option value="all">All Doctors</option>
              <!-- Loaded dynamically -->
            </select>
          </div>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th data-i18n="th_rx_no">Rx Number</th>
                <th data-i18n="th_patient_name">Patient Name</th>
                <th data-i18n="th_date">Date & Time</th>
                <th data-i18n="th_items_count">Items Count</th>
                <th data-i18n="lbl_diagnosis">Diagnosis</th>
                <th data-i18n="th_actions">Actions</th>
              </tr>
            </thead>
            <tbody id="tbody-history">
              <!-- Dynamically Populated -->
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls (Issue #23) -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px; padding-top:12px; border-top:1px solid var(--border-subtle);">
          <div style="font-size:12.5px; color:var(--text-muted); font-weight:700;" id="history-pagination-info">Showing page 1 of 1</div>
          <div style="display:flex; gap:8px;">
            <button class="btn btn-outline btn-sm" id="btn-history-prev" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
            <button class="btn btn-outline btn-sm" id="btn-history-next" disabled>Next <i class="fa-solid fa-chevron-right"></i></button>
          </div>
        </div>
      </div>
    </section>
