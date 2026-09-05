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
