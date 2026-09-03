<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Database Configuration - CureRx Pro</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --primary: #0d9488;
      --primary-dark: #0f766e;
      --bg: #0f172a;
      --card-bg: #1e293b;
      --text: #f8fafc;
      --text-muted: #94a3b8;
      --border: #334155;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: var(--bg);
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }

    .setup-container {
      width: 100%;
      max-width: 540px;
      background-color: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 40px 30px;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    }

    .brand {
      text-align: center;
      margin-bottom: 25px;
    }

    .brand-icon {
      font-size: 44px;
      color: var(--primary);
      margin-bottom: 10px;
    }

    .brand h2 {
      font-size: 24px;
      font-weight: 800;
    }

    .brand p {
      font-size: 13.5px;
      color: var(--text-muted);
      margin-top: 5px;
    }

    .error-banner {
      background: #450a0a;
      border: 1px solid #991b1b;
      color: #fca5a5;
      padding: 12px 15px;
      border-radius: 8px;
      font-size: 12.5px;
      margin-bottom: 25px;
      display: flex;
      align-items: flex-start;
      gap: 10px;
      line-height: 1.5;
    }

    .tab-container {
      display: flex;
      border-bottom: 1px solid var(--border);
      margin-bottom: 25px;
    }

    .tab-btn {
      flex: 1;
      padding: 12px;
      background: transparent;
      border: none;
      color: var(--text-muted);
      font-weight: 700;
      font-size: 13.5px;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .tab-btn.active {
      color: var(--primary);
      border-bottom-color: var(--primary);
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 6px;
      color: var(--text);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 120px;
      gap: 15px;
    }

    .form-control {
      width: 100%;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid var(--border);
      background-color: #0f172a;
      color: var(--text);
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s;
      font-family: inherit;
    }

    .form-control:focus {
      border-color: var(--primary);
    }

    .input-group {
      display: flex;
      gap: 10px;
    }

    .btn-action-small {
      padding: 10px 15px;
      background: #334155;
      color: white;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-weight: 700;
      cursor: pointer;
      font-size: 13px;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s;
    }

    .btn-action-small:hover {
      background: #475569;
    }

    .btn-row {
      display: flex;
      gap: 12px;
      margin-top: 30px;
    }

    .btn {
      flex: 1;
      padding: 13px;
      border-radius: 8px;
      border: none;
      font-weight: 700;
      font-size: 13.5px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.2s;
    }

    .btn-outline {
      background: transparent;
      border: 2px solid var(--border);
      color: var(--text);
    }

    .btn-outline:hover {
      background: rgba(255,255,255,0.05);
      border-color: var(--text-muted);
    }

    .btn-primary {
      background: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background: var(--primary-dark);
    }

    .status-msg {
      margin-top: 15px;
      padding: 10px;
      border-radius: 6px;
      font-size: 13px;
      display: none;
      text-align: center;
    }

    .status-success {
      background: #064e3b;
      color: #6ee7b7;
      border: 1px solid #047857;
    }

    .status-error {
      background: #7f1d1d;
      color: #fca5a5;
      border: 1px solid #b91c1c;
    }
  </style>
</head>
<body>

  <div class="setup-container">
    <div class="brand">
      <div class="brand-icon"><i class="fa-solid fa-network-wired"></i></div>
      <h2>CureRx Pro</h2>
      <p>Database & Role Setup Wizard</p>
    </div>

    <!-- DB Error details from bootstrap failure -->
    <div class="error-banner">
      <i class="fa-solid fa-triangle-exclamation" style="font-size: 16px; margin-top: 2px;"></i>
      <div>
        <strong>Connection Offline:</strong><br>
        <span style="font-family: monospace; font-size: 11px;"><?php echo htmlspecialchars($db_error_message); ?></span>
      </div>
    </div>

    <!-- Mode Selector Tabs -->
    <div class="tab-container">
      <button type="button" class="tab-btn active" id="tab-local-btn"><i class="fa-solid fa-desktop"></i> Standalone Server</button>
      <button type="button" class="tab-btn" id="tab-network-btn"><i class="fa-solid fa-wifi"></i> Network Client</button>
    </div>

    <form id="db-setup-form">
      <!-- IP Address / Host & Port -->
      <div class="form-group">
        <div class="form-row">
          <div>
            <label>Server IP Address (Host) *</label>
            <input type="text" class="form-control" id="db-host" value="127.0.0.1" required>
          </div>
          <div>
            <label>Port *</label>
            <input type="text" class="form-control" id="db-port" value="3307" required>
          </div>
        </div>
      </div>

      <!-- Username & Password -->
      <div class="form-group">
        <div class="form-row">
          <div>
            <label>Database Username *</label>
            <input type="text" class="form-control" id="db-user" value="root" required>
          </div>
          <div>
            <label>Database Password</label>
            <input type="password" class="form-control" id="db-pass" placeholder="Empty">
          </div>
        </div>
      </div>

      <!-- DB Name with Discovery Option -->
      <div class="form-group">
        <label>Database Name *</label>
        <div class="input-group">
          <!-- Wrapper container for dynamic input swapping -->
          <div id="dbname-input-wrapper" style="flex-grow:1;">
            <input type="text" class="form-control" id="db-name" value="prescription_db" required>
          </div>
          <button type="button" class="btn-action-small" id="btn-discover-db" title="Refresh available databases from server">
            <i class="fa-solid fa-rotate"></i> Discover
          </button>
        </div>
      </div>

      <!-- App Role Selection -->
      <div class="form-group">
        <label>Workstation Access Role *</label>
        <select class="form-control" id="app-role" style="background:#0f172a; color:#ffffff;">
          <option value="both">Complete Suite (Single Doctor Standalone)</option>
          <option value="doctor">Doctor Room (Prescription Builder Only)</option>
          <option value="receptionist">Receptionist Counter (Bills & Queue Only)</option>
        </select>
        <p style="font-size: 11.5px; color: var(--text-muted); margin-top: 5px;">Hides irrelevant tabs based on who is using this computer.</p>
      </div>

      <!-- Connection Status Message Alert -->
      <div class="status-msg" id="status-alert"></div>

      <!-- Form Buttons -->
      <div class="btn-row">
        <button type="button" class="btn btn-outline" id="btn-test-conn"><i class="fa-solid fa-circle-question"></i> Test Connection</button>
        <button type="submit" class="btn btn-primary" id="btn-save-conn"><i class="fa-solid fa-check"></i> Save & Connect</button>
      </div>
    </form>
  </div>

  <script>
    const hostInput = document.getElementById('db-host');
    const portInput = document.getElementById('db-port');
    const tabLocal = document.getElementById('tab-local-btn');
    const tabNetwork = document.getElementById('tab-network-btn');
    const form = document.getElementById('db-setup-form');
    const btnTest = document.getElementById('btn-test-conn');
    const btnDiscover = document.getElementById('btn-discover-db');
    const alertBox = document.getElementById('status-alert');
    const dbnameWrapper = document.getElementById('dbname-input-wrapper');

    // 1. Switch between local and network setup modes
    tabLocal.addEventListener('click', () => {
      tabLocal.classList.add('active');
      tabNetwork.classList.remove('active');
      hostInput.value = '127.0.0.1';
      hostInput.readOnly = true;
      hostInput.style.opacity = '0.7';
      portInput.value = '3307'; // Portable database default port
    });

    tabNetwork.addEventListener('click', () => {
      tabNetwork.classList.add('active');
      tabLocal.classList.remove('active');
      hostInput.value = '';
      hostInput.placeholder = 'e.g. 192.168.8.100';
      hostInput.readOnly = false;
      hostInput.style.opacity = '1';
      hostInput.focus();
      portInput.value = '3306'; // Standard network default port
    });

    // Make host read-only initially for Standalone Server mode
    hostInput.readOnly = true;
    hostInput.style.opacity = '0.7';

    // Helper to display alert banner
    function showAlert(msg, isSuccess) {
      alertBox.textContent = msg;
      alertBox.className = 'status-msg ' + (isSuccess ? 'status-success' : 'status-error');
      alertBox.style.display = 'block';
    }

    // 2. Discover Databases from Server
    btnDiscover.addEventListener('click', async () => {
      alertBox.style.display = 'none';
      btnDiscover.disabled = true;
      btnDiscover.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

      const payload = {
        action: 'discover',
        host: hostInput.value.trim(),
        port: portInput.value.trim(),
        user: document.getElementById('db-user').value.trim(),
        pass: document.getElementById('db-pass').value
      };

      try {
        const res = await fetch('api/save_db_config.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.status === 'success' && data.databases && data.databases.length > 0) {
          // Replace text input with dropdown select list
          let selectHtml = `<select id="db-name" class="form-control" style="background:#0f172a; color:#ffffff;">`;
          data.databases.forEach(db => {
            const selected = (db === 'prescription_db') ? 'selected' : '';
            selectHtml += `<option value="${db}" ${selected}>${db}</option>`;
          });
          selectHtml += `</select>`;
          dbnameWrapper.innerHTML = selectHtml;
          showAlert('✅ Databases discovered! Choose yours from the list.', true);
        } else {
          // Revert to text input if failed or empty
          dbnameWrapper.innerHTML = `<input type="text" class="form-control" id="db-name" value="prescription_db" required>`;
          showAlert('⚠️ No custom databases discovered. Please create/enter manually.', false);
        }
      } catch (err) {
        dbnameWrapper.innerHTML = `<input type="text" class="form-control" id="db-name" value="prescription_db" required>`;
        showAlert('❌ Discover failed. Please verify server IP and login credentials.', false);
      } finally {
        btnDiscover.disabled = false;
        btnDiscover.innerHTML = '<i class="fa-solid fa-rotate"></i> Discover';
      }
    });

    // 3. Test Connection Action
    btnTest.addEventListener('click', async () => {
      alertBox.style.display = 'none';
      btnTest.disabled = true;
      btnTest.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testing...';

      const dbNameInput = document.getElementById('db-name');
      const payload = {
        action: 'test',
        host: hostInput.value.trim(),
        port: portInput.value.trim(),
        user: document.getElementById('db-user').value.trim(),
        pass: document.getElementById('db-pass').value,
        dbname: dbNameInput ? dbNameInput.value.trim() : 'prescription_db'
      };

      try {
        const res = await fetch('api/save_db_config.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.status === 'success') {
          showAlert('✅ Connection Successful! The database server is reachable.', true);
        } else {
          showAlert('❌ ' + data.message, false);
        }
      } catch (err) {
        showAlert('❌ Failed to reach the setup server endpoint.', false);
      } finally {
        btnTest.disabled = false;
        btnTest.innerHTML = '<i class="fa-solid fa-circle-question"></i> Test Connection';
      }
    });

    // 4. Save Connection Form Submit
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      alertBox.style.display = 'none';
      const btnSave = document.getElementById('btn-save-conn');
      btnSave.disabled = true;
      btnSave.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';

      const dbNameInput = document.getElementById('db-name');
      const payload = {
        action: 'save',
        host: hostInput.value.trim(),
        port: portInput.value.trim(),
        user: document.getElementById('db-user').value.trim(),
        pass: document.getElementById('db-pass').value,
        dbname: dbNameInput ? dbNameInput.value.trim() : 'prescription_db',
        app_role: document.getElementById('app-role').value
      };

      try {
        const res = await fetch('api/save_db_config.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.status === 'success') {
          showAlert('🎉 Configuration saved successfully! Relaunching suite...', true);
          setTimeout(() => {
            window.location.reload();
          }, 1500);
        } else {
          showAlert('❌ ' + data.message, false);
          btnSave.disabled = false;
          btnSave.innerHTML = '<i class="fa-solid fa-check"></i> Save & Connect';
        }
      } catch (err) {
        showAlert('❌ Failed to save configuration details.', false);
        btnSave.disabled = false;
        btnSave.innerHTML = '<i class="fa-solid fa-check"></i> Save & Connect';
      }
    });
  </script>
</body>
</html>
