// assets/js/i18n.js - Multilingual Dictionary & Translation Engine (English, Sinhala, Tamil)

const i18nTranslations = {
  en: {
    // Navigation
    nav_new_rx: "New Prescription",
    nav_patients: "Patients Directory",
    nav_queue_monitor: "Live Queue Monitor",
    nav_channeling_billing: "Channeling Receipt",
    nav_drugs: "Drug Master DB",
    nav_history: "Prescription History",
    nav_templates: "Quick Templates",
    nav_settings: "Clinic Settings",
    nav_version: "CureRx v2.5 | Active",
    title_live_queue_dashboard: "Live Queue Monitor Dashboard",
    title_assign_queue: "Add Patient to Queue",
    title_issue_channeling_bill: "Issue Channeling Receipt",

    // Header & Stats
    stat_today_rx: "Today's Rx",
    stat_patients: "Patients",
    stat_drugs: "Drugs DB",
    btn_new_patient: "+ New Patient",
    stat_license_days: "License",
    lbl_days_remaining: "Days Remaining",
    reminder_7days_msg: "Warning: Your monthly subscription expires in {days} days! Please contact support to renew.",
    btn_renew_now: "Renew Now",
    btn_check_updates: "Check for Updates",
    update_latest_msg: "✓ You are running the latest version of CureRx Pro (v2.5.0)",

    // Rx Builder - Patient & Vitals
    title_patient_info: "Patient Information",
    ph_search_patient: "🔍 Search patient by Name / Phone...",
    title_vitals: "Patient Vitals",
    lbl_bp: "BP (mmHg)",
    lbl_pulse: "Pulse (bpm)",
    lbl_weight: "Weight (kg)",
    lbl_temp: "Temp (°F)",

    // Clinical Notes & Diseases
    title_clinical_notes: "Clinical Notes & Diagnosis",
    lbl_complaints: "Chief Complaints (Symptoms)",
    ph_complaints: "e.g. Fever x 2 days, Cough, Body aches",
    lbl_diagnosis: "Diagnosis / Disease (ලෙඩාගේ ලෙඩේ)",
    ph_diagnosis: "Select below or type disease (e.g. Gastritis, Diabetes, Fever)...",
    lbl_disease_chips: "Quick Select Illness / Disease:",

    // Medicines Table
    title_prescribed_meds: "Prescribed Medicines",
    th_med_name: "Medicine Name",
    th_strength: "Strength",
    th_dosage: "Dosage",
    th_instruction: "Instruction",
    th_duration: "Duration",
    th_qty: "Qty",
    th_source: "Source",
    opt_source_clinic: "🏥 Clinic",
    opt_source_pharmacy: "💊 Pharmacy",
    section_clinic_drugs: "℞ Medicines Issued from Clinic",
    section_pharmacy_drugs: "🏪 To Purchase from Outside Pharmacy",
    btn_add_row: "+ Add Another Medicine",
    
    // Doctor's Advice, Dispensary Billing & Save
    lbl_advice: "Doctor's Special Advice",
    ph_advice: "e.g. Drink warm water, Rest well for 3 days",
    lbl_followup: "Follow-up Date",
    title_dispensary_billing: "Dispensary & Pharmacy Billing",
    lbl_consultation_fee: "Doctor Fee (LKR)",
    lbl_hospital_fee: "Hospital / Center Fee (LKR)",
    lbl_meds_subtotal: "Medicines Subtotal (LKR)",
    lbl_discount: "Discount (LKR)",
    lbl_grand_total: "Net Grand Total (LKR)",
    th_unit_price: "Price/Unit",
    th_item_total: "Amount",
    btn_save_print: "Save & Print Prescription / Bill",
    lbl_practice_mode: "Doctor Practice Mode",
    opt_mode_dispensary: "🏥 In-House Dispensary with Billing (ඩිස්පෙන්සරි)",
    opt_mode_channeling: "🩺 Channeling / Pharmacy Rx Only (චැනලින් / ෆාමසි)",
    lbl_include_bill: "Include Dispensary Bill on Printout",
    btn_mode_rx_only: "📋 Prescription Only",
    btn_mode_rx_bill: "🧾 Rx + Bill",
    btn_repeat_last_rx: "🔁 Repeat Last Rx",
    btn_send_whatsapp: "📲 Send via WhatsApp",
    title_backup_restore: "Database Backup & Data Safety",
    desc_backup_restore: "Export and download a full snapshot of your clinic database to a secure file, or restore from a previous backup.",
    btn_download_backup: "📦 Download Full Backup (.json)",
    btn_restore_backup: "📥 Restore Database",
    msg_restore_confirm: "Restoring will replace the current database with the backup file. Do you wish to continue?",
    msg_restore_success: "Database successfully restored from backup!",

    // Patients Directory Tab
    title_patients_dir: "Registered Patients Directory",
    th_patient_code: "Code",
    th_patient_name: "Patient Name",
    th_age_gender: "Age / Gender",
    th_phone: "Phone Number",
    th_blood: "Blood Group",
    th_allergies: "Allergies",
    th_actions: "Actions",

    // Drug Master Tab
    title_drug_master: "Drug Master Database",
    btn_add_drug: "+ Add New Drug",
    th_drug_name: "Drug Name",
    th_generic_name: "Generic Name",
    th_type: "Type",
    th_default_strength: "Default Strength",
    th_default_dosage: "Default Dosage",
    th_default_instruction: "Default Instructions",

    // History Tab
    title_history: "Prescription History Archive",
    th_rx_no: "Rx Number",
    th_date: "Date & Time",
    th_items_count: "Items Count",

    // Templates Tab
    title_templates: "Prescription Quick Templates",
    desc_templates: "Predefined medicine packages for instant 1-click prescription entry.",

    // Settings Tab
    title_settings: "Doctor & Clinic Profile Settings",
    lbl_doc_name: "Doctor Name",
    lbl_qualifications: "Qualifications",
    lbl_slmc_reg: "SLMC Reg. No",
    lbl_clinic_name: "Clinic Name",
    lbl_clinic_address: "Clinic Address",
    lbl_clinic_phone: "Contact Phone Numbers",
    lbl_paper_size: "Default Print Paper Size",
    btn_save_settings: "Save Settings",

    // Print Modal & Options
    title_print_preview: "Print Preview",
    lbl_paper: "Paper:",
    btn_print_now: "Print Now",
    btn_close: "Close",

    // Medicine Instruction Options
    ins_after_meals: "After Meals",
    ins_before_meals: "Before Meals",
    ins_at_night: "At Night",
    ins_morning: "Morning",
    ins_with_meals: "With Meals",
    ins_prn: "PRN (As Needed)",
    ins_as_directed: "As Directed"
  },

  si: {
    // Navigation
    nav_new_rx: "නව බෙහෙත් වට්ටෝරුව",
    nav_patients: "ලෙඩ්ඩුන්ගේ නාමාවලිය",
    nav_queue_monitor: "සජීවී රෝගී පෝලිම",
    nav_channeling_billing: "චැනලින් බිල්පත",
    nav_drugs: "ඖෂධ තොරතුරු ගබඩාව",
    nav_history: "පරණ බෙහෙත් තුණ්ඩු",
    nav_templates: "ක්ෂණික සැකිලි",
    nav_settings: "සායනයේ සැකසුම්",
    nav_version: "CureRx v2.5 | සක්‍රීයයි",
    title_live_queue_dashboard: "සජීවී රෝගී පෝලිම් පුවරුව",
    title_assign_queue: "රෝගියාව පෝලිමට ඇතුළත් කරන්න",
    title_issue_channeling_bill: "නව චැනලින් රිසිට්පතක් නිකුත් කිරීම",

    // Header & Stats
    stat_today_rx: "අද නිකුත් කළ:",
    stat_patients: "ලෙඩ්ඩු ගණන:",
    stat_drugs: "ඖෂධ ගණන:",
    btn_new_patient: "+ නව රෝගියෙක්",
    stat_license_days: "බලපත්‍රය:",
    lbl_days_remaining: "ඉතිරි දින ගණන",
    reminder_7days_msg: "අවධානයයි: ඔබගේ මාසික ගිණුමේ කාලසීමාව තව දින {days} කින් අවසන් වේ! කරුණාකර අලුත් කරගන්න.",
    btn_renew_now: "දැන් අලුත් කරන්න",
    btn_check_updates: "අලුත්ම Updates පරීක්ෂා කරන්න",
    update_latest_msg: "✓ ඔබ දැනට පාවිච්චි කරන්නේ CureRx Pro හි අලුත්ම සංස්කරණයයි (v2.5.0)",

    // Rx Builder - Patient & Vitals
    title_patient_info: "රෝගියාගේ තොරතුරු",
    ph_search_patient: "🔍 නම හෝ දුරකථන අංකයෙන් සොයන්න...",
    title_vitals: "රෝගියාගේ සෞඛ්‍ය මිනුම් (Vitals)",
    lbl_bp: "රුධිර පීඩනය (BP)",
    lbl_pulse: "නාඩි වැටීම (Pulse)",
    lbl_weight: "බර (Weight)",
    lbl_temp: "උෂ්ණත්වය (Temp)",

    // Clinical Notes & Diseases
    title_clinical_notes: "රෝගී සටහන් සහ රෝග තත්ත්වය",
    lbl_complaints: "රෝග ලක්ෂණ (Complaints)",
    ph_complaints: "උදා: උණ දින 2ක්, කැස්ස, ඇඟපත වේදනාව",
    lbl_diagnosis: "රෝග තත්ත්වය / විනිශ්චය (ලෙඩාගේ ලෙඩේ)",
    ph_diagnosis: "පහතින් තෝරන්න හෝ ලෙඩේ සඳහන් කරන්න (උදා: උණ, ගැස්ට්‍රයිටිස්, දියවැඩියාව)...",
    lbl_disease_chips: "පොදු රෝග තත්ත්වයන් (1-Click Select):",

    // Medicines Table
    title_prescribed_meds: "නියම කරන ලද ඖෂධ",
    th_med_name: "ඖෂධයේ නම",
    th_strength: "ප්‍රමාණය (Strength)",
    th_dosage: "මාත්‍රාව (Dosage)",
    th_instruction: "ලබාගන්නා ආකාරය",
    th_duration: "කාලසීමාව",
    th_qty: "එකතු ගණන",
    th_source: "ලබාගන්නා ස්ථානය",
    opt_source_clinic: "🏥 සායනයෙන් (Clinic)",
    opt_source_pharmacy: "💊 ෆාමසියෙන් (Pharmacy)",
    section_clinic_drugs: "℞ සායනයෙන් නිකුත් කළ ඖෂධ (Issued from Clinic)",
    section_pharmacy_drugs: "🏪 බාහිර ඖෂධසැලෙන් ලබාගත යුතු ඖෂධ (To Purchase from Pharmacy)",
    btn_add_row: "+ තවත් ඖෂධයක් එකතු කරන්න",
    
    // Doctor's Advice, Dispensary Billing & Save
    lbl_advice: "වෛද්‍යවරයාගේ විශේෂ උපදෙස්",
    ph_advice: "උදා: උණු වතුර පානය කරන්න, දින 3ක් විවේක ගන්න",
    lbl_followup: "නැවත පැමිණිය යුතු දිනය",
    title_dispensary_billing: "ඩිස්පෙන්සරියේ බෙහෙත් බිල් පත",
    lbl_consultation_fee: "වෛද්‍යවරයාගේ ගාස්තුව (රු.)",
    lbl_hospital_fee: "චැනලින් සෙන්ටර් ගාස්තුව (රු.)",
    lbl_meds_subtotal: "බෙහෙත් එකතු ගාස්තුව (රු.)",
    lbl_discount: "වට්ටම් (රු.)",
    lbl_grand_total: "ගෙවිය යුතු මුළු මුදල (රු.)",
    th_unit_price: "ඒකක මිල",
    th_item_total: "වටිනාකම",
    btn_save_print: "තබාගෙන Print කරන්න",
    lbl_practice_mode: "වෛද්‍ය සේවා ක්‍රමය",
    opt_mode_dispensary: "🏥 ඩිස්පෙන්සරිය සහ බෙහෙත් බිල්පත් ක්‍රමය",
    opt_mode_channeling: "🩺 චැනලින් / ඖෂධසල් සඳහා තුණ්ඩුව පමණි (No Prices)",
    lbl_include_bill: "බෙහෙත් බිල්පත Print එකට ඇතුළත් කරන්න",
    btn_mode_rx_only: "📋 බෙහෙත් තුණ්ඩුව පමණි",
    btn_mode_rx_bill: "🧾 තුණ්ඩුව + බිල්පත",
    btn_repeat_last_rx: "🔁 පෙර බෙහෙත් ලබාදෙන්න",
    btn_send_whatsapp: "📲 WhatsApp මගින් යවන්න",
    title_backup_restore: "දත්ත ආරක්ෂිතව Backup තබාගැනීම",
    desc_backup_restore: "පරිගණකය අක්‍රිය වුවහොත් හෝ වෙනත් පරිගණකයකට මාරු වුවහොත් ඔබගේ සියලුම දත්ත ආරක්ෂිතව ගබඩා කරගන්න.",
    btn_download_backup: "📦 සම්පූර්ණ Backup එක බාගත කරන්න (.json)",
    btn_restore_backup: "📥 Backup එකෙන් Restore කරන්න",
    msg_restore_confirm: "මෙම Backup File එකෙන් දත්ත නැවත ලබාගැනීමට (Restore) ඔබට සහතිකද?",
    msg_restore_success: "Database එක සාර්ථකව Backup එකෙන් Restore කරන ලදී!",

    // Patients Directory Tab
    title_patients_dir: "ලියාපදිංචි රෝගීන්ගේ ලැයිස්තුව",
    th_patient_code: "අංකය",
    th_patient_name: "රෝගියාගේ නම",
    th_age_gender: "වයස / ස්ත්‍රී-පුරුෂ",
    th_phone: "දුරකථන අංකය",
    th_blood: "ලේ වර්ගය",
    th_allergies: "ආසාත්මිකතා",
    th_actions: "ක්‍රියාමාර්ග",

    // Drug Master Tab
    title_drug_master: "ඖෂධ ප්‍රධාන තොරතුරු ගබඩාව",
    btn_add_drug: "+ අලුත් ඖෂධයක්",
    th_drug_name: "ඖෂධයේ නම",
    th_generic_name: "රසායනික නම",
    th_type: "වර්ගය",
    th_default_strength: "සාමාන්‍ය ප්‍රමාණය",
    th_default_dosage: "සාමාන්‍ය මාත්‍රාව",
    th_default_instruction: "සාමාන්‍ය උපදෙස්",

    // History Tab
    title_history: "නිකුත් කළ බෙහෙත් තුණ්ඩු එකතුව",
    th_rx_no: "තුණ්ඩුවේ අංකය",
    th_date: "දිනය සහ වේලාව",
    th_items_count: "ඖෂධ ගණන",

    // Templates Tab
    title_templates: "පූර්ව නිශ්චිත බෙහෙත් සැකිලි",
    desc_templates: "එක් ක්ලික් එකකින් බෙහෙත් වට්ටෝරු යෙදීම සඳහා.",

    // Settings Tab
    title_settings: "වෛද්‍යවරයා සහ සායන සැකසුම්",
    lbl_doc_name: "වෛද්‍යවරයාගේ නම",
    lbl_qualifications: "සුදුසුකම්",
    lbl_slmc_reg: "SLMC ලියාපදිංචි අංකය",
    lbl_clinic_name: "සායනයේ නම",
    lbl_clinic_address: "ලිපිනය",
    lbl_clinic_phone: "දුරකථන අංක",
    lbl_paper_size: "මුද්‍රණ කඩදාසි ප්‍රමාණය",
    btn_save_settings: "සැකසුම් සුරකින්න",

    // Print Modal & Options
    title_print_preview: "මුද්‍රණ පූර්ව දර්ශනය",
    lbl_paper: "කඩදාසිය:",
    btn_print_now: "දැන් මුද්‍රණය කරන්න",
    btn_close: "වසා දමන්න",

    // Medicine Instruction Options
    ins_after_meals: "කෑමට පසු (After Meals)",
    ins_before_meals: "කෑමට පෙර (Before Meals)",
    ins_at_night: "රාත්‍රියට (At Night)",
    ins_morning: "උදෑසන (Morning)",
    ins_with_meals: "කෑම සමඟ (With Meals)",
    ins_prn: "අවශ්‍ය විටදී (PRN)",
    ins_as_directed: "උපදෙස් පරිදි (As Directed)"
  },

  ta: {
    // Navigation
    nav_new_rx: "புதிய மருந்துச் சீட்டு",
    nav_patients: "நோயாளிகள் விபரம்",
    nav_queue_monitor: "நேரடி வரிசை கண்காணிப்பு",
    nav_channeling_billing: "சேனலிங் ரசீது",
    nav_drugs: "மருந்துகள் தரவுத்தளம்",
    nav_history: "மருந்துச் சீட்டு வரலாறு",
    nav_templates: "விரைவு வார்ப்புருக்கள்",
    nav_settings: "கிளினிக் அமைப்புகள்",
    nav_version: "CureRx v2.5 | செயலில் உள்ளது",
    title_live_queue_dashboard: "நேரடி வரிசை கண்காணிப்பு கட்டுப்பாட்டுப் பலகை",
    title_assign_queue: "நோயாளியை வரிசையில் சேர்க்கவும்",
    title_issue_channeling_bill: "சேனலிங் ரசீது வழங்கல்",

    // Header & Stats
    stat_today_rx: "இன்றைய சீட்டுகள்:",
    stat_patients: "நோயாளிகள்:",
    stat_drugs: "மருந்துகள்:",
    btn_new_patient: "+ புதிய நோயாளி",
    stat_license_days: "உரிமம்:",
    lbl_days_remaining: "மீதமுள்ள நாட்கள்",
    reminder_7days_msg: "எச்சரிக்கை: உங்கள் கணக்கு {days} நாட்களில் காலாவதியாகிறது! புதுப்பிக்க தொடர்பு கொள்ளவும்.",
    btn_renew_now: "இப்போதே புதுப்பிக்கவும்",
    btn_check_updates: "புதிய புதுப்பிப்புகளைச் சரிபார்க்கவும்",
    update_latest_msg: "✓ நீங்கள் CureRx Pro இன் சமீபத்திய பதிப்பைப் பயன்படுத்துகிறீர்கள் (v2.5.0)",

    // Rx Builder - Patient & Vitals
    title_patient_info: "நோயாளி விபரம்",
    ph_search_patient: "🔍 பெயர் அல்லது தொலைபேசி மூலம் தேடுக...",
    title_vitals: "உடல்நல அளவீடுகள் (Vitals)",
    lbl_bp: "இரத்த அழுத்தம் (BP)",
    lbl_pulse: "நாடித் துடிப்பு (Pulse)",
    lbl_weight: "எடை (Weight)",
    lbl_temp: "வெப்பநிலை (Temp)",

    // Clinical Notes & Diseases
    title_clinical_notes: "மருத்துவக் குறிப்புகள் & நோய் விபரம்",
    lbl_complaints: "அறிகுறிகள் (Chief Complaints)",
    ph_complaints: "எ-டு: காய்ச்சல் 2 நாட்கள், இருமல்",
    lbl_diagnosis: "நோய் கண்டறிதல் (Diagnosis / Disease)",
    ph_diagnosis: "நோயைத் தேர்ந்தெடுக்கவும் அல்லது உள்ளிடவும்...",
    lbl_disease_chips: "பொதுவான நோய்கள் (1-Click Select):",

    // Medicines Table
    title_prescribed_meds: "பரிந்துரைக்கப்பட்ட மருந்துகள்",
    th_med_name: "மருந்தின் பெயர்",
    th_strength: "அளவு (Strength)",
    th_dosage: "மாத்திரை அளவு (Dosage)",
    th_instruction: "அறிவுறுத்தல்",
    th_duration: "கால அளவு",
    th_qty: "மொத்த எண்ணிக்கை",
    th_source: "பெறும் இடம்",
    opt_source_clinic: "🏥 மருத்துவமனையில்",
    opt_source_pharmacy: "💊 மருந்தகத்தில்",
    section_clinic_drugs: "℞ மருத்துவமனையில் வழங்கப்பட்ட மருந்துகள் (Issued from Clinic)",
    section_pharmacy_drugs: "🏪 வெளி மருந்தகத்தில் வாங்க வேண்டியவை (To Purchase from Pharmacy)",
    btn_add_row: "+ மற்றொரு மருந்தைச் சேர்க்கவும்",
    
    // Doctor's Advice, Dispensary Billing & Save
    lbl_advice: "மருத்துவரின் சிறப்பு அறிவுரை",
    ph_advice: "எ-டு: வெதுவெதுப்பான நீர் அருந்தவும், 3 நாட்கள் ஓய்வு எடுக்கவும்",
    lbl_followup: "அடுத்த வருகை தேதி",
    title_dispensary_billing: "மருந்தகக் கட்டணப்பட்டியல் (Billing)",
    lbl_consultation_fee: "மருத்துவர் கட்டணம் (ரூ.)",
    lbl_hospital_fee: "மருத்துவமனை கட்டணம் (ரூ.)",
    lbl_meds_subtotal: "மருந்துகள் மொத்தம் (ரூ.)",
    lbl_discount: "தள்ளுபடி (ரூ.)",
    lbl_grand_total: "மொத்தக் கட்டணம் (ரூ.)",
    th_unit_price: "அலகு விலை",
    th_item_total: "தொகை",
    btn_save_print: "சேமித்து அச்சிடுக",
    lbl_practice_mode: "மருத்துவ சேவை முறை",
    opt_mode_dispensary: "🏥 மருந்தகம் மற்றும் கட்டண முறை (Dispensary)",
    opt_mode_channeling: "🩺 மருந்துச் சீட்டு மட்டும் (Channeling / Pharmacy Only)",
    lbl_include_bill: "அச்சுப்பிரதியில் கட்டணத்தைச் சேர்க்கவும்",
    btn_mode_rx_only: "📋 மருந்துச் சீட்டு மட்டும்",
    btn_mode_rx_bill: "🧾 சீட்டு + கட்டணம்",
    btn_repeat_last_rx: "🔁 முந்தைய மருந்தை மீண்டும் வழங்குக",
    btn_send_whatsapp: "📲 WhatsApp மூலம் அனுப்புக",
    title_backup_restore: "தரவுத்தள காப்புப்பிரதி (Backup & Restore)",
    desc_backup_restore: "உங்கள் கிளினிக் தரவுகளைப் பாதுகாப்பாக கோப்பாகப் பதிவிறக்கலாம் அல்லது மீட்டெடுக்கலாம்.",
    btn_download_backup: "📦 முழு காப்புப்பிரதியை பதிவிறக்குக (.json)",
    btn_restore_backup: "📥 கோப்பிலிருந்து மீட்டமைக்குக",
    msg_restore_confirm: "காப்புப்பிரதியிலிருந்து தரவை மீட்டமைக்க விரும்புகிறீர்களா?",
    msg_restore_success: "தரவுத்தளம் வெற்றிகரமாக மீட்டமைக்கப்பட்டது!",

    // Patients Directory Tab
    title_patients_dir: "பதிவுசெய்யப்பட்ட நோயாளிகள் பட்டியல்",
    th_patient_code: "குறியீடு",
    th_patient_name: "நோயாளி பெயர்",
    th_age_gender: "வயது / பாலினம்",
    th_phone: "தொலைபேசி எண்",
    th_blood: "இரத்த வகை",
    th_allergies: "ஒவ்வாமைகள்",
    th_actions: "செயல்கள்",

    // Drug Master Tab
    title_drug_master: "மருந்துகள் முதன்மை தரவுத்தளம்",
    btn_add_drug: "+ புதிய மருந்து",
    th_drug_name: "மருந்தின் பெயர்",
    th_generic_name: "வேதியியல் பெயர்",
    th_type: "வகை",
    th_default_strength: "இயல்பு அளவு",
    th_default_dosage: "இயல்பு மாத்திரை அளவு",
    th_default_instruction: "இயல்பு அறிவுறுத்தல்",

    // History Tab
    title_history: "மருந்துச் சீட்டு வரலாற்று காப்பகம்",
    th_rx_no: "சீட்டு எண்",
    th_date: "தேதி & நேரம்",
    th_items_count: "மருந்துகள் எண்ணிக்கை",

    // Templates Tab
    title_templates: "விரைவு மருந்து வார்ப்புருக்கள்",
    desc_templates: "ஒரே கிளிக்கில் மருந்துகளைச் சேர்க்கும் வசதி.",

    // Settings Tab
    title_settings: "மருத்துவர் & கிளினிக் அமைப்புகள்",
    lbl_doc_name: "மருத்துவர் பெயர்",
    lbl_qualifications: "தகுதிகள்",
    lbl_slmc_reg: "SLMC பதிவு எண்",
    lbl_clinic_name: "கிளினிக் பெயர்",
    lbl_clinic_address: "முகவரி",
    lbl_clinic_phone: "தொடர்பு எண்கள்",
    lbl_paper_size: "அச்சு தாள் அளவு",
    btn_save_settings: "அமைப்புகளைச் சேமிக்கவும்",

    // Print Modal & Options
    title_print_preview: "அச்சு முன்னோட்டம்",
    lbl_paper: "தாள்:",
    btn_print_now: "இப்போதே அச்சிடுக",
    btn_close: "மூடுக",

    // Medicine Instruction Options
    ins_after_meals: "உணவுக்கு பின் (After Meals)",
    ins_before_meals: "உணவுக்கு முன் (Before Meals)",
    ins_at_night: "இரவில் (At Night)",
    ins_morning: "காலையில் (Morning)",
    ins_with_meals: "உணவோடு (With Meals)",
    ins_prn: "தேவைப்படும்போது (PRN)",
    ins_as_directed: "கூறியபடி (As Directed)"
  }
};

