import mysql.connector
import re

conn = mysql.connector.connect(
    host='127.0.0.1',
    user='root',
    password='',
    database='prescription_db'
)
cursor = conn.cursor()

cursor.execute("SELECT id, name FROM medicines")
rows = cursor.fetchall()

surgical_and_cosmetic_patterns = [
    r'\bcorset\b', r'\bhenna\b', r'\bpads\b', r'\bpad\b', r'\bbelt\b', r'\bcollar\b',
    r'\bbrace\b', r'\bsupport\b', r'\bknee cap\b', r'\bkneecap\b', r'\bcrepe\b',
    r'\bbattery\b', r'\bmeter\b', r'\bglucometer\b', r'\bthermometer\b', r'\bspacer\b',
    r'\bsoap\b', r'\bsoaps\b', r'\bshampoo\b', r'\bscrub\b', r'\blotion\b',
    r'\bpowder\b', r'\boil\b', r'\boils\b', r'\bcream\b', r'\bgel\b'
]

# Note: We want to KEEP medical creams & medical gels & medical lotions (e.g. Voltaren Gel, Betnovate Cream, Hydrocortisone, Clotrimazole, Calamine)
# But remove cosmetic/household creams/lotions/soaps/belts/meters

ids_to_del = []
for rid, name in rows:
    nl = name.lower()
    
    # 1. Non-pharma devices, hardware, cosmetics
    if any(re.search(p, nl) for p in [
        r'\bcorset\b', r'\bhenna\b', r'\bpads\b', r'\bbelt\b', r'\bcollar\b',
        r'\bbrace\b', r'\bknee cap\b', r'\bkneecap\b', r'\bbattery\b', r'\bmeter\b',
        r'\bglucometer\b', r'\bthermometer\b', r'\bsoap\b', r'\bsoaps\b', r'\bshampoo\b',
        r'\bconditioner\b', r'\bface wash\b', r'\bfacewash\b', r'\bbody wash\b',
        r'\bperfume\b', r'\bdeodorant\b', r'\bbody spray\b', r'\bcologne\b',
        r'\btoothpaste\b', r'\btoothbrush\b', r'\bcomb\b', r'\bnail\b', r'\bhair color\b',
        r'\bhair dye\b', r'\bhair oil\b', r'\bdiaper\b', r'\bpampers\b', r'\bsanitary\b'
    ]):
        ids_to_del.append(rid)

print(f"Total non-drug devices & cosmetics to remove: {len(ids_to_del)}")

if ids_to_del:
    format_strings = ','.join(['%s'] * len(ids_to_del))
    cursor.execute(f"DELETE FROM medicines WHERE id IN ({format_strings})", tuple(ids_to_del))
    conn.commit()

cursor.execute("SELECT COUNT(*) FROM medicines")
print(f"Total pure medicines remaining: {cursor.fetchone()[0]}")

cursor.execute("SELECT id, name, type, default_strength, unit_price FROM medicines ORDER BY name ASC LIMIT 25")
top_rows = cursor.fetchall()

print("\n--- SAMPLE TOP 25 REAL MEDICINES: ---")
for r in top_rows:
    print(f"  * [{r[0]}] {r[1]} ({r[2]} | {r[3]}) - LKR {r[4]}")

cursor.close()
conn.close()
