import openpyxl
import json

wb = openpyxl.load_workbook('Price lists.xlsx', data_only=True)
print("=== SHEET NAMES ===")
print(wb.sheetnames)

for sheetname in wb.sheetnames:
    sheet = wb[sheetname]
    print(f"\n--- SHEET: {sheetname} (Rows: {sheet.max_row}, Cols: {sheet.max_column}) ---")
    
    # Print first 10 rows
    for r in range(1, min(15, sheet.max_row + 1)):
        row_vals = [sheet.cell(r, c).value for c in range(1, min(15, sheet.max_column + 1))]
        if any(v is not None for v in row_vals):
            print(f"Row {r}: {row_vals}")
