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

additional_excludes = [
    '4rever', '4 ever', 'body milk', 'pigmentation remover', '7 oil in one', 'syringer',
    'kaha 200ml', 'lavender body', 'gold body', 'hibiscus body', 'venivel body', 'sun cc',
    'aloe vera cup', 'cleansing milk', 'toning milk', 'facial cream', 'scrub', 'fairness cream',
    'body lotion', 'face wash', 'body wash', 'hair treatment', 'hair serum', 'hair shampoo'
]

ids_to_del = []
for rid, name in rows:
    nl = name.lower()
    for kw in additional_excludes:
        if kw in nl:
            ids_to_del.append(rid)
            break

print(f"Additional cosmetics / body milk found: {len(ids_to_del)}")
if ids_to_del:
    format_strings = ','.join(['%s'] * len(ids_to_del))
    cursor.execute(f"DELETE FROM medicines WHERE id IN ({format_strings})", tuple(ids_to_del))
    conn.commit()

cursor.execute("SELECT id, name, type, default_strength, unit_price FROM medicines ORDER BY name ASC LIMIT 25")
top_rows = cursor.fetchall()

print("\n--- NEW TOP 25 MEDICINES IN DATABASE: ---")
for r in top_rows:
    print(f"  * [{r[0]}] {r[1]} ({r[2]} | {r[3]}) - LKR {r[4]}")

cursor.close()
conn.close()
