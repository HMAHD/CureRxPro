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
