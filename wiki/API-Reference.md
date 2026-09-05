# API Reference

## Authentication

Every request to the PHP backend must include:

```
X-CureRx-Token: <token>
```

The token is injected into `window.__crxToken` by `index.php` when the page loads. It is an HMAC-SHA256 signed value generated from the installation secret in `config/db_config.json`. Requests without a valid token receive `HTTP 401`.

The JS `api` module (`assets/js/modules/api.js`) attaches this header automatically on all calls.

---

## Base URL

```
http://localhost:8989
```

All endpoints are relative to this base.

---

## Response Format

All endpoints return JSON. Successful responses:

```json
{ "status": "success", "data": { ... } }
```

Error responses:

```json
{ "status": "error", "message": "Human-readable description" }
```

---

## Endpoints

### Patients — `api/patients.php`

| Method | Action | Body / Params |
| :--- | :--- | :--- |
| `GET` | List / search patients | `?q=search&page=0&limit=25` |
| `POST` | Create patient | `{ name, phone, dob, address, patient_code }` |
| `PUT` | Update patient | `{ id, name, phone, … }` |
| `DELETE` | Soft-delete patient | `{ id }` |

### Doctors — `api/doctors.php`

| Method | Action | Body / Params |
| :--- | :--- | :--- |
| `GET` | List all doctors | — |
| `POST` | Create doctor | `{ name, specialization, phone }` |
| `PUT` | Update doctor | `{ id, name, … }` |
| `DELETE` | Delete doctor | `{ id }` |

### Prescriptions — `api/prescriptions.php`

| Method | Action | Body / Params |
| :--- | :--- | :--- |
| `GET` | List prescriptions for patient | `?patient_id=<id>&page=0` |
| `POST` | Create prescription | `{ patient_id, doctor_id, diagnosis, notes, items: [{medicine_name, dosage, frequency, duration, instructions}] }` |

### Queue — `api/queue.php`

| Method | Action | Body / Params |
| :--- | :--- | :--- |
| `GET` | Get today's active queue | — |
| `POST` | Add patient to queue | `{ patient_id, doctor_id, patient_name }` |
| `PUT` | Update queue entry status | `{ id, status }` |

Status values: `waiting`, `in_progress`, `completed`, `cancelled`.

### Channeling Billing — `api/queue_billing.php`

| Method | Action | Body / Params |
| :--- | :--- | :--- |
| `GET` | Get billing details for queue entry | `?queue_id=<id>` |
| `POST` | Record payment | `{ queue_id, amount, payment_method }` |

### Medicines — `api/medicines.php`

| Method | Action | Body / Params |
| :--- | :--- | :--- |
| `GET` | List / search medicines | `?q=search&page=0` |
| `POST` | Add medicine | `{ name, generic_name, type, default_strength, unit_price }` |
| `PUT` | Update medicine | `{ id, … }` |
| `DELETE` | Delete medicine | `{ id }` |

### Settings — `api/settings.php`

| Method | Action | Body / Params |
| :--- | :--- | :--- |
| `GET` | Get all settings | — |
| `POST` | Save settings | `{ clinic_name, address, phone, license_key, … }` |

### Statistics — `api/stats.php`

| Method | Action | Body / Params |
| :--- | :--- | :--- |
| `GET` | Dashboard counts | — |

Returns today's patient count, prescription count, queue totals.

### Backup — `api/backup.php`

| Method | Action | Body / Params |
| :--- | :--- | :--- |
| `GET` | Export database backup | — |
| `POST` | Restore from backup | multipart file upload |

---

## Error Codes

| HTTP Status | Meaning |
| :--- | :--- |
| 200 | Success |
| 400 | Validation error — see `message` field |
| 401 | Missing or invalid `X-CureRx-Token` |
| 404 | Resource not found |
| 500 | Server error — see PHP error log |
