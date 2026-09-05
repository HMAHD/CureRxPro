    <!-- Top Header Bar -->
    <header class="top-header">
      <div>
        <h1 class="page-headline" id="page-headline" data-i18n="nav_new_rx">New Prescription</h1>
      </div>

      <div class="quick-stats-bar">
        <!-- Active Doctor Dropdown -->
        <div class="stat-pill" style="padding:4px 12px; border-color:var(--teal-600);">
          <i class="fa-solid fa-user-doctor" style="color:var(--teal-600);"></i>
          <select id="active-doctor-select" style="background:transparent;border:none;color:var(--text-heading);font-weight:700;font-size:13px;cursor:pointer;outline:none;max-width:180px;">
            <!-- Loaded dynamically -->
          </select>
        </div>

        <!-- Language Switcher Dropdown -->
        <div class="stat-pill" style="padding:4px 12px; border-color:var(--teal-600);">
          <i class="fa-solid fa-globe"></i>
          <select id="app-language-select" style="background:transparent;border:none;color:var(--text-heading);font-weight:700;font-size:13px;cursor:pointer;outline:none;">
            <option value="en">🇬🇧 English</option>
            <option value="si">🇱🇰 සිංහල</option>
            <option value="ta">🇱🇰 தமிழ்</option>
          </select>
        </div>

        <!-- License Days Remaining Badge -->
        <div class="stat-pill" id="stat-license-badge">
          <i class="fa-solid fa-clock"></i> <span data-i18n="stat_license_days">License</span>: <strong id="stat-license-days-count" style="color:var(--teal-600);">—</strong>
        </div>

        <div class="stat-pill">
          <i class="fa-solid fa-calendar-day"></i> <span data-i18n="stat_today_rx">Today's Rx</span>: <strong id="stat-today-rx">0</strong>
        </div>
        <div class="stat-pill" style="border-color:var(--emerald-600);">
          <i class="fa-solid fa-coins" style="color:var(--emerald-600);"></i> <span>Revenue Today</span>: <strong id="stat-today-revenue" style="color:var(--emerald-700);">LKR 0.00</strong>
        </div>
        <div class="stat-pill">
          <i class="fa-solid fa-user-group"></i> <span data-i18n="stat_patients">Patients</span>: <strong id="stat-total-patients">0</strong>
        </div>
        <div class="stat-pill">
          <i class="fa-solid fa-tablets"></i> <span data-i18n="stat_drugs">Drugs DB</span>: <strong id="stat-total-meds">0</strong>
        </div>
        <button class="btn btn-primary" id="btn-new-patient-modal">
          <i class="fa-solid fa-user-plus"></i> <span data-i18n="btn_new_patient">+ New Patient</span>
        </button>
      </div>
    </header>

    <!-- 7-Day Expiry Warning Banner (Shown when days_remaining <= 7) -->
    <div id="expiry-warning-banner" style="display:none; background:#fff1f2; border:1px solid #fecdd3; padding:12px 18px; border-radius:10px; margin-bottom:18px; align-items:center; justify-content:space-between; color:#9f1239; box-shadow:0 4px 12px rgba(225,29,72,0.12);">
      <div style="display:flex;align-items:center;gap:10px;font-weight:600;font-size:13.5px;">
        <i class="fa-solid fa-triangle-exclamation" style="font-size:18px;color:#e11d48;"></i>
        <span id="expiry-warning-text">Warning: Your monthly subscription expires soon! Please contact support to renew.</span>
      </div>
      <button type="button" class="btn btn-danger btn-sm" id="btn-renew-banner-action">
        <i class="fa-solid fa-key"></i> <span data-i18n="btn_renew_now">Renew Now</span>
      </button>
    </div>
