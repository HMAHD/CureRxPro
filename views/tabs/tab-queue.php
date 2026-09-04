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
