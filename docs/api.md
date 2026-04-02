# O2O API Documentation

**Base URL:** `{{BASE_URL}}/api/v1`  
**Auth:** Bearer token via `Authorization: Bearer {token}` on all authenticated routes.  
**Content-Type:** `application/json` (use `multipart/form-data` for file uploads).  
**Pagination:** All list endpoints return:

```json
{
    "data": [],
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 100
    },
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    }
}
```

**Errors:**
| Status | Meaning |
|--------|---------|
| 401 | Unauthenticated |
| 403 | Unauthorized / onboarding incomplete |
| 404 | Resource not found |
| 422 | Validation failed — body: `{ "message": "...", "errors": { "field": ["..."] } }` |
| 500 | Server error |

**Filtering / Sorting (Query Builder):** Append `filter[field]=value`, `sort=field` or `sort=-field` (desc),
`include=relation` to any list endpoint that supports it.

---

## 1. Auth

### POST `/auth/login`

**Public**

```json
// Request
{
    "email": "user@example.com",
    "password": "secret"
}

// Response 200
{
    "token": "1|abc...",
    "user": {
        "id": 1,
        "name": "Admin",
        "email": "admin@o2o.com",
        "mobile": null,
        "status": "active",
        "role": {
            "name": "admin",
            "permissions": [
                "customer.view",
                "..."
            ]
        },
        "customer_id": null,
        "last_login_at": "2024-01-01T00:00:00Z"
    }
}
```

### POST `/auth/forgot-password` — Public

```json
// Request
{
    "email": "user@example.com"
}
// Response 200
{
    "message": "Password reset link sent."
}
```

### POST `/auth/reset-password` — Public

```json
// Request
{
    "token": "...",
    "email": "...",
    "password": "newpass",
    "password_confirmation": "newpass"
}
// Response 200
{
    "message": "Password has been reset."
}
```

### POST `/auth/logout` — Auth required

```json
// Response 200
{
    "message": "Logged out successfully."
}
```

### POST `/auth/change-password` — Auth required

```json
// Request
{
    "current_password": "old",
    "password": "new",
    "password_confirmation": "new"
}
// Response 200
{
    "message": "Password changed successfully."
}
```

---

## 2. Profile

### GET `/me`

Returns authenticated user with role + permissions.

### PATCH `/me`

```json
// Request (all optional)
{
    "name": "New Name",
    "mobile": "9999999999"
}
```

### GET `/me/customer`

Client users only. Returns own customer record with wallet, locations, ports.

---

## 3. Users

> Platform users manage platform users. Client admins manage their org's users.  
> Filters: `filter[status]=active|invited|inactive|suspended`, `filter[customer_id]=1` (platform only)  
> Sorts: `sort=name`, `sort=-created_at`

### GET `/users`

### POST `/users`

```json
// Request
{
    "name": "Jane",
    "email": "jane@co.com",
    "mobile": "9999999999",
    "customer_id": 5
}
// customer_id: platform users only — omit for platform user, provide for client user
// Response 201: UserResource (invitation email sent automatically)
```

### GET `/users/{id}`

### PATCH `/users/{id}`

```json
// Request (all optional)
{
    "name": "Updated",
    "email": "new@email.com",
    "mobile": "..."
}
```

### DELETE `/users/{id}`

```json
// Response 200
{
    "message": "User deleted."
}
```

### PATCH `/users/{id}/toggle-active`

Toggles `active ↔ inactive`. Response: UserResource.

---

**UserResource shape:**

```json
{
    "id": 1,
    "name": "Jane",
    "email": "jane@co.com",
    "mobile": "...",
    "status": "active",
    "role": {
        "name": "customer_admin",
        "permissions": [
            "trip.view",
            "..."
        ]
    },
    "customer_id": 5,
    "last_login_at": "...",
    "created_at": "..."
}
```

---

## 4. Customers

> Platform users only (except `GET` for own record — client sees own via `/me/customer`).  
> Filters: `filter[onboarding_status]=pending|submitted|il_parked|il_approved|il_rejected|mfg_rejected|completed`,
`filter[is_active]=true`  
> Sorts: `sort=company_name`, `sort=-created_at`  
> Includes: `include=approvedBy,wallet`

### GET `/customers`

### POST `/customers`

