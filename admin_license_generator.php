<?php
// admin_license_generator.php - Admin Tool for Generating Monthly & Annual License Keys
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CureRx Admin - License Key Generator</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: #0f172a;
      color: #f8fafc;
    }
    .admin-card {
      background: #1e293b;
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 16px;
      padding: 32px;
      width: 100%;
      max-width: 520px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }
    .key-box {
      background: #0f172a;
      border: 2px dashed #0d9488;
      border-radius: 10px;
      padding: 16px;
      text-align: center;
      margin-top: 20px;
    }
    .key-display {
      font-size: 22px;
      font-weight: 800;
      letter-spacing: 2px;
      color: #10b981;
      font-family: monospace;
    }
  </style>
</head>
<body>

  <div class="admin-card">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
      <div class="brand-icon" style="width:48px;height:48px;font-size:24px;">
        <i class="fa-solid fa-key"></i>
      </div>
      <div>
        <h2 style="font-size:20px;font-weight:800;">License Key Generator</h2>
        <p style="font-size:12px;color:#94a3b8;">CureRx Pro Admin Billing Portal</p>
      </div>
    </div>

    <form id="gen-form">
      <div class="form-group">
        <label style="color:#cbd5e1;">Doctor / Clinic Name (Optional Note)</label>
        <input type="text" class="form-control" id="doc-note" placeholder="e.g. Dr. Sampath Perera - Maharagama">
      </div>

      <div class="form-group">
        <label style="color:#cbd5e1;">Subscription Period</label>
        <select class="form-control" id="plan-duration">
          <option value="MONTHLY">1 Month Subscription (+30 Days)</option>
          <option value="6MONTH">6 Months Subscription (+180 Days)</option>
          <option value="YEAR">1 Year Subscription (+365 Days)</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary" style="width:100%;padding:12px;font-size:15px;margin-top:10px;">
        <i class="fa-solid fa-wand-magic-sparkles"></i> Generate License Key
      </button>
    </form>

    <div class="key-box" id="key-result-box" style="display:none;">
      <span style="font-size:11px;color:#94a3b8;text-transform:uppercase;font-weight:700;">Generated License Key</span>
      <div class="key-display" id="key-text">CRX-MONTHLY-8821</div>
      <p style="font-size:12px;color:#cbd5e1;margin-top:6px;" id="validity-text">Valid for 30 Days</p>
      <button type="button" class="btn btn-success btn-sm" id="btn-copy-key" style="margin-top:12px;">
        <i class="fa-solid fa-copy"></i> Copy Key to Clipboard
      </button>
    </div>
  </div>

  <script>
    document.getElementById('gen-form').addEventListener('submit', (e) => {
      e.preventDefault();
      const dur = document.getElementById('plan-duration').value;
      const randHex = Math.random().toString(36).substring(2, 7).toUpperCase();
      
      const key = `CRX-${dur}-${randHex}`;
      document.getElementById('key-text').textContent = key;
      document.getElementById('validity-text').textContent = dur === 'YEAR' ? 'Valid for 1 Year (+365 Days)' : (dur === '6MONTH' ? 'Valid for 6 Months (+180 Days)' : 'Valid for 1 Month (+30 Days)');
      document.getElementById('key-result-box').style.display = 'block';
    });

    document.getElementById('btn-copy-key').addEventListener('click', () => {
      const keyText = document.getElementById('key-text').textContent;
      navigator.clipboard.writeText(keyText).then(() => {
        alert('License Key copied to clipboard! You can now send this to the doctor via WhatsApp.');
      });
    });
  </script>
</body>
</html>
