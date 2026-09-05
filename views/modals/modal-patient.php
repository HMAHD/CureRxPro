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
