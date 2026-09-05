    <!-- Tab 2.6: Channeling Billing Tab -->
    <section class="tab-content <?php echo $app_role === 'receptionist' ? 'active' : ''; ?>" id="tab-channeling-billing">
      <div class="rx-grid">

        <!-- Left Panel: Issue Bill Form -->
        <div class="left-panel">
          <div class="glass-card">
            <div class="card-title">
              <span><i class="fa-solid fa-file-invoice-dollar" style="color:var(--teal-600);"></i> <span data-i18n="title_issue_channeling_bill">Issue Channeling Receipt</span></span>
            </div>

            <!-- Patient search/select -->
            <div class="form-group" style="position:relative;">
              <label>Select Patient *</label>
              <input type="text" class="form-control" id="chl-patient-search" placeholder="🔍 Search patient by Name / Phone..." autocomplete="off">
              <div class="autocomplete-dropdown" id="chl-patient-dropdown" style="display:none; max-height:220px; overflow-y:auto; position:absolute; z-index:100; width:100%; background:#ffffff; border:1px solid var(--border-subtle); border-radius:6px; box-shadow:0 4px 6px rgba(0,0,0,0.05);"></div>
            </div>

            <!-- Selected Patient details card -->
            <div id="chl-selected-patient-badge" class="patient-info-badge" style="display:none; margin-bottom:15px; background:var(--teal-50); border:1px solid var(--teal-200); padding:10px; border-radius:8px;">
              <input type="hidden" id="chl-selected-patient-id" value="">
              <div style="font-weight:800; color:var(--teal-700);" id="chl-selected-patient-name">--</div>
              <small style="color:var(--text-muted);" id="chl-selected-patient-meta">--</small>
            </div>

            <!-- Doctor Select -->
            <div class="form-group">
              <label>Select Consulting Doctor *</label>
              <select id="chl-doctor-select" class="form-control" style="font-weight:700;">
                <!-- Populated dynamically -->
              </select>
            </div>

            <!-- Fees row -->
            <div class="form-row">
              <div class="form-group">
                <label>Doctor Fee (LKR) *</label>
                <input type="number" class="form-control chl-calc-input" id="chl-doctor-fee" value="1500" step="50">
              </div>
              <div class="form-group">
                <label>Hospital / Center Fee (LKR) *</label>
                <input type="number" class="form-control chl-calc-input" id="chl-hospital-fee" value="500" step="50">
              </div>
            </div>

            <!-- Grand Total display -->
            <div class="form-group" style="margin-top:15px;">
              <label style="color:var(--teal-700); font-weight:800;">Total Amount (LKR)</label>
              <input type="number" class="form-control" id="chl-grand-total" value="2000" readonly style="background:#f0fdf4; border-color:#bbf7d0; color:#166534; font-weight:800; font-size:16px;">
            </div>

            <div style="margin-top:20px; display:flex; gap:10px;">
              <button type="button" class="btn btn-outline" id="btn-phone-book-channeling" style="flex:1; padding:12px; font-size:14px; font-weight:800; border-color:var(--teal-600); color:var(--teal-600);">
                <i class="fa-solid fa-phone"></i> Phone Booking
              </button>
              <button type="button" class="btn btn-success" id="btn-print-channeling-bill" style="flex:1.5; padding:12px; font-size:14px; font-weight:800;">
                <i class="fa-solid fa-print"></i> Issue & Print
              </button>
            </div>
          </div>
        </div>

        <!-- Right Panel: Today's printed bills log -->
        <div class="right-panel">
          <div class="glass-card">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
              <span><i class="fa-solid fa-clock-rotate-left"></i> Today's Issued Receipts</span>
              <button type="button" class="btn btn-outline btn-sm" id="btn-refresh-chl-bills-list" style="padding:2px 8px; font-size:11px;"><i class="fa-solid fa-rotate"></i> Refresh</button>
            </div>

            <div style="overflow-x:auto;">
              <table class="data-table" style="font-size:12.5px;">
                <thead>
                  <tr>
                    <th>Bill Number</th>
                    <th>Patient Name</th>
                    <th>Doctor</th>
                    <th>Token</th>
                    <th>Total Paid</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="tbody-channeling-bills">
                  <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No receipts issued today.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </section>