```json
// Request — creates customer + sends invitation to customer_admin user
{
    "first_name": "Raj",
    "last_name": "Shah",
    "company_name": "Raj Exports Pvt Ltd",
    "email": "raj@rajexports.com",
    "mobile": "9876543210"
}
// Response 201: CustomerResource
```

### GET `/customers/{id}`

### PATCH `/customers/{id}`

All profile fields optional. Same fields as onboarding profile save.

### PATCH `/customers/{id}/toggle-active`

### POST `/customers/{id}/approve`

```json
// Request
{
    "remarks": "KYC verified."
}
```

### POST `/customers/{id}/reject`

```json
// Request
{
    "remarks": "GST mismatch."
}  // required
```

### POST `/customers/{id}/park`

```json
// Request
{
    "remarks": "Awaiting CIN."
}  // optional
```

### GET `/customers/{id}/documents`

### GET `/customers/{id}/seals`

### GET `/customers/{id}/orders`

### GET `/customers/{id}/trips`

---

**CustomerResource shape:**

```json
{
    "id": 1,
    "first_name": "Raj",
    "last_name": "Shah",
    "company_name": "Raj Exports Pvt Ltd",
    "email": "raj@rajexports.com",
    "mobile": "9876543210",
    "company_type": "pvt_ltd",
    "industry_type": "Textiles",
    "onboarding_status": "completed",
    "is_active": true,
    "iec_number": "IEC123",
    "gst_number": "...",
    "pan_number": "...",
    "cin_number": null,
    "tin_number": null,
    "cha_number": null,
    "billing_address": "...",
    "billing_city": "Mumbai",
    "billing_state": "Maharashtra",
    "billing_pincode": "400001",
    "billing_country": "India",
    "billing_landmark": null,
    "primary_contact_name": "...",
    "primary_contact_email": "...",
    "primary_contact_mobile": "...",
    "alternate_contact_name": null,
    "alternate_contact_phone": null,
    "alternate_contact_email": null,
    "il_remarks": null,
    "il_approved_at": null,
    "approved_by": {
        "id": 2,
        "name": "IL Admin"
    },
    "wallet": {
        "costing_type": "cash",
        "cost_balance": 5000.00,
        "credit_used": 0,
        "credit_capping": null
    },
    "created_at": "...",
    "updated_at": "..."
}
```

---

## 5. Onboarding

> Client users only. All mutations blocked once status is `submitted / il_approved / completed`.  
> `il_parked` and `il_rejected` are editable (customer can fix and resubmit).

### GET `/onboarding/status`

```json
// Response 200
{
    "onboarding_status": "pending",
    "can_submit": false,
    "customer": {
        /* CustomerResource */
    },
    "signatories": [
        /* AuthorizedSignatoryResource[] */
    ],
    "documents": [
        /* CustomerDocumentResource[] */
    ],
    "ports": [
        /* CustomerPort[] */
    ],
    "checklist": {
        "profile_complete": false,
        "has_signatories": false,
        "required_docs": [
            "gst_cert",
            "pan_card",
            "iec_cert"
        ],
        "uploaded_doc_types": [],
        "has_ports": false
    }
}
```

### POST `/onboarding/profile` · PUT `/onboarding/profile`

```json
// Request — all optional, send only changed fields
{
    "first_name": "Raj",
    "last_name": "Shah",
    "company_name": "...",
    "mobile": "...",
    "email": "...",
    "company_type": "pvt_ltd|llp|proprietorship|partnership|public_ltd",
    "industry_type": "...",
    "gst_number": "...",
    "pan_number": "...",
    "iec_number": "...",
    "cin_number": "...",
    "tin_number": "...",
    "cha_number": "...",
    "billing_address": "...",
    "billing_landmark": "...",
    "billing_city": "...",
    "billing_state": "...",
    "billing_pincode": "...",
    "billing_country": "India",
    "primary_contact_name": "...",
    "primary_contact_email": "...",
    "primary_contact_mobile": "...",
    "alternate_contact_name": "...",
    "alternate_contact_phone": "...",
    "alternate_contact_email": "..."
}
// Response 200: CustomerResource
```

### POST `/onboarding/signatories`

`multipart/form-data`
| Field | Type | Required |
|-------|------|----------|
| name | string | ✓ |
| designation | string | — |
| id_proof | file (pdf/jpg/png, max 5MB) | — |

Response 201: AuthorizedSignatoryResource

### PUT `/onboarding/signatories/{id}`

Same fields, all optional.

### DELETE `/onboarding/signatories/{id}`