class I18nEngine {
  constructor() {
    this.currentLang = localStorage.getItem('curerx_lang') || 'en';
  }

  setLanguage(lang) {
    if (!i18nTranslations[lang]) return;
    this.currentLang = lang;
    localStorage.setItem('curerx_lang', lang);
    this.applyLanguage();
  }

  t(key) {
    const dict = i18nTranslations[this.currentLang] || i18nTranslations.en;
    return dict[key] || i18nTranslations.en[key] || key;
  }

  applyLanguage() {
    // Translate all elements with data-i18n
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      const text = this.t(key);
      if (text) {
        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
          el.value = text;
        } else {
          el.textContent = text;
        }
      }
    });

    // Translate all placeholders with data-i18n-placeholder
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
      const key = el.getAttribute('data-i18n-placeholder');
      const text = this.t(key);
      if (text) el.placeholder = text;
    });

    // Update active state in language selector
    const langSelect = document.getElementById('app-language-select');
    if (langSelect) langSelect.value = this.currentLang;

    // Trigger custom event for dynamic components
    document.dispatchEvent(new CustomEvent('languageChanged', { detail: { lang: this.currentLang } }));
  }
}

const i18n = new I18nEngine();
document.addEventListener('DOMContentLoaded', () => i18n.applyLanguage());
