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