```json
{
    "message": "Signatory removed."
}
```

### POST `/onboarding/documents`

`multipart/form-data`
| Field | Type | Required |
|-------|------|----------|
| doc_type | enum | ✓ |
| doc_number | string | — |
| file | file (pdf/jpg/png, max 10MB) | ✓ |

`doc_type` values: `gst_cert` `pan_card` `iec_cert` `certificate_of_registration` `self_stuffing_cert` `cha_auth_letter`
`tin` `supporting`

Response 201: CustomerDocumentResource

### DELETE `/onboarding/documents/{id}`

### POST `/onboarding/ports`

```json
// Request — full replace, send complete desired set
{
    "port_ids": [
        1,
        2,
        3
    ]
}
// Response 200
{
    "message": "Ports saved."
}
```

### POST `/onboarding/submit`

Validates checklist server-side. Moves status to `submitted`.

```json
// Response 422 if incomplete
{
    "message": "Onboarding incomplete.",
    "errors": [
        "Field 'gst_number' is required before submission.",
        "..."
    ]
}
// Response 200
{
    "message": "Onboarding submitted successfully.",
    "customer": {
        /* CustomerResource */
    }
}
```

---

**AuthorizedSignatoryResource:**

```json
{
    "id": 1,
    "name": "Raj Shah",
    "designation": "Director",
    "id_proof_url": "https://...",
    "created_at": "..."
}
```

**CustomerDocumentResource:**

```json
{
    "id": 1,
    "doc_type": "gst_cert",
    "doc_number": "27AABCU...",
    "file_name": "gst.pdf",
    "url": "https://signed-url...",
    "sepio_file_name": null,
    "uploaded_by": {
        "id": 5,
        "name": "Raj Shah"
    },
    "created_at": "..."
}
```

---

## 6. Ports

> Read: all authenticated users. Write: platform users only (`port.manage`).  
> Filters: `filter[port_category]=port|icd|cfs`, `filter[is_active]=true`, `filter[country]=India`,
`filter[search]=JNPT`  
> Sorts: `sort=name`, `sort=code`

### GET `/ports`

### POST `/ports`

```json
{
    "name": "Jawaharlal Nehru Port",
    "code": "INNSA1",
    "city": "Navi Mumbai",
    "country": "India",
    "port_category": "port|icd|cfs",
    "sepio_id": 101,
    "lat": 18.9500,
    "lng": 72.9500,
    "geo_fence_radius": 2000
}
```

### GET `/ports/{id}`

### PATCH `/ports/{id}` — all fields optional

### PATCH `/ports/{id}/toggle-active`

**PortResource:**

```json
{
    "id": 1,
    "name": "JNPT",
    "code": "INNSA1",
    "city": "Navi Mumbai",
    "country": "India",
    "port_category": "port",
    "sepio_id": 101,
    "lat": 18.95,
    "lng": 72.95,
    "geo_fence_radius": 2000,
    "is_active": true,
    "created_at": "..."
}
```

---

## 7. Customer Ports

> Client users manage their own port selections (added after onboarding too).  
> Filters: `filter[port_category]=port|icd|cfs`, `filter[is_active]=true`, `filter[search]=JNPT`  
> Includes: `include=port`

### GET `/customer-ports`

### POST `/customer-ports`

```json
// Request — remaining values copied from master port
{
    "port_id": 1,
    "lat": 18.9600,
    // optional override
    "lng": 72.9600,
    // optional override
    "geo_fence_radius": 2500
    // optional override
}
// Response 201: CustomerPortResource
```

### GET `/customer-ports/{id}`

### PATCH `/customer-ports/{id}`

```json
// Only these three are editable
{
    "lat": 18.97,
    "lng": 72.97,
    "geo_fence_radius": 3000,
    "is_active": false
}
```

### DELETE `/customer-ports/{id}`

**CustomerPortResource:**

```json
{
    "id": 1,
    "port_id": 1,
    "port_category": "port",
    "name": "JNPT",
    "code": "INNSA1",
    "lat": 18.96,
    "lng": 72.96,
    "geo_fence_radius": 2500,
    "is_active": true,
    "port": {
        /* PortResource — when included */
    },
    "created_at": "..."
}
```

---

## 8. Locations

> Client users manage their own. TenantScope auto-filters.  
> Filters: `filter[location_type]=billing|shipping|both`, `filter[is_active]=true`, `filter[search]=Mumbai`  
> Sorts: `sort=name`, `sort=city`  
> Includes: `include=createdBy`

