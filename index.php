<?php
// index.php - Clean Multilingual Medical SaaS Prescription System
require_once __DIR__ . '/config/db.php';
if (!$db_connected) {
    include __DIR__ . '/db_setup.php';
    exit;
}

// Load App Role configuration
$app_role = 'both';
$configPath = __DIR__ . '/config/db_config.json';
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true);
    $app_role = $config['app_role'] ?? 'both';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CureRx Pro - Digital Prescription System</title>
  
  <!-- FontAwesome 6 Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  
  <!-- Custom Stylesheets -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/print.css">

  <style>
    body.role-receptionist [data-tab="tab-builder"],
    body.role-receptionist [data-tab="tab-templates"],
    body.role-receptionist [data-tab="tab-medicines"],
    body.role-receptionist [data-tab="tab-history"] {
      display: none !important;
    }
    body.role-doctor [data-tab="tab-channeling-billing"] {
      display: none !important;
    }
  </style>
</head>
<body class="role-<?php echo $app_role; ?>">

  <!-- Sidebar Navigation -->
  <aside class="app-sidebar">
    <div class="brand-header">
      <div class="brand-icon">
        <i class="fa-solid fa-notes-medical"></i>
      </div>
      <div>
        <div class="brand-title">CureRx Pro</div>
        <div class="brand-sub">Medical Suite</div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a class="nav-item <?php echo $app_role === 'receptionist' ? '' : 'active'; ?>" data-tab="tab-builder">
        <i class="fa-solid fa-file-pen"></i>
        <span data-i18n="nav_new_rx">New Prescription</span>
      </a>
      <a class="nav-item" data-tab="tab-patients">
        <i class="fa-solid fa-users"></i>
        <span data-i18n="nav_patients">Patients Directory</span>
      </a>
      <a class="nav-item" data-tab="tab-queue-monitor">
        <i class="fa-solid fa-list-ol"></i>
        <span data-i18n="nav_queue_monitor">Live Queue Monitor</span>
      </a>
      <a class="nav-item <?php echo $app_role === 'receptionist' ? 'active' : ''; ?>" data-tab="tab-channeling-billing">
        <i class="fa-solid fa-file-invoice-dollar"></i>
        <span data-i18n="nav_channeling_billing">Channeling Receipt</span>
      </a>
      <a class="nav-item" data-tab="tab-medicines">
        <i class="fa-solid fa-pills"></i>
        <span data-i18n="nav_drugs">Drug Master DB</span>
      </a>
      <a class="nav-item" data-tab="tab-history">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span data-i18n="nav_history">Prescription History</span>
      </a>
      <a class="nav-item" data-tab="tab-templates">
        <i class="fa-solid fa-bolt"></i>
        <span data-i18n="nav_templates">Quick Templates</span>
      </a>
      <a class="nav-item" data-tab="tab-settings">
        <i class="fa-solid fa-sliders"></i>
        <span data-i18n="nav_settings">Clinic Settings</span>
      </a>
    </nav>

    <div class="sidebar-footer">
      <i class="fa-solid fa-shield-heart" style="color:var(--teal-600);"></i>
      <span data-i18n="nav_version">CureRx v2.5 | Active</span>
    </div>
  </aside>

  <!-- Main Application Body -->
  <main class="app-main">
    
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
          <i class="fa-solid fa-clock"></i> <span data-i18n="stat_license_days">License</span>: <strong id="stat-license-days-count" style="color:var(--teal-600);">-- Days</strong>
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

    <!-- Tab 1: Prescription Builder -->
    <section class="tab-content <?php echo $app_role === 'receptionist' ? '' : 'active'; ?>" id="tab-builder">
      <div class="rx-grid">
        
        <!-- Left Panel: Patient Details & Vitals -->
        <div class="left-panel">
          
          <!-- Waiting Patient Queue Card -->
          <div class="glass-card" style="border-color: var(--teal-600); padding: 12px 15px;">
            <div class="card-title" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
              <span><i class="fa-solid fa-list-ol" style="color:var(--teal-600);"></i> <span data-i18n="title_waiting_queue">Today's Patient Queue</span></span>
              <span class="badge" id="queue-count-badge" style="background:var(--teal-600); color:#ffffff; font-weight:800; padding:2px 8px; border-radius:5px; font-size:11px;">0</span>
            </div>
            
            <div id="queue-list-container" style="max-height: 180px; overflow-y: auto;">
              <div style="padding: 10px; text-align: center; color: var(--text-muted); font-size:12.5px;">No patients in queue.</div>
            </div>
          </div>
          
          <!-- Patient Selection Box -->
          <div class="glass-card">
            <div class="card-title">
              <span><i class="fa-solid fa-user-check"></i> <span data-i18n="title_patient_info">Patient Information</span></span>
            </div>
            
            <input type="hidden" id="selected-patient-id" value="">
            
            <div class="patient-select-box autocomplete-wrapper">
              <input type="text" class="form-control" id="patient-search-input" data-i18n-placeholder="ph_search_patient" placeholder="🔍 Search patient by Name / Phone..." autocomplete="off">
              <div class="autocomplete-dropdown" id="patient-dropdown" style="display:none;"></div>
            </div>

            <!-- Selected Patient Summary Badge -->
            <div id="selected-patient-badge" class="patient-info-badge" style="display:none;"></div>
          </div>

          <!-- Patient Vitals Card -->
          <div class="glass-card">
            <div class="card-title">
              <span><i class="fa-solid fa-heart-pulse"></i> <span data-i18n="title_vitals">Patient Vitals</span></span>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label data-i18n="lbl_bp">BP (mmHg)</label>
                <input type="text" class="form-control" id="vitals-bp" placeholder="120/80">
              </div>
              <div class="form-group">
                <label data-i18n="lbl_pulse">Pulse (bpm)</label>
                <input type="text" class="form-control" id="vitals-pulse" placeholder="72">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label data-i18n="lbl_weight">Weight (kg)</label>
                <input type="text" class="form-control" id="vitals-weight" placeholder="65">
              </div>
              <div class="form-group">
                <label data-i18n="lbl_temp">Temp (°F)</label>
                <input type="text" class="form-control" id="vitals-temp" placeholder="98.6">
              </div>
            </div>
          </div>

        </div>

        <!-- Right Panel: Rx Medicine Builder & Action -->
        <div class="right-panel">
          
          <!-- High-Priority Allergy Alert Banner Container (Issue #6) -->
          <div id="patient-allergy-alert-container" style="display:none;"></div>

          <!-- Chief Complaints & Diagnosis -->
          <div class="glass-card">
            <div class="card-title">
              <span><i class="fa-solid fa-stethoscope"></i> <span data-i18n="title_clinical_notes">Clinical Notes & Diagnosis</span></span>
            </div>

            <div class="form-group">
              <label data-i18n="lbl_complaints">Chief Complaints (Symptoms)</label>
              <textarea class="form-control" id="rx-complaint" rows="2" data-i18n-placeholder="ph_complaints" placeholder="e.g. Fever x 2 days, Cough, Body aches"></textarea>
            </div>

            <div class="form-group">
              <label data-i18n="lbl_diagnosis">Diagnosis / Disease (ලෙඩාගේ ලෙඩේ)</label>
              <input type="text" class="form-control" id="rx-diagnosis" data-i18n-placeholder="ph_diagnosis" placeholder="Select below or type disease (e.g. Gastritis, Diabetes, Fever)..." autocomplete="off">
            </div>

            <!-- Quick Selectable Disease Chips Bar -->
            <div class="form-group" style="margin-top: 10px;">
              <label style="font-size: 11px; font-weight: 700; color: var(--teal-700);" data-i18n="lbl_disease_chips">Quick Select Illness / Disease:</label>
              <div class="template-chips" id="disease-quick-chips" style="margin-top: 6px;">
                <span class="template-chip disease-chip" data-val="Fever / Flu (උණ)">🌡️ Fever / Flu</span>
                <span class="template-chip disease-chip" data-val="Gastritis (ගැස්ට්‍රයිටිස්)">🩺 Gastritis</span>
                <span class="template-chip disease-chip" data-val="Hypertension (අධික රුධිර පීඩනය)">🩸 Hypertension</span>
                <span class="template-chip disease-chip" data-val="Diabetes (දියවැඩියාව)">🍬 Diabetes</span>
                <span class="template-chip disease-chip" data-val="Asthma / URTI (ඇදුම)">🫁 Asthma / URTI</span>
                <span class="template-chip disease-chip" data-val="Joint Pain / Arthritis">🦵 Joint Pain</span>
                <span class="template-chip disease-chip" data-val="Headache / Migraine">🤕 Headache</span>
              </div>
            </div>
          </div>

          <div class="glass-card">
            
            <div class="card-title">
              <span><i class="fa-solid fa-prescription"></i> <span data-i18n="title_prescribed_meds">Prescribed Medicines</span></span>
            </div>

            <!-- Quick Templates Bar -->
            <div class="template-chips" id="template-chips-container">
              <!-- Dynamically populated chips -->
            </div>

            <!-- Medicines Dynamic Table -->
            <div class="rx-table-container">
              <table class="rx-table">
                <thead>
                  <tr>
                    <th style="width: 22%;" data-i18n="th_med_name">Medicine Name</th>
                    <th style="width: 10%;" data-i18n="th_strength">Strength</th>
                    <th style="width: 11%;" data-i18n="th_dosage">Dosage</th>
                    <th style="width: 16%;" data-i18n="th_instruction">Instruction</th>
                    <th style="width: 9%;" data-i18n="th_duration">Duration</th>
                    <th style="width: 6%;" data-i18n="th_qty">Qty</th>
                    <th style="width: 11%;" data-i18n="th_source">Source</th>
                    <th style="width: 7%;" data-i18n="th_unit_price">Price/Unit</th>
                    <th style="width: 8%;" data-i18n="th_item_total">Amount</th>
                    <th style="width: 3%;"></th>
                  </tr>
                </thead>
                <tbody id="rx-items-tbody">
                  <!-- Dynamic Rx Rows Inserted Here -->
                </tbody>
              </table>
            </div>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; flex-wrap:wrap; gap:8px;">
              <button type="button" class="btn btn-outline btn-sm" id="btn-add-rx-row">
                <i class="fa-solid fa-plus"></i> <span data-i18n="btn_add_row">+ Add Another Medicine</span>
              </button>
              <button type="button" class="btn btn-outline btn-sm" id="btn-pediatric-calc" style="color:var(--teal-700); border-color:var(--teal-600); font-weight:700;">
                <i class="fa-solid fa-calculator"></i> <span>Pediatric Dosage Calculator</span>
              </button>
            </div>
          </div>

          <!-- Doctor's Advice & Dispensary Pharmacy Billing Bar -->
          <div class="glass-card">
            <div class="form-row">
              <div class="form-group" style="flex:2;">
                <label data-i18n="lbl_advice">Doctor's Special Advice</label>
                <input type="text" class="form-control" id="rx-advice" data-i18n-placeholder="ph_advice" placeholder="e.g. Drink warm water, Rest well for 3 days.">
              </div>
              <div class="form-group" style="flex:1;">
                <label data-i18n="lbl_followup">Follow-up Date</label>
                <input type="date" class="form-control" id="rx-followup">
              </div>
            </div>

            <!-- Dispensary Bill Breakdown (Toggleable) -->
            <div id="dispensary-billing-wrapper" style="background: #f8fafc; border: 1px solid var(--border-subtle); border-radius: 10px; padding: 14px 18px; margin-top: 14px;">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px; flex-wrap:wrap; gap:8px;">
                <div style="font-size: 13px; font-weight: 800; color: var(--teal-700);" data-i18n="title_dispensary_billing">
                  <i class="fa-solid fa-receipt"></i> Dispensary & Pharmacy Billing
                </div>
                <label style="display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; cursor:pointer; color:var(--text-heading); margin:0;">
                  <input type="checkbox" id="chk-include-bill" checked style="width:16px; height:16px; cursor:pointer; accent-color:var(--teal-600);">
                  <span data-i18n="lbl_include_bill">Include Dispensary Bill on Printout</span>
                </label>
              </div>

              <div id="billing-inputs-row" class="form-row">
                <div class="form-group">
                  <label data-i18n="lbl_consultation_fee">Consultation Fee (LKR)</label>
                  <input type="number" class="form-control bill-calc-input" id="bill-consultation-fee" value="1000" step="50">
                </div>
                <div class="form-group">
                  <label data-i18n="lbl_meds_subtotal">Medicines Total (LKR)</label>
                  <input type="number" class="form-control" id="bill-meds-total" value="0" readonly style="background:#f1f5f9; font-weight:700;">
                </div>
                <div class="form-group">
                  <label data-i18n="lbl_discount">Discount (LKR)</label>
                  <input type="number" class="form-control bill-calc-input" id="bill-discount" value="0" step="10">
                </div>
                <div class="form-group">
                  <label style="color:var(--teal-700);font-weight:800;" data-i18n="lbl_grand_total">Net Grand Total (LKR)</label>
                  <input type="number" class="form-control" id="bill-grand-total" value="1000" readonly style="background:#f0fdf4; border-color:#bbf7d0; color:#166534; font-weight:800; font-size:16px;">
                </div>
              </div>
            </div>

            <div style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 12px;">
              <button type="button" class="btn btn-success" id="btn-save-print-rx" style="padding: 12px 28px; font-size: 15px;">
                <i class="fa-solid fa-print"></i> <span data-i18n="btn_save_print">Save & Print Prescription / Bill</span>
              </button>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Tab 2: Patients Directory -->
    <section class="tab-content" id="tab-patients">
      <div class="glass-card">
        <div class="card-title">
          <span><i class="fa-solid fa-users"></i> <span data-i18n="title_patients_dir">Registered Patients Directory</span></span>
        </div>
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th data-i18n="th_patient_code">Code</th>
                <th data-i18n="th_patient_name">Patient Name</th>
                <th data-i18n="th_age_gender">Age / Gender</th>
                <th data-i18n="th_phone">Phone Number</th>
                <th data-i18n="th_blood">Blood Group</th>
                <th data-i18n="th_allergies">Allergies</th>
                <th data-i18n="th_actions">Actions</th>
              </tr>
            </thead>
            <tbody id="tbody-patients">
              <!-- Dynamically Populated -->
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Tab 2.5: Live Queue Monitor Dashboard -->
    <section class="tab-content" id="tab-queue-monitor">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
        <h2 style="font-size:18px; font-weight:800; color:var(--teal-700); margin:0;"><i class="fa-solid fa-list-ol"></i> <span data-i18n="title_live_queue_dashboard">Live Queue Monitor Dashboard</span></h2>
        <button type="button" class="btn btn-outline btn-sm" id="btn-refresh-queue-monitor"><i class="fa-solid fa-rotate"></i> Refresh Dashboard</button>
      </div>

      <div class="rx-grid" id="queue-monitor-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:20px;">
        <!-- Dynamically loaded Doctor Queue Cards -->
      </div>
    </section>

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

    <!-- Tab 3: Medicine Master Database -->
    <section class="tab-content" id="tab-medicines">
      <div class="glass-card">
        <div class="card-title">
          <span><i class="fa-solid fa-pills"></i> <span data-i18n="title_drug_master">Drug Master Database</span></span>
          <button class="btn btn-primary btn-sm" id="btn-new-medicine-modal">
            <i class="fa-solid fa-plus"></i> <span data-i18n="btn_add_drug">+ Add New Drug</span>
          </button>
        </div>

        <!-- Category Filter Pills (Issue #15) -->
        <div class="template-chips" id="med-category-filter-chips" style="margin-bottom: 16px;">
          <div class="template-chip active" data-cat="all" style="background:var(--teal-600); color:#fff;">All Medicines</div>
          <div class="template-chip" data-cat="Tablet">Tablets</div>
          <div class="template-chip" data-cat="Capsule">Capsules</div>
          <div class="template-chip" data-cat="Syrup">Syrups</div>
          <div class="template-chip" data-cat="Injection">Injections</div>
          <div class="template-chip" data-cat="Cream/Ointment">Creams/Ointments</div>
          <div class="template-chip" data-cat="Drops">Drops</div>
          <div class="template-chip" data-cat="Inhaler">Inhalers</div>
        </div>

        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead>
              <tr>
                <th data-i18n="th_drug_name">Drug Name</th>
                <th data-i18n="th_generic_name">Generic Name</th>
                <th data-i18n="th_type">Type</th>
                <th data-i18n="th_default_strength">Default Strength</th>
                <th data-i18n="th_unit_price">Unit Price (LKR)</th>
                <th data-i18n="th_actions">Action</th>
              </tr>
            </thead>
            <tbody id="tbody-medicines">
              <!-- Dynamically Populated -->
            </tbody>
          </table>
        </div>
      </div>
    </section>

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

    <!-- Tab 5: Quick Templates -->
    <section class="tab-content" id="tab-templates">
      <div class="glass-card">
        <div class="card-title">
          <span><i class="fa-solid fa-bolt"></i> <span data-i18n="title_templates">Prescription Quick Templates</span></span>
        </div>
        <p style="color:var(--text-muted);font-size:13.5px;margin-bottom:16px;" data-i18n="desc_templates">
          Predefined medicine packages for instant 1-click prescription entry.
        </p>
        <div id="templates-manage-list">
          <!-- Dynamically populated templates -->
        </div>
      </div>
    </section>

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

  </main>

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

  <!-- MODAL 2: Register New Patient -->
  <div class="modal-overlay" id="modal-patient">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fa-solid fa-user-plus"></i> <span data-i18n="btn_new_patient">Register New Patient</span></h3>
        <button type="button" class="modal-close">&times;</button>
      </div>

      <form id="form-patient-modal">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" class="form-control" id="patient-name-in" placeholder="e.g. K.A. Sunethra Perera" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Age *</label>
            <input type="number" class="form-control" id="patient-age-in" placeholder="e.g. 35" required>
          </div>
          <div class="form-group">
            <label>Gender</label>
            <select class="form-control" id="patient-gender-in">
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Phone Number</label>
            <input type="text" class="form-control" id="patient-phone-in" placeholder="077-1234567">
          </div>
          <div class="form-group">
            <label>Blood Group</label>
            <select class="form-control" id="patient-blood-in">
              <option value="">Unknown</option>
              <option value="A+">A+</option>
              <option value="A-">A-</option>
              <option value="B+">B+</option>
              <option value="B-">B-</option>
              <option value="O+">O+</option>
              <option value="O-">O-</option>
              <option value="AB+">AB+</option>
              <option value="AB-">AB-</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Address</label>
          <input type="text" class="form-control" id="patient-address-in" placeholder="City or Street address">
        </div>

        <div class="form-group">
          <label>Known Allergies / Medical Conditions</label>
          <input type="text" class="form-control" id="patient-allergies-in" placeholder="e.g. Penicillin, Asthma">
        </div>

        <div style="margin-top: 20px; text-align: right;">
          <button type="button" class="btn btn-outline btn-modal-close">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Register Patient</button>
        </div>
      </form>
    </div>
  </div>

  <!-- MODAL 3: Add New Drug Master -->
  <div class="modal-overlay" id="modal-medicine">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fa-solid fa-capsules"></i> <span data-i18n="btn_add_drug">Add New Drug</span></h3>
        <button type="button" class="modal-close">&times;</button>
      </div>

      <form id="form-medicine-modal">
        <div class="form-group">
          <label>Drug Trade Name *</label>
          <input type="text" class="form-control" id="med-name-in" placeholder="e.g. Augmentin" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Generic Chemical Name</label>
            <input type="text" class="form-control" id="med-generic-in" placeholder="e.g. Co-amoxiclav">
          </div>
          <div class="form-group">
            <label>Form / Type</label>
            <select class="form-control" id="med-type-in">
              <option value="Tablet">Tablet</option>
              <option value="Capsule">Capsule</option>
              <option value="Syrup">Syrup</option>
              <option value="Injection">Injection</option>
              <option value="Cream/Ointment">Cream/Ointment</option>
              <option value="Drops">Drops</option>
              <option value="Inhaler">Inhaler</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Default Strength</label>
            <input type="text" class="form-control" id="med-strength-in" placeholder="e.g. 625mg">
          </div>
          <div class="form-group">
            <label>Unit Selling Price (LKR)</label>
            <input type="number" class="form-control" id="med-price-in" placeholder="e.g. 85.00" step="0.50" value="10.00" required>
          </div>
        </div>

        <div style="margin-top: 20px; text-align: right;">
          <button type="button" class="btn btn-outline btn-modal-close">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Add Drug</button>
        </div>
      </form>
    </div>
  </div>

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

  <!-- MODAL 5: Pediatric Body-Weight Dosage Calculator (Issue #7) -->
  <div class="modal-overlay" id="modal-pediatric-calc">
    <div class="modal-content" style="max-width: 580px;">
      <div class="modal-header">
        <h3><i class="fa-solid fa-calculator" style="color:var(--teal-600);"></i> <span>Pediatric Dosage Calculator</span></h3>
        <button type="button" class="modal-close">&times;</button>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Child Body Weight (kg) *</label>
          <input type="number" class="form-control" id="ped-weight-in" placeholder="e.g. 14.5" step="0.5" value="12">
        </div>
        <div class="form-group">
          <label>Select Medication Syrup *</label>
          <select class="form-control" id="ped-drug-select" style="font-weight:700;">
            <option value="paracetamol_120">Paracetamol Syrup (120mg / 5ml)</option>
            <option value="paracetamol_250">Paracetamol Syrup (250mg / 5ml)</option>
            <option value="amoxicillin_125">Amoxicillin Syrup (125mg / 5ml)</option>
            <option value="amoxicillin_250">Amoxicillin Syrup (250mg / 5ml)</option>
            <option value="ibuprofen_100">Ibuprofen Syrup (100mg / 5ml)</option>
            <option value="cetirizine_5">Cetirizine Syrup (5mg / 5ml)</option>
            <option value="domperidone_5">Domperidone Syrup (5mg / 5ml)</option>
          </select>
        </div>
      </div>

      <!-- Calculation Result Box -->
      <div style="background:var(--teal-50); border:1px solid var(--teal-100); border-radius:10px; padding:16px; margin-top:10px;">
        <h4 style="color:var(--teal-700); font-weight:800; font-size:14.5px; margin-bottom:8px;" id="ped-calc-title">Recommended Dose:</h4>
        <div style="font-size:18px; font-weight:800; color:var(--text-heading);" id="ped-calc-dose-ml">-- ml per dose</div>
        <div style="font-size:13px; color:var(--text-body); margin-top:4px;" id="ped-calc-details">--</div>
      </div>

      <div style="margin-top: 20px; display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" class="btn btn-outline btn-modal-close">Cancel</button>
        <button type="button" class="btn btn-primary" id="btn-apply-ped-dose"><i class="fa-solid fa-plus"></i> Apply to Prescription</button>
      </div>
    </div>
  </div>

  <!-- MODAL 6: Application Auto-Update Status Dialog (Issue #14) -->
  <div class="modal-overlay" id="modal-auto-update">
    <div class="modal-content" style="max-width: 540px;">
      <div class="modal-header">
        <h3><i class="fa-solid fa-cloud-arrow-down" style="color:var(--teal-600);"></i> <span>Software Update Available</span></h3>
        <button type="button" class="modal-close">&times;</button>
      </div>

      <div style="background:var(--bg-app); border:1px solid var(--border-subtle); padding:16px; border-radius:10px; margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <span style="font-size:12px; font-weight:700; color:var(--text-muted); text-transform:uppercase;">New Version Available</span>
          <span class="btn-sm" style="background:var(--teal-600); color:#fff; font-weight:800; border-radius:12px; padding:2px 10px;" id="update-modal-ver">v2.6.0</span>
        </div>
        <p style="font-size:13.5px; color:var(--text-heading); font-weight:600;" id="update-modal-notes">Includes critical security enhancements, fast-tab prescribing, and performance improvements.</p>
      </div>

      <!-- Download Progress Bar Container -->
      <div id="update-progress-container" style="display:none; margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; font-size:13px; font-weight:700; margin-bottom:6px; color:var(--text-heading);">
          <span id="update-progress-status-text">Downloading Update...</span>
          <span id="update-progress-percent">0%</span>
        </div>
        <div style="width:100%; height:10px; background:var(--border-subtle); border-radius:5px; overflow:hidden;">
          <div id="update-progress-bar-fill" style="width:0%; height:100%; background:linear-gradient(90deg, var(--teal-600), #0284c7); transition:width 0.2s ease;"></div>
        </div>
      </div>

      <div style="display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" class="btn btn-outline btn-modal-close">Remind Me Later</button>
        <button type="button" class="btn btn-primary" id="btn-start-download-update"><i class="fa-solid fa-download"></i> Install Update Now</button>
      </div>
    </div>
  </div>

  <!-- Subscription Lock Overlay -->
  <div id="subscription-lock-overlay" style="display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(15,23,42,0.96); backdrop-filter:blur(8px); align-items:center; justify-content:center; color:#ffffff; font-family:'Plus Jakarta Sans', sans-serif;">
    <div style="background:#1e293b; padding:40px; border-radius:16px; border:1px solid #334155; width:90%; max-width:480px; text-align:center; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
      <div style="font-size:50px; color:#ef4444; margin-bottom:20px;"><i class="fa-solid fa-lock"></i></div>
      <h2 style="font-size:24px; font-weight:800; margin-bottom:10px;">Subscription Expired</h2>
      <p style="color:#94a3b8; font-size:14px; margin-bottom:30px;">Your access to CureRx Pro has been locked. Please enter your registered email address or contact SmartHub to renew your subscription.</p>
      
      <div style="margin-bottom:20px;">
        <input type="email" id="lock-license-key-input" placeholder="Enter Registered Email Address" style="width:100%; padding:12px; border-radius:8px; border:1px solid #475569; background:#0f172a; color:#ffffff; font-size:14px; text-align:center; outline:none; font-weight:700;">
      </div>
      
      <button type="button" id="btn-activate-lock-license" style="width:100%; padding:12px; border:none; background:#0d9488; color:#ffffff; font-weight:700; font-size:14px; border-radius:8px; cursor:pointer; transition:background 0.2s; margin-bottom:25px;">
        <i class="fa-solid fa-unlock"></i> Activate Subscription
      </button>
      
      <div style="border-top:1px solid #334155; padding-top:20px; font-size:13px; color:#94a3b8; display:flex; flex-direction:column; gap:6px; align-items:center;">
        <p style="margin-bottom:4px;">Contact SmartHub for License Key:</p>
        <strong style="color:#38bdf8; font-size:14.5px;"><i class="fa-solid fa-phone"></i> +94 71 798 6399</strong>
        <strong style="color:#22c55e; font-size:14.5px;"><i class="fa-brands fa-whatsapp"></i> +94 71 078 0600</strong>
        <a href="https://smarthub.web.lk/curerx.php" target="_blank" style="color:#38bdf8; text-decoration:none; font-size:13.5px; font-weight:700; margin-top:4px;"><i class="fa-solid fa-globe"></i> smarthub.web.lk/curerx.php</a>
      </div>
    </div>
  </div>

  <!-- Dedicated Container for Printer Engine -->
  <div id="print-area"></div>

  <!-- Multilingual Engine & App Script -->
  <script src="assets/js/i18n.js"></script>
  <script src="assets/js/app.js"></script>
</body>
</html>
