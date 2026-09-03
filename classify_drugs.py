import openpyxl
import re

wb = openpyxl.load_workbook('Price lists.xlsx', data_only=True)
sheet = wb['Page1']

# Comprehensive exclusion keyword list
hard_excludes = [
    # Bakery / Food / Grocery / Beverages
    'cake', 'swiss roll', 'layer cake', 'sponge', 'biscuit', 'cookie', 'cracker', 'wafer',
    'chocolate', 'candy', 'toffee', 'sweet', 'bubble gum', 'chewing gum', 'marshmallow',
    'snack', 'popcorn', 'crisps', 'chips', 'nuts', 'cashew', 'peanut', 'almond',
    'tea', 'bopf', 'dilmah', 'watawala', 'lipton', 'coffee', 'nescafe', 'bru',
    'sugar', 'salt', 'rice', 'dhal', 'flour', 'noodle', 'pasta', 'maggi', 'kothmale',
    'sauce', 'jam', 'butter', 'cheese', 'curd', 'yoghurt', 'ice cream', 'ice pop',
    'juice', 'drink', 'water-', 'water 5l', 'water 1l', 'water 500ml', 'water 1.5l',
    'cordial', 'soda', 'coke', 'pepsi', 'sprite', 'fanta', 'elephant house', 'smak',
    'soup', 'cube', 'knorr', 'marmite', 'vegemite', 'nutella', 'peanut butter',
    'cooking oil', 'coconut oil', 'gingelly oil', 'sesame oil', 'sunflower oil', 'palm oil',
    'anchor', 'raththi', 'maliban', 'highland', 'pelwatte', 'nespray', 'lakspray',
    'sustagen', 'pediasure', 'ensure', 'glucerna', 'milo', 'horlicks', 'ovaltine', 'nestomalt',
    
    # Baby Care & Diapers & Foods
    'pampers', 'diaper', 'nappy', 'wetex', 'cuddles', 'velona', 'huggies', 'drypers', 'mamypoko',
    'wipes', 'baby wipes', 'cerelac', 'nan 1', 'nan 2', 'nan 3', 'nan 4', 'nan optipro', 'nan pro',
    'lactogen', 'cow & gate', 'sma ', 'similac', 'pediasure', 'isomil',
    'baby cheramy', 'pears baby', 'panda baby', 'khomba baby', 'johnson baby', 'johnson\'s baby',
    'tiki baby', 'baby lotion', 'baby cream', 'baby oil', 'baby cologne', 'baby powder', 'baby soap',
    'baby shampoo', 'baby wash', 'feeding bottle', 'feeding cup', 'teat', 'soother', 'pacifier',
    'breast pad', 'breast pump', 'cotton buds', 'cotton balls', 'baby safety pin',

    # Beauty, Cosmetics & Hair
    'facewash', 'face wash', 'body wash', 'body scrub', 'face scrub', 'face pack', 'face mask',
    'whitening', 'fairness', 'cleanser', 'toner', 'night cream', 'day cream', 'blemish',
    'fair & lovely', 'glow & lovely', 'garnier', 'l\'oreal', '4ever', 'janet', 'nature\'s secrets',
    'dreamron', 'bellose', 'kumarika', 'keerthi', 'chandanalepa', 'vivya', 'jovees',
    'sunscreen', 'sunblock', 'sun protect', 'spf ', 'bb cream', 'cc cream', 'foundation',
    'lipstick', 'lip balm', 'lip gloss', 'mascara', 'eyeliner', 'eye shadow', 'nail polish',
    'nail remover', 'hair color', 'hair dye', 'hair colour', 'hair straight', 'conditioner',
    'shampoo', 'hair gel', 'hair spray', 'hair mousse', 'hair wax', 'hair oil', 'waralasha',
    'perfume', 'deodorant', 'body spray', 'cologne', 'eau de', 'attar', 'bodymist', 'body mist',
    'velvet', 'lux soap', 'lifebuoy', 'dove soap', 'pears soap', 'rexona', 'dettol soap', 'khomba soap',
    'cinthol', 'sandalwood soap', 'kohomba soap', 'rani soap', 'mysore sandal', 'medimix soap',
    
    # Toiletries & Household & Sanitary
    'sanitary', 'whisper', 'stayfree', 'kotex', 'fems', 'eva ', 'lady ', 'tampax', 'pantyliner',
    'serviette', 'tissue', 'toilet paper', 'kitchen towel', 'air freshener', 'ambipur',
    'mosquito coil', 'mosquito mat', 'mosquito vaporizer', 'mortein', 'baygon', 'ninja',
    'durex', 'condom', 'moods', 'skore', 'stallion', 'sensual', 'playboy',
    'toothbrush', 'toothpaste', 'signal', 'colgate', 'clogard', 'sensodyne', 'darlie', 'mouthwash',
    'harpic', 'lysol', 'vim', 'sunlight', 'rinso', 'surf excel', 'comfort', 'fabric',
    'battery', 'bulb', 'pen', 'pencil', 'notebook', 'match box', 'lighter', 'air pump',
    
    # Pet Care
    'top dog', 'dog shampoo', 'cat food', 'dog food', 'pedigree', 'whiskas', 'tick-out'
]

pure_drugs = []
filtered_out = []

for r in range(2, sheet.max_row + 1):
    code = sheet.cell(r, 1).value
    name = sheet.cell(r, 2).value
    cost = sheet.cell(r, 3).value
    unit_price = sheet.cell(r, 5).value

    if not name:
        continue

    name_str = str(name).strip()
    lower = name_str.lower()

    # Check against hard exclusion list
    matched_reason = None
    for kw in hard_excludes:
        # Use regex boundary or word match where appropriate
        pattern = r'\b' + re.escape(kw) if not kw.endswith(' ') else re.escape(kw)
        if re.search(pattern, lower) or kw in lower:
            matched_reason = kw
            break

    if matched_reason:
        filtered_out.append((name_str, unit_price, matched_reason))
    else:
        pure_drugs.append((name_str, unit_price))

print(f"Total Rows Examined: {sheet.max_row - 1}")
print(f"Filtered Out (Grocery, Cosmetic, Baby, Toiletries, FMCG): {len(filtered_out)}")
print(f"Pure Medical Prescription Drugs: {len(pure_drugs)}")

print("\n--- SAMPLE 30 FILTERED OUT ITEMS (Grocery/Cosmetics/Baby): ---")
for item in filtered_out[:30]:
    print(f"  [REMOVED - {item[2].upper()}]: {item[0]} (LKR {item[1]})")

print("\n--- SAMPLE 30 PURE MEDICAL DRUGS: ---")
for item in pure_drugs[:30]:
    print(f"  [KEPT - DRUG]: {item[0]} (LKR {item[1]})")