### GET `/locations`

### POST `/locations`

```json
{
    "location_type": "billing|shipping|both",
    "name": "Mumbai Warehouse",
    "gst_number": "27AABCU...",
    // required when type is billing or both
    "address": "...",
    "landmark": "...",
    "city": "Mumbai",
    "state": "Maharashtra",
    "pincode": "400001",
    "country": "India",
    "contact_person": "...",
    "contact_number": "...",
    "lat": 19.076,
    "lng": 72.877
}
```

### GET `/locations/{id}`

### PATCH `/locations/{id}` — all fields optional

### DELETE `/locations/{id}`

Returns 422 if location is used in active routes.

### PATCH `/locations/{id}/toggle-active`

**CustomerLocationResource:**

```json
{
    "id": 1,
    "location_type": "billing",
    "name": "Mumbai Warehouse",
    "gst_number": "27AABCU...",
    "address": "...",
    "landmark": "...",
    "city": "Mumbai",
    "state": "Maharashtra",
    "pincode": "400001",
    "country": "India",
    "contact_person": "...",
    "contact_number": "...",
    "lat": 19.076,
    "lng": 72.877,
    "is_active": true,
    "sepio_address_id": null,
    "created_by": {
        "id": 5,
        "name": "Raj"
    },
    "created_at": "...",
    "updated_at": "..."
}
```

---

## 9. Routes

> Trip templates. Client users only.  
> Filters: `filter[trip_type]=import|export|domestic`, `filter[transport_mode]=road|sea|multimodal`,
`filter[is_active]=true`, `filter[search]=Delhi`  
> Includes: `include=dispatchLocation,deliveryLocation,originPort,destinationPort`

### GET `/routes`

### POST `/routes`

```json
{
    "name": "Mumbai to Delhi",
    "trip_type": "domestic",
    "transport_mode": "road",
    "dispatch_location_id": 1,
    "delivery_location_id": 2,
    "origin_port_id": null,
    "destination_port_id": null,
    "notes": "..."
}
```

### GET `/routes/{id}`

### PATCH `/routes/{id}` — all fields optional

### DELETE `/routes/{id}`

### PATCH `/routes/{id}/toggle-active`

**CustomerRouteResource:**

```json
{
    "id": 1,
    "name": "Mumbai to Delhi",
    "trip_type": "domestic",
    "transport_mode": "road",
    "is_active": true,
    "notes": "...",
    "dispatch_location": {
        /* CustomerLocationResource */
    },
    "delivery_location": {
        /* CustomerLocationResource */
    },
    "origin_port": null,
    "destination_port": null,
    "created_at": "...",
    "updated_at": "..."
}
```

---

## 10. Wallet & Pricing

> Wallet: platform creates/manages, client reads own via `/me/customer`.  
> Pricing: platform sets per customer, client reads own tiers.

### GET `/customers/{id}/wallet`

### POST `/customers/{id}/wallet` — platform only

```json
{
    "il_policy_number": "POL123",
    "il_policy_expiry": "2025-12-31",
    "sum_insured": 5000000,
    "gwp": 25000,
    "costing_type": "cash|credit",
    "credit_period": 30,
    // required if credit
    "credit_capping": 100000,
    "freight_rate_per_seal": 50.00
}
```

### PUT `/customers/{id}/wallet` — platform only, same fields all optional

### POST `/customers/{id}/wallet/top-up` — platform only

```json
{
    "amount": 10000.00,
    "remarks": "Advance payment received."
}
```

### GET `/customers/{id}/wallet/transactions`

Returns paginated transaction history.

### GET `/customers/{id}/pricing` · GET `/pricing` (client reads own)

```json
// Response 200
[
    {
        "id": 1,
        "min_quantity": 20,
        "max_quantity": 99,
        "price_per_seal": 150.00
    },
    {
        "id": 2,
        "min_quantity": 100,
        "max_quantity": null,
        "price_per_seal": 120.00
    }
]
```

### POST `/customers/{id}/pricing` — platform only, full replace

```json
{
    "tiers": [
        {
            "min_quantity": 20,
            "max_quantity": 99,
            "price_per_seal": 150.00
        },
        {
            "min_quantity": 100,
            "max_quantity": null,
            "price_per_seal": 120.00
        }
    ]
}
```

