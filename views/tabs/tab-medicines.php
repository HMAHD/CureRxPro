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
        <div style="margin-top:12px;">
          <button id="btn-med-prev">&#8592; Prev</button>
          <button id="btn-med-next">Next &#8594;</button>
        </div>
      </div>
    </section>
