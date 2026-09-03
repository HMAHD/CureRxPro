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

deleted_ids = []
cleaned_rows = []

for rid, name in rows:
    clean_name = str(name).strip()
    
    # Check if name has at least 2 letters
    letters = re.findall(r'[a-zA-Z]', clean_name)
    if len(letters) < 2 or clean_name in ['.', '-', '--', '---', '..', '...', 'null', 'None']:
        deleted_ids.append(rid)
    else:
        # Strip leading dots/dashes if any
        stripped_name = re.sub(r'^[.\-\s_#*]+', '', clean_name)
        if stripped_name != clean_name:
            cleaned_rows.append((stripped_name, rid))

print(f"Total junk rows to delete (dots, dashes, invalid names): {len(deleted_ids)}")

if deleted_ids:
    format_strings = ','.join(['%s'] * len(deleted_ids))
    cursor.execute(f"DELETE FROM medicines WHERE id IN ({format_strings})", tuple(deleted_ids))
    conn.commit()

for new_name, rid in cleaned_rows:
    cursor.execute("UPDATE medicines SET name = %s, generic_name = %s WHERE id = %s", (new_name, new_name, rid))
conn.commit()

# Check count after cleanup
cursor.execute("SELECT COUNT(*) FROM medicines")
total_remaining = cursor.fetchone()[0]

# Check top 15 rows sorted by name
cursor.execute("SELECT id, name, type, default_strength, unit_price FROM medicines ORDER BY name ASC LIMIT 15")
sample_top = cursor.fetchall()

print(f"Cleaned database count: {total_remaining} medicines remaining.")
print("\nTop 15 medicines in database now (sorted alphabetically):")
for r in sample_top:
    print(f"  * [{r[0]}] {r[1]} ({r[2]} | {r[3]}) - LKR {r[4]}")

cursor.close()
conn.close()