### POST `/pricing/calculate` — client only, order cost preview

```json
// Request
{
    "quantity": 50
}
// Response 200
{
    "quantity": 50,
    "unit_price": 150.00,
    "seal_cost": 7500.00,
    "freight_amount": 2500.00,
    "gst_amount": 1800.00,
    "total_amount": 11800.00
}
```

**WalletResource:**

```json
{
    "id": 1,
    "il_policy_number": "POL123",
    "il_policy_expiry": "2025-12-31",
    "sum_insured": 5000000,
    "gwp": 25000,
    "costing_type": "cash",
    "credit_period": null,
    "credit_capping": null,
    "credit_used": 0,
    "freight_rate_per_seal": 50.00,
    "cost_balance": 15000.00,
    "pricing_tiers": [
        /* when loaded */
    ],
    "updated_at": "..."
}
```

---

## 11. Seal Orders

> Filters:
`filter[status]=il_pending|il_approved|il_rejected|il_parked|mfg_pending|in_progress|order_placed|in_transit|completed|mfg_rejected`,
`filter[payment_type]=cash|credit|advance_balance`, `filter[search]=IL1110001`  
> Sorts: `sort=-ordered_at`, `sort=total_amount`  
> Includes: `include=billingLocation,shippingLocation,orderedBy,ilApprovedBy`

### GET `/orders`

### POST `/orders`

```json
{
    "quantity": 100,
    "payment_type": "cash|credit|advance_balance",
    "billing_location_id": 1,
    "shipping_location_id": 2,
    "receiver_name": "Raj",
    "receiver_contact": "9999999999",
    "port_ids": [
        1,
        2
    ]
    // customer_port IDs (not master port IDs)
}
// Unit price, costs, GST all calculated server-side.
// Response 201: SealOrderResource
```

### GET `/orders/{id}`

### POST `/orders/{id}/approve` — platform only

```json
{
    "remarks": "Approved.",
    "remarks_file": "<file>"
}  // multipart
```

### POST `/orders/{id}/reject` — platform only

```json
{
    "remarks": "Duplicate order."
}  // remarks required
```

### POST `/orders/{id}/park` — platform only

```json
{
    "remarks": "Pending KYC update."
}
```

### POST `/orders/{id}/seals` — platform only, ingest after dispatch

```json
{
    "seal_numbers": [
        "SPPL10901584",
        "SPPL10901585"
    ],
    "dispatched_at": "2024-01-15T10:00:00Z"
}
// seal_numbers count must match order quantity exactly
// Response 200
{
    "message": "100 seals ingested successfully.",
    "order_ref": "IL1110001"
}
```

**SealOrderResource:**

```json
{
    "id": 1,
    "order_ref": "IL1110001",
    "status": "il_pending",
    "quantity": 100,
    "unit_price": 120.00,
    "seal_cost": 12000.00,
    "freight_amount": 5000.00,
    "gst_amount": 3060.00,
    "total_amount": 20060.00,
    "payment_type": "cash",
    "receiver_name": "Raj",
    "receiver_contact": "9999999999",
    "il_remarks": null,
    "il_approved_at": null,
    "sepio_order_id": null,
    "courier_name": null,
    "courier_docket_number": null,
    "seals_dispatched_at": null,
    "seals_delivered_at": null,
    "billing_location": {
        /* CustomerLocationResource */
    },
    "shipping_location": {
        /* CustomerLocationResource */
    },
    "ordered_by": {
        "id": 5,
        "name": "Raj"
    },
    "il_approved_by": null,
    "ordered_at": "...",
    "updated_at": "..."
}
```

---

## 12. Seals

> Filters: `filter[status]=in_inventory|assigned|in_transit|used|tampered|lost`,
`filter[sepio_status]=valid|tampered|broken|unknown`, `filter[seal_order_id]=1`, `filter[trip_id]=1`,
`filter[search]=SPPL`  
> Sorts: `sort=seal_number`, `sort=-last_scan_at`  
> Includes: `include=order,trip`

### GET `/seals`

### GET `/seals/{id}`

### GET `/seals/{id}/status-history`

Returns paginated scan logs (most recent first).

**SealResource:**

```json
{
    "id": 1,
    "seal_number": "SPPL10901584",
    "status": "in_inventory",
    "sepio_status": "unknown",
    "last_scan_at": null,
    "delivered_at": null,
    "trip_id": null,
    "order": {
        "id": 1,
        "order_ref": "IL1110001"
    },
    "trip": null,
    "created_at": "...",
    "updated_at": "..."
}
```

