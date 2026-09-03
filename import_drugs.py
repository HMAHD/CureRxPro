import openpyxl
import re
import mysql.connector

# 1. Connect to MySQL
conn = mysql.connector.connect(
    host='127.0.0.1',
    user='root',
    password='',
    database='prescription_db'
)
cursor = conn.cursor()

# Ensure medicines table is clean
cursor.execute("SET FOREIGN_KEY_CHECKS = 0;")
cursor.execute("TRUNCATE TABLE `medicines`;")
cursor.execute("SET FOREIGN_KEY_CHECKS = 1;")
conn.commit()

# 2. Non-medicine exclusion keywords (Groceries, Diapers, Cosmetics, Toiletries, FMCG)
exclude_keywords = [
    'pampers', 'diaper', 'wipes', 'nappy', 'cerelac', 'nan 1', 'nan 2', 'nan 3',
    'lactogen', 'anchor', 'raththi', 'nespray', 'sustagen', 'pediasure',
    'facewash', 'face wash', 'body wash', 'toothbrush', 'brush',
    'whitening', 'fairness', 'cleanser', 'toner', 'night cream', 'day cream',
    'sunscreen', 'sunblock', 'lipstick', 'nail polish', 'hair color', 'hair dye', 'conditioner',
    'perfume', 'deodorant', 'body spray', 'cologne', 'cotton buds', 'durex', 'condom',
    'sanitary', 'whisper', 'stayfree', 'kotex', 'serviette',
    'biscuit', 'chocolate', 'snack', 'water- 5l', 'water 5l', 'water- 1l', 'water- 500ml',
    'candy', 'toffee', 'velvet soap', 'lifebuoy', 'lux soap', 'dove soap', 'pears soap',
    'baby cheramy', 'johnson baby', 'cologne', 'baby powder', 'feeding bottle', 'teat', 'soother',
    'hair oil', 'coconut oil', 'cook oil', 'uno candy', 'wafer', 'touch cologne', 'top dog',
    'sandalwood soap', 'sakura body', 'saffron body', 'avocado body', 'straight conditioner'
]

# 3. Load Excel file
print("Loading Price lists.xlsx...")
wb = openpyxl.load_workbook('Price lists.xlsx', data_only=True)
sheet = wb['Page1']

insert_query = """
INSERT INTO `medicines` 
(`name`, `generic_name`, `type`, `default_strength`, `default_dosage`, `default_instruction`, `unit_price`, `stock_qty`) 
VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
"""

imported_count = 0
skipped_count = 0
batch_data = []

for r in range(2, sheet.max_row + 1):
    code = sheet.cell(r, 1).value
    name = sheet.cell(r, 2).value
    cost = sheet.cell(r, 3).value
    unit_price = sheet.cell(r, 5).value

    if not name:
        continue

    name_str = str(name).strip()
    lower = name_str.lower()

    # Check if non-medicine
    if any(kw in lower for kw in exclude_keywords):
        skipped_count += 1
        continue

    # Parse Unit Price
    try:
        price_val = float(unit_price) if unit_price is not None else 10.00
    except (ValueError, TypeError):
        price_val = 10.00

    # Extract strength (e.g. 500mg, 50mg, 10ml, 5%, 200iu, 625mg)
    strength_match = re.search(r'(\d+(?:\.\d+)?\s*(?:mg|g|ml|mcg|iu|%|IU|MG|ML|G|MCG))', name_str)
    strength = strength_match.group(1).strip() if strength_match else ''

    # Determine Type, Default Dosage & Instruction
    med_type = 'Tablet'
    default_dosage = '1 Tab tds'
    default_instruction = 'After Meals / කෑමට පසු'

    if any(s in lower for s in ['syrup', 'syr', 'suspension', 'susp', 'liquid', 'solution', 'elixir', 'linctus']):
        med_type = 'Syrup'
        default_dosage = '5ml tds'
        default_instruction = 'After Meals / කෑමට පසු'
    elif any(s in lower for s in ['cream', 'crm', 'ointment', 'gel', 'lotion', 'paste']):
        med_type = 'Cream/Ointment'
        default_dosage = 'Apply thin layer bd'
        default_instruction = 'External Use / බාහිර ආලේපයට'
    elif any(s in lower for s in ['drop', 'eye drop', 'ear drop', 'nasal drop', 'drops']):
        med_type = 'Drops'
        default_dosage = '1-2 Drops tds'
        default_instruction = 'Instill in affected area / යොදන්න'
    elif any(s in lower for s in ['inhaler', 'evohaler', 'rotacap', 'respule', 'spray', 'nasal spray']):
        med_type = 'Inhaler'
        default_dosage = '2 Puffs bd'
        default_instruction = 'Inhale as directed / උරන්න'
    elif any(s in lower for s in ['inj', 'injection', 'vial', 'ampoule']):
        med_type = 'Injection'
        default_dosage = 'As directed'
        default_instruction = 'Administer as directed / එන්නත් කරන්න'
    elif any(s in lower for s in ['cap', 'capsule']):
        med_type = 'Capsule'
        default_dosage = '1 Cap tds'
        default_instruction = 'After Meals / කෑමට පසු'
    else:
        med_type = 'Tablet'
        default_dosage = '1 Tab tds'
        default_instruction = 'After Meals / කෑමට පසු'

    # Generic Name (Clean extraction)
    generic_name = name_str

    batch_data.append((
        name_str,
        generic_name,
        med_type,
        strength,
        default_dosage,
        default_instruction,
        round(price_val, 2),
        500
    ))
    imported_count += 1

    # Batch insert every 500 records
    if len(batch_data) >= 500:
        cursor.executemany(insert_query, batch_data)
        conn.commit()
        batch_data = []

# Insert any remaining records
if batch_data:
    cursor.executemany(insert_query, batch_data)
    conn.commit()

cursor.close()
conn.close()

print(f"\n==========================================")
print(f"IMPORT COMPLETE!")
print(f"Total Pure Medical Drugs Imported: {imported_count}")
print(f"Total Non-Medicine / FMCG Items Skipped: {skipped_count}")
print(f"==========================================")
