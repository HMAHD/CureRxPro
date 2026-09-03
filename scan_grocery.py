import mysql.connector

conn = mysql.connector.connect(
    host='127.0.0.1',
    user='root',
    password='',
    database='prescription_db'
)
cursor = conn.cursor()

cursor.execute("SELECT id, name, unit_price FROM medicines")
rows = cursor.fetchall()

print(f"Total rows in DB: {len(rows)}")

# Let's inspect rows containing common non-medicine words
suspicious_words = [
    'cream', 'soap', 'oil', 'tea', 'milk', 'water', 'biscuit', 'bun', 'cake', 'juice',
    'drink', 'rice', 'sugar', 'salt', 'flour', 'noodle', 'sauce', 'jam', 'butter',
    'cheese', 'curd', 'yoghurt', 'ice', 'pop', 'snack', 'nut', 'seed', 'corn', 'soup',
    'paste', 'lotion', 'gel', 'spray', 'wash', 'clean', 'scrub', 'shampoo', 'conditioner',
    'powder', 'bar', 'roll', 'sheet', 'tape', 'battery', 'bulb', 'pen', 'pencil', 'box',
    'bag', 'cup', 'bottle', 'can', 'pack', 'tissue', 'cotton', 'pad', 'stick', 'glove',
    'needle', 'syringe', 'plaster', 'bandage', 'kit', 'strip', 'meter', 'mask'
]

found_suspicious = []
for r in rows:
    name_lower = r[1].lower()
    for w in suspicious_words:
        if w in name_lower:
            found_suspicious.append((w, r[1], r[2]))
            break

print(f"Found suspicious / non-pure-pharma items count: {len(found_suspicious)}")
print("\nSample 50 suspicious items in current DB:")
for item in found_suspicious[:60]:
    print(f"  [{item[0]}] {item[1]} (LKR {item[2]})")

cursor.close()
conn.close()