**SealStatusLogResource:**

```json
{
    "id": 1,
    "status": "valid",
    "scan_location": "CH Cochin (INCOK1)",
    "scanned_lat": 9.9312,
    "scanned_lng": 76.2673,
    "scanned_by": "officer@customs.gov.in",
    "checked_at": "..."
}
```

---

## 13. Trips

> Filters: `filter[status]=draft|in_transit|at_port|on_vessel|vessel_arrived|delivered|completed`,
`filter[trip_type]=import|export|domestic`, `filter[transport_mode]=road|sea|multimodal`, `filter[search]=TR0000001`,
`filter[dispatch_date_from]=2024-01-01`, `filter[dispatch_date_to]=2024-12-31`  
> Sorts: `sort=trip_ref`, `sort=-dispatch_date`, `sort=-created_at`  
> Includes: `include=seal,route,createdBy,documents`

### GET `/trips`

### POST `/trips`

```json
{
    "route_id": 1,
    // optional — prefills locations/ports/type/mode if not overridden
    "trip_type": "import",
    "transport_mode": "road",
    "seal_id": 5,
    // optional — must be in_inventory
    "driver_name": "Ravi Kumar",
    "driver_license": "MH01 2024 1234567",
    "driver_aadhaar": "1234-5678-9012",
    "driver_phone": "9876543210",
    "vehicle_number": "MH01AB1234",
    "vehicle_type": "truck|trailer|container_carrier",
    "transporter_name": "Fast Cargo",
    "transporter_id": "TC001",
    "container_number": "MSCU1234567",
    "container_type": "20GP",
    "seal_issue_date": "2024-01-15",
    "cargo_type": "Electronics",
    "cargo_description": "...",
    "hs_code": "8471.30",
    "gross_weight": 1500.500,
    "net_weight": 1400.000,
    "weight_unit": "kg",
    "quantity": 50,
    "quantity_unit": "cartons",
    "declared_cargo_value": 500000.00,
    "invoice_number": "INV2024001",
    "invoice_date": "2024-01-14",
    "eway_bill_number": "EWB1234567890",
    "eway_bill_validity_date": "2024-01-20",
    "dispatch_location_id": 1,
    // resolved to snapshot server-side
    "delivery_location_id": 2,
    "origin_port_id": 1,
    "destination_port_id": 2,
    "dispatch_date": "2024-01-15",
    "expected_delivery_date": "2024-01-18"
}
// Response 201: TripResource
```

### GET `/trips/{id}`

### PATCH `/trips/{id}`

All fields optional. Cannot update `completed` trips. Can change `status` to:
`in_transit|at_port|on_vessel|vessel_arrived|delivered`. Can swap `seal_id` — old seal released, new seal assigned
automatically.

### POST `/trips/{id}/vessel-info`

```json
{
    "vessel_name": "MSC Gulsun",
    "vessel_imo_number": "9811000",
    "voyage_number": "FE-120W",
    "bill_of_lading": "MEDUAB123456",
    "eta": "2024-02-01T06:00:00Z",
    "etd": "2024-01-20T14:00:00Z"
}
// Response 200: TripResource
```

### POST `/trips/{id}/confirm-destination`

Locks the trip (`completed`), marks seal `used`, sets ePOD completed.

```json
{
    "notes": "Delivery received in good condition.",
    "actual_delivery_date": "2024-01-18"
}
// Response 200: TripResource
```

### GET `/trips/{id}/timeline`

Returns ordered trip events array.

```json
[
    {
        "id": 1,
        "event_type": "trip_created",
        "previous_status": null,
        "new_status": "draft",
        "event_data": {
            "trip_ref": "TR0000001"
        },
        "actor_type": "user",
        "actor_id": 5,
        "created_at": "..."
    }
]
```

### GET `/trips/{id}/seal-status`

```json
{
    "seal_number": "SPPL10901584",
    "status": "assigned",
    "sepio_status": "valid",
    "last_scan_at": "...",
    "latest_log": {
        /* SealStatusLogResource */
    }
}
```

**TripResource shape** (grouped for clarity):

