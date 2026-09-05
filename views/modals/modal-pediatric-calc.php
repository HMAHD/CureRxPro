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
