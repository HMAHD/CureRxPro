    <!-- Tab 6: Clinic Settings -->
    <section class="tab-content" id="tab-settings">
      <div style="display:grid; grid-template-columns: 1fr 340px; gap: 20px;">

        <!-- Profile Form -->
        <div class="glass-card">
          <div class="card-title">
            <span><i class="fa-solid fa-sliders"></i> <span data-i18n="title_settings">Doctor & Clinic Profile Settings</span></span>
          </div>

          <form id="form-settings">
            <div class="form-group">
              <label data-i18n="lbl_clinic_name">Clinic Name</label>
              <input type="text" class="form-control" id="set-clinic-name" required>
            </div>

            <div class="form-group">
              <label data-i18n="lbl_clinic_address">Clinic Address</label>
              <input type="text" class="form-control" id="set-clinic-address" required>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label data-i18n="lbl_clinic_phone">Contact Phone Numbers</label>
                <input type="text" class="form-control" id="set-clinic-phone" required>
              </div>
              <div class="form-group">
                <label data-i18n="lbl_paper_size">Default Print Paper Size</label>
                <select class="form-control" id="set-paper-size">
                  <option value="A5">A5 (Standard Prescription Size)</option>
                  <option value="A4">A4 (Full Page)</option>
                  <option value="Thermal">Thermal Receipt (80mm)</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label data-i18n="lbl_practice_mode">Doctor Practice Mode</label>
              <select class="form-control" id="set-practice-mode" style="font-weight:700;">
                <option value="dispensary" data-i18n="opt_mode_dispensary">🏥 In-House Dispensary with Billing (ඩිස්පෙන්සරි)</option>
                <option value="channeling" data-i18n="opt_mode_channeling">🩺 Channeling / Pharmacy Rx Only (චැනලින් / ෆාමසි)</option>
            <div class="form-group">
              <label>Custom Clinic Logo (Image URL / Base64)</label>
              <input type="text" class="form-control" id="set-clinic-logo" placeholder="e.g. https://example.com/logo.png or data:image/png;base64,...">
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 10px;">
              <i class="fa-solid fa-floppy-disk"></i> <span data-i18n="btn_save_settings">Save Settings</span>
            </button>
          </form>

          <!-- Doctors Directory Card -->
          <div class="glass-card" style="margin-top: 20px;">
            <div class="card-title">
              <span><i class="fa-solid fa-user-doctor"></i> <span data-i18n="title_doctors_directory">Manage Doctors Profiles</span></span>
            </div>
            <form id="form-doctor-profile" style="margin-bottom: 18px;">
              <input type="hidden" id="doc-profile-id">
              <div class="form-group">
                <label>Doctor Name *</label>
                <input type="text" class="form-control" id="doc-profile-name" placeholder="e.g. Dr. Sampath Perera" required>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Qualifications</label>
                  <input type="text" class="form-control" id="doc-profile-qualifications" placeholder="e.g. MBBS, MD" required>
                </div>
                <div class="form-group">
                  <label>SLMC Reg. No</label>
                  <input type="text" class="form-control" id="doc-profile-slmc" placeholder="e.g. SLMC 34821" required>
                </div>
              </div>
              <div style="margin-top: 10px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary btn-sm" id="btn-save-doctor-profile">
                  <i class="fa-solid fa-plus"></i> Add Doctor Profile
                </button>
                <button type="button" class="btn btn-outline btn-sm" id="btn-clear-doctor-profile" style="display: none;">Cancel / Clear</button>
              </div>
            </form>

            <div style="overflow-x:auto;">
              <table class="data-table" style="font-size: 13px;">
                <thead>
                  <tr>
                    <th>Doctor Name</th>
                    <th>Qualifications</th>
                    <th>SLMC No</th>
                    <th style="width: 120px;">Actions</th>
                  </tr>
                </thead>
                <tbody id="tbody-profile-doctors">
                  <!-- Dynamically populated -->
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Monthly Subscription & Licensing Card -->
        <div>
          <div class="glass-card" style="border-color: var(--teal-600);">
            <div class="card-title">
              <span><i class="fa-solid fa-key"></i> Monthly Subscription</span>
            </div>

            <div style="margin-bottom: 12px;">
              <span style="font-size: 11px; font-weight:700; color: var(--text-muted); text-transform: uppercase;">Current Plan</span>
              <h4 style="color: var(--teal-700); font-size: 15px; font-weight: 800; margin-top:2px;">Monthly Pro (LKR 3,000/mo)</h4>
            </div>

            <div style="margin-bottom: 12px; display:flex; justify-content:space-between; align-items:center;">
              <div>
                <span style="font-size: 11px; font-weight:700; color: var(--text-muted); text-transform: uppercase;">Status</span>
                <div style="margin-top:2px;">
                  <span class="stat-pill" id="sub-status-pill" style="background:#f0fdf4; border-color:#bbf7d0; color:#166534; font-weight:700;">
                    <i class="fa-solid fa-circle-check"></i> Active
                  </span>
                </div>
              </div>
              <div style="text-align:right;">
                <span style="font-size: 11px; font-weight:700; color: var(--text-muted); text-transform: uppercase;" data-i18n="lbl_days_remaining">Days Remaining</span>
                <h4 style="color: var(--teal-600); font-size: 16px; font-weight: 800; margin-top:2px;" id="sub-days-remaining-display">-- Days</h4>
              </div>
            </div>

            <div style="margin-bottom: 14px;">
              <span style="font-size: 11px; font-weight:700; color: var(--text-muted); text-transform: uppercase;">Valid Until</span>
              <p style="font-weight: 700; color: var(--text-heading); font-size: 13.5px;" id="sub-expiry-display">2026-12-31</p>
            </div>

            <div style="border-top: 1px solid var(--border-subtle); padding-top: 14px; margin-top: 14px;">
              <label style="font-size: 12px; font-weight: 700; color: var(--text-muted);">Enter Registered Email Address</label>
              <input type="email" class="form-control" id="license-key-input" placeholder="e.g. doctor@gmail.com" style="margin-top: 4px;">
              <button type="button" class="btn btn-success btn-sm" id="btn-activate-license" style="width: 100%; margin-top: 8px;">
                <i class="fa-solid fa-unlock"></i> Activate / Extend Subscription
              </button>
            </div>

            <!-- Software Updates Card Section -->
            <div style="border-top: 1px solid var(--border-subtle); padding-top: 14px; margin-top: 14px;">
              <label style="font-size: 12px; font-weight: 700; color: var(--text-muted);">Software Updates</label>
              <button type="button" class="btn btn-outline btn-sm" id="btn-check-updates" style="width: 100%; margin-top: 6px;">
                <i class="fa-solid fa-rotate"></i> <span data-i18n="btn_check_updates">Check for Updates</span>
              </button>
              <p id="update-status-msg" style="font-size: 11px; color: var(--emerald-700); font-weight: 600; margin-top: 6px; display: none;" data-i18n="update_latest_msg">
                ✓ You are running the latest version of CureRx Pro (v2.5.0)
              </p>
            </div>
          </div>
        </div>

        <!-- Database Backup & Data Safety Card -->
        <div class="glass-card" style="grid-column: 1 / -1; margin-top: 10px; border-color: var(--teal-600);">
          <div class="card-title">
            <span><i class="fa-solid fa-database"></i> <span data-i18n="title_backup_restore">Database Backup & Data Safety</span></span>
          </div>
          <p style="color:var(--text-muted);font-size:13.5px;margin-bottom:16px;" data-i18n="desc_backup_restore">
            Export and download a full snapshot of your clinic database to a secure file, or restore from a previous backup.
          </p>
          <div style="display:flex; gap:14px; flex-wrap:wrap; align-items:center;">
            <a href="api/backup.php?action=export" class="btn btn-primary" id="btn-export-backup" download>
              <i class="fa-solid fa-download"></i> <span data-i18n="btn_download_backup">Download Full Backup (.json)</span>
            </a>

            <label class="btn btn-outline" style="margin:0; cursor:pointer;">
              <i class="fa-solid fa-upload"></i> <span data-i18n="btn_restore_backup">Restore Database</span>
              <input type="file" id="backup-file-input" accept=".json" style="display:none;">
            </label>
          </div>
        </div>

      </div>
    </section>