```json
{
    "id": 1,
    "trip_ref": "TR0000001",
    "status": "draft",
    "trip_type": "import",
    "transport_mode": "road",
    "risk_score": null,
    "driver_name": "...",
    "driver_license": "...",
    "driver_phone": "...",
    "vehicle_number": "...",
    "vehicle_type": "truck",
    "transporter_name": "...",
    "container_number": "...",
    "container_type": "...",
    "seal_issue_date": "...",
    "cargo_type": "...",
    "cargo_description": "...",
    "hs_code": "...",
    "gross_weight": 1500.5,
    "net_weight": 1400.0,
    "weight_unit": "kg",
    "quantity": 50,
    "quantity_unit": "cartons",
    "declared_cargo_value": 500000.00,
    "invoice_number": "...",
    "invoice_date": "...",
    "eway_bill_number": "...",
    "eway_bill_validity_date": "...",
    "dispatch": {
        "location_name": "Mumbai Warehouse",
        "address": "...",
        "city": "Mumbai",
        "state": "Maharashtra",
        "pincode": "400001",
        "country": "India",
        "contact_person": "...",
        "contact_number": "...",
        "lat": 19.076,
        "lng": 72.877
    },
    "delivery": {
        "location_name": "Delhi Hub",
        "...": "..."
    },
    "origin_port": {
        "name": "JNPT",
        "code": "INNSA1",
        "category": "port"
    },
    "destination_port": {
        "name": "Mundra",
        "code": "INMUN1",
        "category": "port"
    },
    "vessel_name": null,
    "vessel_imo_number": null,
    "voyage_number": null,
    "bill_of_lading": null,
    "eta": null,
    "etd": null,
    "dispatch_date": "2024-01-15",
    "trip_start_time": null,
    "expected_delivery_date": "2024-01-18",
    "actual_delivery_date": null,
    "trip_end_time": null,
    "epod_status": "pending",
    "epod_confirmed_at": null,
    "destination_confirmed_at": null,
    "destination_confirmation_notes": null,
    "seal": {
        /* SealResource */
    },
    "route": {
        "id": 1,
        "name": "Mumbai to Delhi"
    },
    "created_by": {
        "id": 5,
        "name": "Raj"
    },
    "documents": [
        /* TripDocumentResource[] */
    ],
    "created_at": "...",
    "updated_at": "..."
}
```

---

## 14. Trip Documents

### GET `/trips/{id}/documents`

### POST `/trips/{id}/documents`

`multipart/form-data`
| Field | Type | Required |
|-------|------|----------|
| doc_type | enum | ✓ |
| file | file (pdf/jpg/png, max 10MB) | ✓ |

`doc_type` values: `e_way_bill` `e_invoice` `e_pod` `supporting`

Response 201: TripDocumentResource

### DELETE `/trips/{id}/documents/{documentId}`

**TripDocumentResource:**

```json
{
    "id": 1,
    "doc_type": "e_way_bill",
    "file_name": "eway.pdf",
    "url": "https://signed-url...",
    "uploaded_by": {
        "id": 5,
        "name": "Raj"
    },
    "created_at": "..."
}
```

---

## 15. Dashboard & Reports

### GET `/dashboard/stats`

Platform and client users get different shapes:

**Platform response:**

```json
{
    "customers": {
        "total": 50,
        "pending": 5,
        "submitted": 10,
        "il_approved": 8,
        "il_parked": 2,
        "completed": 25
    },
    "orders": {
        "total": 200,
        "il_pending": 12,
        "il_approved": 5,
        "in_transit": 8,
        "completed": 175
    },
    "trips": {
        "total": 500,
        "draft": 20,
        "in_transit": 30,
        "at_port": 15,
        "on_vessel": 10,
        "vessel_arrived": 5,
        "delivered": 8,
        "completed": 412
    },
    "seals": {
        "total": 10000,
        "in_inventory": 4500,
        "assigned": 300,
        "in_transit": 200,
        "used": 4900,
        "tampered": 50,
        "lost": 50
    },
    "recent_orders": [
        /* 5 items */
    ],
    "recent_trips": [
        /* 5 items */
    ],
    "tampered_seals": [
        /* up to 10 */
    ]
}
```

**Client response:**

