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