```json
{
    "orders": {
        "total": 10,
        "il_pending": 1,
        "in_transit": 2,
        "completed": 7
    },
    "seals": {
        "total": 500,
        "in_inventory": 200,
        "assigned": 50,
        "tampered": 2
    },
    "trips": {
        "total": 80,
        "active": 15,
        "completed": 60,
        "draft": 5
    },
    "wallet": {
        "costing_type": "cash",
        "cost_balance": 5000.00,
        "credit_used": 0,
        "credit_capping": null,
        "policy_expiry": "2025-12-31"
    },
    "recent_trips": [
        /* 5 items */
    ],
    "recent_orders": [
        /* 5 items */
    ],
    "tampered_seals": [
        /* up to 5 */
    ]
}
```

### GET `/reports/trips`

### GET `/reports/seals`

### GET `/reports/orders`

All reports share the same query params:
| Param | Type | Notes |
|-------|------|-------|
| filter[customer_id] | int | Platform only |
| filter[status] | string | Enum value |
| filter[trip_type] | string | trips report |
| filter[transport_mode] | string | trips report |
| filter[payment_type] | string | orders report |
| filter[sepio_status] | string | seals report |
| filter[from] | date | `YYYY-MM-DD` |
| filter[to] | date | `YYYY-MM-DD` |

**Response shape (all three):**

```json
{
    "summary": {
        /* aggregate counts/sums */
    },
    "trips|seals|orders": {
        /* paginated resource collection */
    }
}
```

---

## 16. Enums Reference

| Enum                  | Values                                                                                                                                  |
|-----------------------|-----------------------------------------------------------------------------------------------------------------------------------------|
| `onboarding_status`   | `pending` `submitted` `il_parked` `il_approved` `il_rejected` `mfg_rejected` `completed`                                                |
| `company_type`        | `pvt_ltd` `llp` `proprietorship` `partnership` `public_ltd`                                                                             |
| `user_status`         | `invited` `active` `inactive` `suspended`                                                                                               |
| `location_type`       | `billing` `shipping` `both`                                                                                                             |
| `port_category`       | `port` `icd` `cfs`                                                                                                                      |
| `seal_order_status`   | `il_pending` `il_approved` `il_rejected` `il_parked` `mfg_pending` `in_progress` `order_placed` `in_transit` `completed` `mfg_rejected` |
| `seal_status`         | `in_inventory` `assigned` `in_transit` `used` `tampered` `lost`                                                                         |
| `sepio_seal_status`   | `valid` `tampered` `broken` `unknown`                                                                                                   |
| `trip_status`         | `draft` `in_transit` `at_port` `on_vessel` `vessel_arrived` `delivered` `completed`                                                     |
| `trip_type`           | `import` `export` `domestic`                                                                                                            |
| `transport_mode`      | `road` `sea` `multimodal`                                                                                                               |
| `vehicle_type`        | `truck` `trailer` `container_carrier`                                                                                                   |
| `doc_type` (customer) | `gst_cert` `pan_card` `iec_cert` `certificate_of_registration` `self_stuffing_cert` `cha_auth_letter` `tin` `supporting`                |
| `doc_type` (trip)     | `e_way_bill` `e_invoice` `e_pod` `supporting`                                                                                           |
| `costing_type`        | `cash` `credit`                                                                                                                         |
| `payment_type`        | `cash` `credit` `advance_balance`                                                                                                       |
| `role`                | `admin` `customer_admin`                                                                                                                |

---

## 17. Permissions Reference

| Permission                       | admin | customer_admin |
|----------------------------------|:-----:|:--------------:|
| `customer.view`                  |   ✓   |  ✓ (own only)  |
| `customer.create`                |   ✓   |       —        |
| `customer.update`                |   ✓   |  ✓ (own only)  |
| `customer.approve/reject/park`   |   ✓   |       —        |
| `user.view/create/update/delete` |   ✓   |  ✓ (own org)   |
| `port.view`                      |   ✓   |       ✓        |
| `port.manage`                    |   ✓   |       —        |
| `location.*`                     |   ✓   |       ✓        |
| `route.*`                        |   ✓   |       ✓        |
| `wallet.view`                    |   ✓   |    ✓ (own)     |
| `wallet.manage`                  |   ✓   |       —        |
| `pricing.view`                   |   ✓   |    ✓ (own)     |
| `pricing.manage`                 |   ✓   |       —        |
| `seal_order.view/create`         |   ✓   |       ✓        |
| `seal_order.approve/reject/park` |   ✓   |       —        |
| `seal.view/assign`               |   ✓   |       ✓        |
| `trip.*`                         |   ✓   |       ✓        |
| `document.upload/delete`         |   ✓   |       ✓        |
| `report.view`                    |   ✓   |       ✓        |
