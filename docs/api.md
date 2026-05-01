# O2O Logistics & Seal Management — API Documentation

**Base URL:** `https://elephantsoftwares.com/o2o-backend-git/public/api/v1`  
**Auth:** Bearer token via Laravel Sanctum — include `Authorization: Bearer {token}` on all authenticated requests.  
**Content-Type:** `application/json` unless noted as `multipart/form-data`.  
**Accept:** `application/json` on all requests.

---

## Enums Reference

```
CompanyType:              pvt_ltd | llp | proprietorship | partnership | public_ltd
CustomerOnboardingStatus: pending | submitted | il_parked | il_approved | il_rejected | mfg_rejected | completed
CustomerDocType:          gst_cert | pan_card | iec_cert | certificate_of_registration | self_stuffing_cert | cha_auth_letter | tin | supporting
PortCategory:             port | icd | cfs
SealStatus:               in_inventory | assigned | in_transit | used | tampered | lost
SepioSealStatus:          valid | tampered | broken | unknown
SealOrderStatus:          il_pending | il_approved | il_rejected | il_parked | mfg_pending | in_progress | order_placed | in_transit | mfg_completed | completed | mfg_rejected
TripStatus:               draft | in_transit | at_port | on_vessel | in_transshipment | vessel_arrived | out_for_delivery | delivered | completed
TripType:                 import | export | domestic
TripTransportationMode:   road | sea | multimodal
TripSegmentTransportMode: road | sea
TripSegmentTrackingSource: gps | tcl_tracker | e_lock | driver_mobile | driver_sim | fast_tag | vessel_ais
TripDocType:              e_way_bill | e_invoice | e_pod | supporting
UserStatus:               invited | active | inactive | suspended
WalletCoastingType:       cash | credit
VehicleType:              truck | trailer | container_carrier
PaymentType:              cash | credit | advance_balance
EpodStatus:               pending | completed
ContainerTrackingStatus:  not_registered | pending | active | failed
```

---

## System Architecture & User Roles

**Platform Users (IL/Admin):** `customer_id = null`. See all data across all tenants. Manage customers, wallets,
pricing, order approvals, and Sepio integration. Can perform actions on behalf of any customer by passing `customer_id`
in request body.

**Client Users (Customer Staff):** Scoped to their `customer_id` via global `TenantScope`. Can only see and modify their
own data. Many requests that require `customer_id` for platform users are automatically resolved from the authenticated
user's context for client users.

**TenantScope:** All models with TenantScope automatically filter by `customer_id` when a client user is authenticated.
Platform users bypass all tenant filtering and see all records.

**EnsureCustomerActive Middleware:** Applied to all authenticated routes. Rejects client users whose company
`is_active = false` with 403 and revokes their token.

**EnsureOnboarded Middleware:** Routes under the `onboarded` group reject client users whose
`onboarding_status !== completed` with 403.

**BindTenantScope Middleware:** Applied globally to all API routes. Loads `role.permissions` into memory once per
request and sets `tenant.customer_id` in the IoC container.

---

## Core System Flows

### Flow 1 — Customer Lifecycle

```
1. IL creates Customer → auto-creates customer_admin user → sends invite email with credentials
2. IL configures Wallet + Pricing Tiers for the customer
3. Customer logs in (status: invited → active on first login)
4. Customer completes Onboarding:
   - Save profile (company details, billing address, contacts)
   - Add authorized signatories
   - Upload KYC documents (gst_cert, pan_card, iec_cert, certificate_of_registration, self_stuffing_cert required)
   - Select operating ports (at least 1 port + 1 ICD required for Sepio registration)
5. Customer submits → onboarding_status: submitted
6. IL reviews → can:
   - Park (il_parked) — awaiting more info
   - Reject (il_rejected) — with mandatory remarks
   - Approve (il_approved) → SepioOnboardCustomerJob dispatched
7. SepioOnboardCustomerJob:
   - Registers company on Sepio (gets sepio_company_id)
   - Syncs all customer locations as billing + shipping addresses
   - Uploads all KYC documents
8. SepioVerificationStatusPollJob polls Sepio every 30 min:
   - VERIFIED → onboarding_status: completed
   - REJECTED → onboarding_status: mfg_rejected (with rejected doc list in remarks)
9. Once completed → customer can access all onboarding-gated routes
```

### Flow 2 — Seal Order Lifecycle

```
1. Customer previews cost → POST /pricing/calculate
2. Customer places order → POST /orders → status: il_pending
   - payment_type=advance_balance: wallet balance debited immediately
   - payment_type=credit: credit_used checked against credit_capping
3. IL reviews:
   - Park → il_parked | Reject → il_rejected (advance_balance auto-refunded) | Approve → il_approved
4. On Approve → SepioPlaceOrderJob dispatched → places order on Sepio → status: mfg_pending
5. SepioOrderStatusSyncJob (every 15 min) syncs Sepio status:
   mfg_pending → order_placed → in_progress → in_transit → mfg_completed
6. SepioSealAllocationPollJob (hourly) detects seal_range from Sepio:
   - Expands seal range (e.g. "SPPL10009259 - SPPL10009260") into individual seal numbers
   - Ingests seals → status: completed
   OR: IL manually ingests via POST /orders/{order}/seals
7. Seals appear in customer inventory with status: in_inventory
```

### Flow 3 — Trip Lifecycle (State Machine)

```
draft → in_transit → at_port → on_vessel → in_transshipment → vessel_arrived → out_for_delivery → delivered → completed
```

```
1. Customer creates trip (status: draft) — assigns a seal from inventory
   - Seal status changes: in_inventory → assigned
2. Customer starts trip → POST /trips/{trip}/start
   - Status: draft → in_transit
   - Sepio installSeal() called — seal registered on Sepio with trip details
   - Seal status: assigned → in_transit (on Sepio confirmation)
   - For sea/multimodal: RegisterContainerTrackingJob dispatched
3. Status transitions via PATCH /trips/{trip} with status field, or:
   - Geofence auto-advance: vehicle tracking detects arrival → fires VehicleArrivedAtDestination event
   - Seal scan auto-advance: seal scanned at origin port → status: in_transit → at_port
4. Add vessel info → POST /trips/{trip}/vessel-info
5. Manual status updates: at_port | on_vessel | vessel_arrived | delivered
6. Upload trip documents at any point (until completed)
7. POST /trips/{trip}/confirm-epod → status: completed
   - Seal status: → used
   - Trip locked — no further edits, document uploads, or segment changes
```

### Flow 4 — Vehicle & Container Tracking

```
Road/Multimodal tracking:
  - Driver mobile app pushes location via POST /tracking/driver-mobile (token-only, no auth)
    OR via POST /trips/{trip}/location (Sanctum or X-Tracking-Token header)
  - FastTagPollJob polls ULIP FastTag API every 15 min for toll plaza hits
  - TripTrackingService records points and checks geofence arrival radius (5 km default)
  - On arrival at destination → VehicleArrivedAtDestination event → AdvanceTripStatusOnArrival listener

Sea tracking:
  - ContainerTrackingService registers container with Kpler API on trip start
  - Kpler webhooks push shipment updates → ContainerTrackingWebhookController
  - SyncContainerMilestonesJob syncs full transportation timeline
  - VesselAisPollJob (every 30 min) polls MarineTraffic AIS for vessel position
  - Vessel position stored as TripTrackingPoint with source: vessel_ais
```

### Flow 5 — Onboarding Checklist

Required fields before submit:

- `company_type`, `company_name`, `gst_number`, `pan_number`, `iec_number`
- `billing_address`, `billing_city`, `billing_state`, `billing_pincode`
- `primary_contact_name`, `primary_contact_email`
- At least 1 authorized signatory
- Required documents: `gst_cert`, `pan_card`, `iec_cert`, `certificate_of_registration`, `self_stuffing_cert`
- At least 1 port selected

Saving profile auto-creates/updates a `CustomerLocation` from billing address (used for Sepio sync).

### Flow 6 — Wallet & Pricing

```
Cash flow:         Customer orders → pays cash on delivery, no wallet deduction
Credit flow:       Wallet costing_type=credit → credit_used tracked (up to credit_capping)
Advance flow:      IL tops up wallet (cost_balance) → customer uses advance_balance
                   → deducted at order creation → refunded automatically on IL rejection
```

---

## Standard Response Formats

### Success (single resource)

```json
{
    "data": {
        ...resource
        fields
    }
}
```

### Success (paginated collection)

```json
{
    "data": [
        ...items
    ],
    "links": {
        "first": "...",
        "last": "...",
        "prev": null,
        "next": "..."
    },
    "meta": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 20,
        "total": 100
    }
}
```

### Validation Error (422)

```json
{
    "message": "Validation failed.",
    "errors": {
        "field_name": [
            "The field name is required."
        ]
    }
}
```

### Auth Error (401)

```json
{
    "message": "Unauthenticated."
}
```

### Authorization Error (403)

```json
{
    "message": "This action is unauthorized."
}
```

### Not Found (404)

```json
{
    "message": "ModelName not found."
}
```

### Business Logic Error (422)

```json
{
    "message": "Descriptive error message."
}
```

### Sepio Integration Error (422/502)

```json
{
    "message": "Sepio-specific error message extracted from their API response."
}
```

---

## Query Builder Conventions

Spatie Query Builder is used on list endpoints. Parameters:

| Parameter        | Example                       | Description                                  |
|------------------|-------------------------------|----------------------------------------------|
| `filter[field]`  | `filter[status]=active`       | Exact match filter                           |
| `filter[search]` | `filter[search]=keyword`      | Full-text search across key fields           |
| `sort`           | `sort=-created_at`            | Prefix `-` for descending                    |
| `include`        | `include=relation1,relation2` | Eager load relations                         |
| `per_page`       | `per_page=50`                 | Items per page (default varies per endpoint) |
| `page`           | `page=2`                      | Page number                                  |

---

---

# AUTHENTICATION

## POST /auth/login

**Public.** Validates credentials, checks user status, issues Sanctum token.  
Invited users are auto-promoted to `active` on first successful login.  
Returns 422 if the user's associated company is deactivated.

**Request:**

```json
{
    "email": "string (required, email)",
    "password": "string (required)"
}
```

**Response 200:**

```json
{
    "token": "1|abcdef...",
    "user": {
        "id": 1,
        "name": "Platform Admin",
        "email": "admin@example.com",
        "mobile": null,
        "status": "active",
        "role": {
            "name": "admin",
            "permissions": [
                "customer.view",
                "customer.create",
                "seal_order.approve",
                "..."
            ]
        },
        "customer": null,
        "last_login_at": "2025-04-01T10:00:00.000000Z",
        "created_at": "2025-04-01T10:00:00.000000Z"
    }
}
```

For client users, `customer` will be populated:

```json
"customer": {
"id": 7,
"first_name": "Rajesh",
"last_name": "Sharma",
"email": "rajesh@company.com",
"mobile": "9876543210",
"company_name": "Sharma Exports Pvt Ltd",
"onboarding_status": "pending",
}
```

**Errors:**

- 422: `email → The provided credentials are incorrect.`
- 422: `email → Your account has been suspended.`
- 422: `email → Your account is inactive.`
- 422: `email → Your company account has been deactivated. Please contact support.`

---

## POST /auth/logout

**Authenticated.** Revokes the current Sanctum token.

**Response 200:**

```json
{
    "message": "Logged out successfully."
}
```

---

## POST /auth/forgot-password

**Public.** Sends a password reset link to the given email.

**Request:**

```json
{
    "email": "string (required, email, must exist in users)"
}
```

**Response 200:**

```json
{
    "message": "Password reset link sent."
}
```

---

## POST /auth/reset-password

**Public.** Resets password using token from the reset email.

**Request:**

```json
{
    "token": "string (required)",
    "email": "string (required, email, must exist in users)",
    "password": "string (required, min:8, confirmed)",
    "password_confirmation": "string (required)"
}
```

**Response 200:**

```json
{
    "message": "Password has been reset."
}
```

---

## POST /auth/change-password

**Authenticated.** Changes the authenticated user's own password. Revokes all other active tokens (forces re-login on
other devices).

**Request:**

```json
{
    "current_password": "string (required, must match current password)",
    "password": "string (required, min:8, confirmed, different from current_password)",
    "password_confirmation": "string (required)"
}
```

**Response 200:**

```json
{
    "message": "Password changed successfully."
}
```

---

---

# USER PROFILE

## GET /me

**Authenticated.** Returns the authenticated user's profile with role and permissions.

**Response 200:** UserResource with `role.permissions` and `customer` loaded.

UserResource:

```json
{
    "id": 5,
    "name": "Rajesh Sharma",
    "email": "rajesh@company.com",
    "mobile": "9876543210",
    "status": "active",
    "role": {
        "name": "customer_admin",
        "permissions": [
            "trip.view",
            "trip.create",
            "seal_order.view",
            "..."
        ]
    },
    "customer": {
        "id": 7,
        "first_name": "Rajesh",
        "last_name": "Sharma",
        "email": "rajesh@company.com",
        "mobile": "9876543210",
        "company_name": "Sharma Exports Pvt Ltd"
    },
    "last_login_at": "2025-04-01T10:00:00.000000Z",
    "created_at": "2025-04-01T09:00:00.000000Z"
}
```

---

## PATCH /me

**Authenticated.** Updates own profile (name and/or mobile only).

**Request:**

```json
{
    "name": "string (optional, max:255)",
    "mobile": "string (optional, nullable, max:20)"
}
```

**Response 200:** Updated UserResource.

---

## GET /me/customer

**Authenticated (client users only).** Returns the full customer record associated with the authenticated user,
including wallet, locations, and ports.

**Response 200:** CustomerResource with `wallet`, `locations`, and `ports` loaded.  
**Errors:** 403 for platform users.

---

---

# USERS

## GET /users

**Authenticated (user.view).** Lists users. Platform users see platform users by default; client users see only their
own org users (TenantScope enforced).

**Allowed Filters:** `filter[role_id]`, `filter[customer_id]` (IL only), `filter[status]`, `filter[search]` (name,
email, mobile)  
**Allowed Sorts:** `name`, `email`, `created_at`, `last_login_at`  
**Allowed Includes:** `role`, `customer`  
**Default Sort:** `-created_at`  
**Default per_page:** 20

**Response 200:** Paginated UserResource[]

---

## POST /users

**Authenticated (user.create).** Creates a new user. Platform users must supply `customer_id` (or omit for platform
user). Client users create users in their own org. Sends invitation email with generated credentials.

**Request:**

```json
{
    "customer_id": "integer (required for platform users, nullable otherwise)",
    "name": "string (required, max:255)",
    "email": "string (required, email, unique)",
    "mobile": "string (optional, nullable, max:20)",
    "password": "string (required, min:8, confirmed)",
    "password_confirmation": "string (required)",
    "role_id": "integer (required, exists in roles)"
}
```

**Response 201:** UserResource

---

## GET /users/{user}

**Authenticated (user.view).** Returns a single user with `role.permissions`.  
Platform users can only view platform users; client users can only view users in their org.

**Response 200:** UserResource with `role.permissions`

---

## PUT/PATCH /users/{user}

**Authenticated (user.update).** Updates a user's name, email, or mobile.

**Request:**

```json
{
    "name": "string (optional)",
    "email": "string (optional, email, unique excluding self)",
    "mobile": "string (optional, nullable, max:20)"
}
```

**Response 200:** Updated UserResource

---

## DELETE /users/{user}

**Authenticated (user.delete).** Deletes a user. Cannot delete yourself. Revokes all their tokens before deleting.

**Response 200:**

```json
{
    "message": "User deleted."
}
```

---

## PATCH /users/{user}/toggle-active

**Authenticated (user.update).** Toggles user between `active` and `inactive` status.

**Response 200:** Updated UserResource

---

---

# CUSTOMERS

## GET /customers

**Authenticated (customer.view).** Lists all customers. Client users only see their own company record (TenantScope).

**Allowed Filters:** `filter[onboarding_status]`, `filter[is_active]`, `filter[search]` (company_name, email,
iec_number)  
**Allowed Sorts:** `company_name`, `created_at`, `onboarding_status`  
**Allowed Includes:** `approvedBy`, `wallet`  
**Default Sort:** `-created_at`  
**Default per_page:** 20

**Response 200:** Paginated CustomerResource[]

---

## POST /customers

**Authenticated (customer.create, IL only).** Creates a new customer and auto-creates a `customer_admin` user with an
invite email.

**Request:**

```json
{
    "first_name": "string (required, max:100)",
    "last_name": "string (required, max:100)",
    "company_name": "string (required, max:255)",
    "email": "string (required, email, unique)",
    "mobile": "string (required, max:20)"
}
```

**Response 201:** CustomerResource

---

## GET /customers/{customer}

**Authenticated (customer.view).** Returns a single customer. Client users can only view their own customer.

**Response 200:** CustomerResource with `approvedBy` and `wallet` loaded.

CustomerResource:

```json
{
    "id": 7,
    "first_name": "Rajesh",
    "last_name": "Sharma",
    "company_name": "Sharma Exports Pvt Ltd",
    "email": "rajesh@company.com",
    "mobile": "9876543210",
    "company_type": "pvt_ltd",
    "industry_type": "Textiles",
    "onboarding_status": "completed",
    "is_active": true,
    "iec_number": "IEC1234567",
    "gst_number": "29ABCDE1234F1Z5",
    "pan_number": "ABCDE1234F",
    "cin_number": null,
    "tin_number": null,
    "cha_number": null,
    "billing_address": "123, MG Road",
    "billing_landmark": "Near City Mall",
    "billing_city": "Mumbai",
    "billing_state": "Maharashtra",
    "billing_pincode": "400001",
    "billing_country": "India",
    "primary_contact_name": "Rajesh Sharma",
    "primary_contact_email": "rajesh@company.com",
    "primary_contact_mobile": "9876543210",
    "alternate_contact_name": null,
    "alternate_contact_phone": null,
    "alternate_contact_email": null,
    "il_remarks": null,
    "il_approved_at": "2025-04-01T10:00:00.000000Z",
    "approved_by": {
        "id": 1,
        "name": "Platform Admin"
    },
    "wallet": {
        "costing_type": "credit",
        "cost_balance": "50000.00",
        "credit_used": "15000.00",
        "credit_capping": "200000.00"
    },
    "created_at": "2025-03-25T08:00:00.000000Z",
    "updated_at": "2025-04-01T10:00:00.000000Z"
}
```

---

## PUT/PATCH /customers/{customer}

**Authenticated (customer.update).** Updates customer fields. Platform users can update any customer; client users can
only update their own.

**Request (all fields optional with `sometimes`):**

```json
{
    "first_name": "string (max:100)",
    "last_name": "string (max:100)",
    "company_name": "string (max:255)",
    "email": "string (email, unique excluding self)",
    "mobile": "string (max:20)",
    "iec_number": "string (max:20, unique excluding self)",
    "company_type": "pvt_ltd|llp|proprietorship|partnership|public_ltd",
    "industry_type": "string (nullable, max:100)",
    "gst_number": "string (nullable, max:20)",
    "pan_number": "string (nullable, max:20)",
    "cin_number": "string (nullable, max:25)",
    "tin_number": "string (nullable, max:30)",
    "cha_number": "string (nullable, max:30)",
    "billing_address": "string (nullable)",
    "billing_landmark": "string (nullable, max:255)",
    "billing_city": "string (nullable, max:100)",
    "billing_state": "string (nullable, max:100)",
    "billing_pincode": "string (nullable, max:10)",
    "billing_country": "string (nullable, max:100)",
    "primary_contact_name": "string (nullable, max:255)",
    "primary_contact_email": "email (nullable)",
    "primary_contact_mobile": "string (nullable, max:20)",
    "alternate_contact_name": "string (nullable, max:255)",
    "alternate_contact_phone": "string (nullable, max:20)",
    "alternate_contact_email": "email (nullable)"
}
```

**Response 200:** Updated CustomerResource

---

## POST /customers/{customer}/approve

**Authenticated (customer.approve, IL only).** Approves customer onboarding. Dispatches `SepioOnboardCustomerJob`.

**Request:**

```json
{
    "remarks": "string (optional, nullable, max:2000)"
}
```

**Response 200:** Updated CustomerResource with `onboarding_status: il_approved`

---

## POST /customers/{customer}/reject

**Authenticated (customer.reject, IL only).** Rejects customer onboarding. Remarks are mandatory.

**Request:**

```json
{
    "remarks": "string (required, max:2000)"
}
```

**Response 200:** Updated CustomerResource with `onboarding_status: il_rejected`

---

## POST /customers/{customer}/park

**Authenticated (customer.park, IL only).** Parks customer onboarding (pending additional info).

**Request:**

```json
{
    "remarks": "string (optional, nullable, max:2000)"
}
```

**Response 200:** Updated CustomerResource with `onboarding_status: il_parked`

---

## PATCH /customers/{customer}/toggle-active

**Authenticated (customer.update, IL only).** Toggles customer `is_active`. When deactivating, immediately revokes all
active tokens for the customer's users.

**Response 200:** Updated CustomerResource

---

## GET /customers/{customer}/documents

**Authenticated (customer.view).** Returns all KYC documents for a customer.

**Response 200:** CustomerDocumentResource[]

CustomerDocumentResource:

```json
{
    "id": 1,
    "doc_type": "gst_cert",
    "doc_number": "29ABCDE1234F1Z5",
    "file_name": "gst_certificate.pdf",
    "url": "https://s3.../signed-url?expires=...",
    "sepio_file_name": "GST_123456.pdf",
    "uploaded_by": {
        "id": 5,
        "name": "Rajesh Sharma"
    },
    "created_at": "2025-03-25T08:00:00.000000Z"
}
```

---

## GET /customers/{customer}/seals

**Authenticated (customer.view).** Returns paginated seals belonging to the customer. Includes order reference.

**Response 200:** Paginated SealResource[] (with `order` loaded, per_page: 50)

---

## GET /customers/{customer}/orders

**Authenticated (customer.view).** Returns paginated seal orders for the customer.

**Response 200:** Paginated SealOrderResource[] (latest first, per_page: 20)

---

## GET /customers/{customer}/trips

**Authenticated (customer.view).** Returns paginated trips for the customer.

**Response 200:** Paginated TripResource[] (latest first, per_page: 20)

---

---

# ONBOARDING

All onboarding routes are authenticated. Platform users must pass `customer_id` in the request body (query param for
GET). Client users are automatically scoped to their own customer. Modification routes (all except GET status) are
blocked for clients once `onboarding_status` is `submitted`, `il_approved`, or `completed` (returns 403). Platform users
can always modify.

## GET /onboarding/status

**Authenticated.** Returns the complete onboarding state for a customer.

**Query Params (platform users only):** `customer_id=7`

**Response 200:**

```json
{
    "onboarding_status": "pending",
    "can_submit": false,
    "customer": {
        ...CustomerResource
    },
    "signatories": [
        {
            "id": 1,
            "name": "Rajesh Sharma",
            "designation": "Director",
            "id_proof_url": "https://s3.../signed-url?expires=...",
            "created_at": "2025-03-25T09:00:00.000000Z"
        }
    ],
    "documents": [
        ...CustomerDocumentResource
        []
    ],
    "ports": [
        ...CustomerPort
        []
    ],
    "checklist": {
        "profile_complete": false,
        "has_signatories": true,
        "required_docs": [
            "gst_cert",
            "pan_card",
            "iec_cert",
            "certificate_of_registration",
            "self_stuffing_cert"
        ],
        "uploaded_doc_types": [
            "gst_cert",
            "pan_card"
        ],
        "has_ports": true
    }
}
```

`can_submit` is `true` only when all checklist items are satisfied.

---

## POST /onboarding/profile

## PUT /onboarding/profile

**Authenticated.** Saves/updates the customer's company profile. Automatically creates or updates a `CustomerLocation`
from the billing address for later Sepio sync.

**Request:**

```json
{
    "first_name": "string (required, max:100)",
    "last_name": "string (required, max:100)",
    "mobile": "string (optional, 10 digits)",
    "email": "string (optional, email, unique excluding self)",
    "company_name": "string (required, max:255)",
    "company_type": "pvt_ltd|llp|proprietorship|partnership|public_ltd (required)",
    "iec_number": "string (optional, format: IEC followed by 7 digits, unique)",
    "industry_type": "string (optional, nullable, max:100)",
    "gst_number": "string (optional, nullable, 15-char GST format)",
    "pan_number": "string (optional, nullable, PAN format: ABCDE1234F)",
    "cin_number": "string (optional, nullable, max:25)",
    "tin_number": "string (optional, nullable, max:30)",
    "cha_number": "string (optional, nullable, max:30)",
    "billing_address": "string (required)",
    "billing_city": "string (required, max:100)",
    "billing_state": "string (required, max:100)",
    "billing_country": "string (optional, nullable, max:100)",
    "billing_pincode": "string (optional, nullable, 6 digits)",
    "billing_landmark": "string (optional, nullable, max:255)",
    "primary_contact_name": "string (required, max:255)",
    "primary_contact_email": "string (required, email)",
    "primary_contact_mobile": "string (optional, nullable, 10 digits)",
    "alternate_contact_name": "string (optional, nullable, max:255)",
    "alternate_contact_phone": "string (optional, nullable, max:20)",
    "alternate_contact_email": "string (optional, nullable, email)"
}
```

**Response 200:** CustomerResource

---

## POST /onboarding/signatories

**Authenticated.** Adds an authorized signatory. Accepts `multipart/form-data`.

**Request (form-data):**

```
name:        string (required, max:255)
designation: string (optional, nullable, max:100)
id_proof:    file (optional, mimes: pdf/jpg/jpeg/png, max:5120 KB)
```

**Response 201:** AuthorizedSignatoryResource

```json
{
    "id": 1,
    "name": "Rajesh Sharma",
    "designation": "Director",
    "id_proof_url": "https://s3.../signed-url?expires=...",
    "created_at": "2025-03-25T09:00:00.000000Z"
}
```

---

## PUT /onboarding/signatories/{signatory}

**Authenticated.** Updates an authorized signatory. Ownership enforced (client users can only update their own
signatories). Accepts `multipart/form-data`.

**Request (form-data):** Same as POST. Replaces existing `id_proof` if a new file is provided.

**Response 200:** AuthorizedSignatoryResource

---

## DELETE /onboarding/signatories/{signatory}

**Authenticated.** Removes an authorized signatory and deletes the stored `id_proof` file.

**Response 200:**

```json
{
    "message": "Signatory removed."
}
```

---

## POST /onboarding/documents

**Authenticated.** Uploads a KYC document. Accepts `multipart/form-data`. If the customer is already registered on
Sepio (`sepio_company_id` set), dispatches `SepioUploadDocumentJob` automatically.

**Request (form-data):**

```
doc_type:   gst_cert|pan_card|iec_cert|certificate_of_registration|self_stuffing_cert|cha_auth_letter|tin|supporting (required)
doc_number: string (optional, nullable, max:100)
file:       file (required, mimes: pdf/jpg/jpeg/png, max:10240 KB)
```

**Response 201:** CustomerDocumentResource

---

## DELETE /onboarding/documents/{document}

**Authenticated.** Deletes a KYC document and removes the stored file. Ownership enforced.

**Response 200:**

```json
{
    "message": "Document removed."
}
```

---

## POST /onboarding/ports

**Authenticated.** Selects the customer's operating ports. Replaces all existing port selections in a transaction.

**Request:**

```json
{
    "port_ids": [
        1,
        2,
        3
    ]
}
```

`port_ids`: array (required, min:1), each must exist in `ports` table.

**Response 200:**

```json
{
    "message": "Ports saved."
}
```

---

## POST /onboarding/submit

**Authenticated.** Submits the onboarding application. Validates all checklist items server-side before accepting. Sets
`onboarding_status: submitted`.

**Business Rules:**

- All required profile fields must be present
- At least 1 signatory required
- Required documents: `gst_cert`, `pan_card`, `iec_cert` (minimum validated at submission)
- Cannot be re-submitted once `submitted` or later

**Response 200:**

```json
{
    "message": "Onboarding submitted successfully.",
    "customer": {
        ...CustomerResource
    }
}
```

**Errors:** 422 with `errors` array listing missing fields/documents.

---

---

# PORTS (Master)

## GET /ports

**Authenticated (port.view).** Lists all master ports (seeded from Sepio API).

**Allowed Filters:** `filter[port_category]` (port|icd|cfs), `filter[is_active]`, `filter[country]`, `filter[search]` (
name, code, city)  
**Allowed Sorts:** `name`, `code`, `port_category`, `created_at`  
**Default Sort:** `name`  
**Default per_page:** 50

**Response 200:** Paginated PortResource[]

PortResource:

```json
{
    "id": 1,
    "name": "Nhava Sheva",
    "code": "INNSA1",
    "city": "Mumbai",
    "country": "India",
    "port_category": "port",
    "sepio_id": 12345,
    "lat": "18.9500000",
    "lng": "72.9400000",
    "geo_fence_radius": 2000,
    "is_active": true,
    "created_at": "2025-04-01T02:00:00.000000Z"
}
```

---

## POST /ports

**Authenticated (port.manage, IL only).** Creates a new master port.

**Request:**

```json
{
    "name": "string (required, max:255)",
    "code": "string (required, max:20, unique)",
    "city": "string (optional, nullable, max:100)",
    "country": "string (optional, nullable, max:100)",
    "port_category": "port|icd|cfs (required)",
    "sepio_id": "integer (optional, nullable)",
    "lat": "decimal (optional, nullable, -90 to 90)",
    "lng": "decimal (optional, nullable, -180 to 180)",
    "geo_fence_radius": "integer (optional, nullable, min:100, in meters)"
}
```

**Response 201:** PortResource

---

## GET /ports/{port}

**Authenticated (port.view).** Returns a single port.

**Response 200:** PortResource

---

## PUT/PATCH /ports/{port}

**Authenticated (port.manage, IL only).** Updates a port. All fields optional.

**Response 200:** Updated PortResource

---

## PATCH /ports/{port}/toggle-active

**Authenticated (port.manage, IL only).** Toggles port `is_active`.

**Response 200:** Updated PortResource

---

---

# CUSTOMER PORTS

Customer-specific port selections. Each customer links master ports to their account. Provides custom geo-fence
coordinates per customer.

## GET /customer-ports

**Authenticated (onboarded).** Lists the authenticated customer's port selections.

**Allowed Filters:** `filter[port_category]`, `filter[is_active]`, `filter[search]` (name, code),
`filter[customer_id]` (IL only)
**Allowed Sorts:** `name`, `code`, `port_category`, `created_at`  
**Allowed Includes:** `port`, `customer`
**Default Sort:** `name`  
**Default per_page:** 50

**Response 200:** Paginated CustomerPortResource[]

CustomerPortResource:

```json
{
    "id": 1,
    "port_id": 5,
    "port_category": "port",
    "name": "Nhava Sheva",
    "code": "INNSA1",
    "lat": "18.9500000",
    "lng": "72.9400000",
    "geo_fence_radius": 3000,
    "is_active": true,
    "port": {
        ...PortResource
        (when
        included)
    },
    "customer": {
        "id": 2,
        "company_name": "Verma Logistics"
    },
    "created_at": "2025-03-25T10:00:00.000000Z"
}
```

---

## POST /customer-ports

**Authenticated (onboarded).** Adds a port to the customer's account. Copies name, code, and category from the master
port.

**Request:**

```json
{
    "customer_id": "integer (required for platform users, resolved from auth for clients)",
    "port_id": "integer (required, must exist and be active, unique per customer)",
    "lat": "decimal (optional, nullable, -90 to 90)",
    "lng": "decimal (optional, nullable, -180 to 180)",
    "geo_fence_radius": "integer (optional, nullable, min:100, in meters)"
}
```

**Response 201:** CustomerPortResource  
**Errors:** 422: `port_id → This port has already been added to your account.`

---

## GET /customer-ports/{customerPort}

**Authenticated (onboarded).** Returns a single customer port. Ownership enforced.

**Response 200:** CustomerPortResource with `port` loaded.

---

## PUT/PATCH /customer-ports/{customerPort}

**Authenticated (onboarded).** Updates custom lat/lng/geo_fence_radius or is_active for a customer port.

**Request:**

```json
{
    "lat": "decimal (optional, nullable)",
    "lng": "decimal (optional, nullable)",
    "geo_fence_radius": "integer (optional, min:100)",
    "is_active": "boolean (optional)"
}
```

**Response 200:** Updated CustomerPortResource

---

## PATCH /customer-ports/{customerPort}/toggle-active

**Authenticated (onboarded).** Toggles port active status for this customer.

**Response 200:** Updated CustomerPortResource

---

---

# LOCATIONS

Customer-specific delivery/billing/shipping locations used in seal orders and trips.

## GET /locations

**Authenticated (location.view, onboarded).** Lists locations. Client users are automatically scoped to their own
customer.

**Allowed Filters:** `filter[is_active]`, `filter[search]` (name, city, state), `filter[company_id]` (IL only)
**Allowed Sorts:** `name`, `city`, `created_at`  
**Allowed Includes:** `createdBy`, `customer`  
**Default Sort:** `name`  
**Default per_page:** 30

**Response 200:** Paginated CustomerLocationResource[]

CustomerLocationResource:

```json
{
    "id": 1,
    "name": "Mumbai Head Office",
    "gst_number": "27ABCDE1234F1Z5",
    "address": "123, MG Road",
    "landmark": "Near City Mall",
    "city": "Mumbai",
    "state": "Maharashtra",
    "pincode": "400001",
    "country": "India",
    "contact_person": "Suresh Kumar",
    "contact_number": "9876543210",
    "lat": "18.9500000",
    "lng": "72.8400000",
    "is_active": true,
    "sepio_billing_address_id": "BILL123",
    "sepio_shipping_address_id": "SHIP456",
    "created_by": {
        "id": 2,
        "name": "Platform Admin"
    },
    "customer": {
        "id": 2,
        "company_name": "Verma Logistics"
    },
    "created_at": "2025-03-25T10:00:00.000000Z",
    "updated_at": "2025-03-25T10:00:00.000000Z"
}
```

`sepio_billing_address_id` and `sepio_shipping_address_id` are populated asynchronously after Sepio sync. If null,
location cannot yet be used in seal orders.

---

## POST /locations

**Authenticated (location.create, onboarded).** Creates a new location. Platform users must supply `customer_id`.
Dispatches `SepioSyncLocationJob` if the customer is registered on Sepio.

**Request:**

```json
{
    "customer_id": "integer (required for platform users, nullable for clients)",
    "name": "string (required, max:255)",
    "gst_number": "string (required, max:20)",
    "address": "string (optional, nullable)",
    "landmark": "string (optional, nullable, max:255)",
    "city": "string (optional, nullable, max:100)",
    "state": "string (optional, nullable, max:100)",
    "pincode": "string (optional, nullable, max:10)",
    "country": "string (optional, nullable, max:100)",
    "contact_person": "string (optional, nullable, max:255)",
    "contact_number": "string (optional, nullable, max:20)",
    "lat": "decimal (optional, nullable, -90 to 90)",
    "lng": "decimal (optional, nullable, -180 to 180)"
}
```

**Response 201:** CustomerLocationResource

---

## GET /locations/{location}

**Authenticated (location.view, onboarded).** Returns a single location with `createdBy`.

**Response 200:** CustomerLocationResource

---

## PUT/PATCH /locations/{location}

**Authenticated (location.update, onboarded).** Updates a location. Dispatches `SepioSyncLocationJob` on save.

**Request:** Same fields as POST, all optional.

**Response 200:** Updated CustomerLocationResource

---

## DELETE /locations/{location}

**Authenticated (location.delete, onboarded).** Deletes a location.

**Business Rules:** Cannot delete if used in any active route.

**Response 200:**

```json
{
    "message": "Location deleted."
}
```

**Errors:** 422: `Location is used in one or more active routes and cannot be deleted.`

---

## PATCH /locations/{location}/toggle-active

**Authenticated (location.update, onboarded).** Toggles location `is_active`.

**Response 200:** Updated CustomerLocationResource

---

---

# ROUTES

Trip templates that store common dispatch/delivery/port configurations for quick trip creation.

## GET /routes

**Authenticated (route.view, onboarded).** Lists routes. TenantScope applied for client users.

**Allowed Filters:** `filter[customer_id]` (IL only), `filter[trip_type]`, `filter[transport_mode]`,
`filter[is_active]`, `filter[search]` (name)  
**Allowed Sorts:** `name`, `trip_type`, `transport_mode`, `created_at`  
**Allowed Includes:** `customer`  
**Default Sort:** `name`  
**Default per_page:** 30

**Response 200:** Paginated CustomerRouteResource[]

CustomerRouteResource:

```json
{
    "id": 1,
    "name": "Mumbai → JNCH",
    "trip_type": "export",
    "transport_mode": "multimodal",
    "is_active": true,
    "dispatch": {
        "location_name": "Factory Gate",
        "address": "Plot 12, Industrial Area",
        "city": "Pune",
        "state": "Maharashtra",
        "pincode": "411001",
        "country": "India",
        "lat": "18.5204",
        "lng": "73.8567"
    },
    "delivery": {
        "location_name": null,
        "address": null,
        "city": null,
        "state": null,
        "pincode": null,
        "country": null,
        "lat": null,
        "lng": null
    },
    "origin_port": {
        "name": "Nhava Sheva",
        "code": "INNSA1",
        "category": "port"
    },
    "destination_port": {
        "name": "Colombo",
        "code": "LKCMB",
        "category": "port"
    },
    "customer": {
        "id": 2,
        "company_name": "Verma Logistics"
    },
    "created_at": "2025-03-25T10:00:00.000000Z",
    "updated_at": "2025-03-25T10:00:00.000000Z"
}
```

---

## POST /routes

**Authenticated (route.create, onboarded).** Creates a route. Name is auto-generated from city/port codes if not
provided. Routes are also auto-created when a new trip is started.

**Request:**

```json
{
    "customer_id": "integer (required for platform users)",
    "name": "string (optional, nullable, max:255)",
    "trip_type": "import|export|domestic (required)",
    "transport_mode": "road|sea|multimodal (required)",
    "dispatch_location_name": "string (optional, nullable, max:255)",
    "dispatch_address": "string (required for road/multimodal)",
    "dispatch_city": "string (required for road/multimodal, max:100)",
    "dispatch_state": "string (required for road/multimodal, max:100)",
    "dispatch_pincode": "string (optional, nullable, max:10)",
    "dispatch_country": "string (optional, nullable, max:100)",
    "dispatch_lat": "decimal (optional, nullable)",
    "dispatch_lng": "decimal (optional, nullable)",
    "delivery_location_name": "string (optional, nullable, max:255)",
    "delivery_address": "string (required for road/multimodal)",
    "delivery_city": "string (required for road/multimodal, max:100)",
    "delivery_state": "string (required for road/multimodal, max:100)",
    "delivery_pincode": "string (optional, nullable, max:10)",
    "delivery_country": "string (optional, nullable, max:100)",
    "delivery_lat": "decimal (optional, nullable)",
    "delivery_lng": "decimal (optional, nullable)",
    "origin_port_name": "string (required for sea/multimodal, max:255)",
    "origin_port_code": "string (required for sea/multimodal, max:20)",
    "origin_port_category": "port|icd|cfs (optional)",
    "destination_port_name": "string (required for sea/multimodal, max:255)",
    "destination_port_code": "string (required for sea/multimodal, max:20)",
    "destination_port_category": "port|icd|cfs (optional)"
}
```

**Response 201:** CustomerRouteResource

---

## GET /routes/{route}

**Authenticated (route.view, onboarded).** Returns a single route.

**Response 200:** CustomerRouteResource

---

## PUT/PATCH /routes/{route}

**Authenticated (route.update, onboarded).** Updates a route. All fields optional.

**Response 200:** Updated CustomerRouteResource

---

## DELETE /routes/{route}

**Authenticated (route.delete, onboarded).**

**Response 200:**

```json
{
    "message": "Route deleted."
}
```

---

## PATCH /routes/{route}/toggle-active

**Authenticated (route.update, onboarded).** Toggles route `is_active`.

**Response 200:** Updated CustomerRouteResource

---

---

# WALLET

One wallet per customer. Managed by IL; readable by the customer.

## GET /customers/{customer}/wallet

**Authenticated (wallet.view).** Returns the customer's wallet with pricing tiers.

**Response 200:** WalletResource

WalletResource:

```json
{
    "id": 1,
    "il_policy_number": "POL-2025-001",
    "il_policy_expiry": "2026-03-31",
    "sum_insured": "5000000.00",
    "gwp": "250000.00",
    "costing_type": "credit",
    "credit_period": 30,
    "credit_capping": "200000.00",
    "credit_used": "45000.00",
    "freight_rate_per_seal": "25.00",
    "cost_balance": "75000.00",
    "pricing_tiers": [
        {
            "id": 1,
            "min_quantity": 20,
            "max_quantity": 100,
            "price_per_seal": "320.00",
            "is_active": true
        },
        {
            "id": 2,
            "min_quantity": 101,
            "max_quantity": null,
            "price_per_seal": "300.00",
            "is_active": true
        }
    ],
    "updated_at": "2025-04-01T10:00:00.000000Z"
}
```

**Errors:** 404: `Wallet not configured for this customer.`

---

## POST /customers/{customer}/wallet

**Authenticated (wallet.manage, IL only).** Creates a wallet for a customer. Only one wallet per customer allowed.

**Request:**

```json
{
    "il_policy_number": "string (optional, nullable, max:100)",
    "il_policy_expiry": "date (optional, nullable)",
    "sum_insured": "decimal (optional, nullable, min:0)",
    "gwp": "decimal (optional, nullable, min:0)",
    "costing_type": "cash|credit (required)",
    "credit_period": "integer (optional, nullable, min:1, required_if: costing_type=credit)",
    "credit_capping": "decimal (optional, nullable, min:0)",
    "freight_rate_per_seal": "decimal (optional, nullable, min:0)"
}
```

**Response 201:** WalletResource  
**Errors:** 422: `Wallet already exists for this customer.`

---

## PUT /customers/{customer}/wallet

**Authenticated (wallet.manage, IL only).** Updates the customer's wallet. All fields optional.

**Response 200:** Updated WalletResource

---

## POST /customers/{customer}/wallet/top-up

**Authenticated (wallet.manage, IL only).** Credits the wallet's `cost_balance`. Records a `credit` transaction.

**Request:**

```json
{
    "amount": "decimal (required, min:0.01)",
    "remarks": "string (optional, nullable, max:500)"
}
```

**Response 200:** Updated WalletResource

---

## GET /customers/{customer}/wallet/transactions

**Authenticated (wallet.view).** Returns paginated wallet transactions (credits and debits).

**Response 200:** Paginated list of:

```json
{
    "id": 1,
    "type": "credit",
    "amount": "50000.00",
    "reference_type": "manual",
    "reference_id": null,
    "balance_after": "75000.00",
    "created_at": "2025-04-01T10:00:00.000000Z"
}
```

---

---

# SEAL PRICING

Pricing tiers define unit prices per seal based on quantity ranges. Managed by IL; readable by customers.

## GET /customers/{customer}/pricing

## GET /pricing

**Authenticated (pricing.view, onboarded).** Returns active pricing tiers for the customer. Client users use`/pricing` (
auto-scoped to their own customer).

**Response 200:**

```json
[
    {
        "id": 1,
        "min_quantity": 20,
        "max_quantity": 100,
        "price_per_seal": "320.00"
    },
    {
        "id": 2,
        "min_quantity": 101,
        "max_quantity": null,
        "price_per_seal": "300.00"
    }
]
```

---

## POST /customers/{customer}/pricing

**Authenticated (pricing.manage, IL only).** Replaces all pricing tiers for a customer in a single call. All existing
tiers are deleted and replaced.

**Business Rules:** Ranges must not overlap. Only the last tier may have a null `max_quantity`.

**Request:**

```json
{
    "tiers": [
        {
            "min_quantity": 20,
            "max_quantity": 100,
            "price_per_seal": 320.00
        },
        {
            "min_quantity": 101,
            "max_quantity": null,
            "price_per_seal": 300.00
        }
    ]
}
```

**Response 200:** Updated tier list (same format as GET)  
**Errors:** 422 if ranges overlap or non-last tier has null max.

---

## POST /pricing/calculate

**Authenticated (onboarded).** Calculates order cost preview without placing an order.

**Request:**

```json
{
    "customer_id": "integer (required for platform users)",
    "quantity": "integer (required, min:20)"
}
```

**Response 200:**

```json
{
    "quantity": 150,
    "unit_price": "300.00",
    "seal_cost": "45000.00",
    "freight_amount": "3750.00",
    "gst_amount": "8775.00",
    "total_amount": "57525.00"
}
```

**Errors:** 422: `No pricing tier found for this quantity.` / `Wallet not configured.`

---

---

# SEAL ORDERS

## GET /orders

**Authenticated (seal_order.view, onboarded).** Lists orders. TenantScope applied for client users.

**Allowed Filters:** `filter[customer_id]` (IL only), `filter[status]`, `filter[payment_type]`, `filter[search]` (
order_ref, sepio_order_id)  
**Allowed Sorts:** `ordered_at`, `total_amount`, `status`  
**Allowed Includes:** `customer`, `billingLocation`, `shippingLocation`, `orderedBy`, `ilApprovedBy`  
**Default Sort:** `-ordered_at`  
**Default per_page:** 20

**Response 200:** Paginated SealOrderResource[]

SealOrderResource:

```json
{
    "id": 1,
    "order_ref": "IL0000001",
    "status": "completed",
    "quantity": 100,
    "unit_price": "320.00",
    "seal_cost": "32000.00",
    "freight_amount": "2500.00",
    "gst_amount": "6210.00",
    "total_amount": "40710.00",
    "payment_type": "credit",
    "receiver_name": "Warehouse Manager",
    "receiver_contact": "9876543210",
    "il_remarks": null,
    "il_approved_at": "2025-04-02T09:00:00.000000Z",
    "sepio_order_id": "ORD-SEPIO-12345",
    "courier_name": "Blue Dart",
    "courier_docket_number": "BD123456789",
    "seals_dispatched_at": "2025-04-03T14:00:00.000000Z",
    "seals_delivered_at": "2025-04-05T10:00:00.000000Z",
    "billing_location": {
        ...CustomerLocationResource
    },
    "shipping_location": {
        ...CustomerLocationResource
    },
    "ordered_by": {
        "id": 5,
        "name": "Rajesh Sharma"
    },
    "il_approved_by": {
        "id": 1,
        "name": "Platform Admin"
    },
    "customer": {
        "id": 2,
        "company_name": "Verma Logistics"
    },
    "ordered_at": "2025-04-01T15:00:00.000000Z",
    "updated_at": "2025-04-05T10:00:00.000000Z"
}
```

---

## POST /orders

**Authenticated (seal_order.create, onboarded).** Places a new seal order.

**Business Rules:**

- `quantity` must be covered by an active pricing tier (min: 20)
- `billing_location_id` and `shipping_location_id` must have Sepio address IDs (i.e. already synced)
- All `port_ids` must belong to the customer
- `advance_balance`: wallet balance must cover total; debited immediately
- `credit`: costing_type must be credit; credit_used + total must not exceed credit_capping
- Order ref auto-generated: `IL` + 7-digit padded number

**Request:**

```json
{
    "customer_id": "integer (required for platform users)",
    "quantity": "integer (required, min:20)",
    "payment_type": "cash|credit|advance_balance (required)",
    "billing_location_id": "integer (required, exists in customer_locations)",
    "shipping_location_id": "integer (required, exists in customer_locations)",
    "receiver_name": "string (optional, nullable, max:255)",
    "receiver_contact": "string (optional, nullable, max:20)",
    "port_ids": [
        1,
        2
    ]
}
```

`port_ids`: array (required, min:1) — IDs from `customer_ports` table (not master ports).

**Response 201:** SealOrderResource with `billingLocation`, `shippingLocation`, `orderedBy`

**Errors:**

- 422: `Customer wallet has not been configured yet.`
- 422: `No active pricing tier found for the requested quantity.`
- 422: `Insufficient advance balance. Required: ₹X, Available: ₹Y.`
- 422: `Credit limit exceeded.`
- 422: `One or more selected ports are invalid.`

---

## GET /orders/{order}

**Authenticated (seal_order.view, onboarded).** Returns a single order with all relations.

**Response 200:** SealOrderResource with all relations loaded.

---

## POST /orders/{order}/approve

**Authenticated (seal_order.approve, IL only).** Approves a pending or parked order. Dispatches `SepioPlaceOrderJob`.

**Request:**

```json
{
    "remarks": "string (optional, nullable, max:2000)",
    "remarks_file": "file (optional, mimes: pdf/jpg/jpeg/png, max:5120 KB)"
}
```

**Response 200:** Updated SealOrderResource  
**Errors:** 422: `Only pending or parked orders can be approved.`

---

## POST /orders/{order}/reject

**Authenticated (seal_order.reject, IL only).** Rejects a pending or parked order. Remarks mandatory. Auto-refunds
advance_balance payment.

**Request:**

```json
{
    "remarks": "string (required, max:2000)",
    "remarks_file": "file (optional, mimes: pdf/jpg/jpeg/png, max:5120 KB)"
}
```

**Response 200:** Updated SealOrderResource  
**Errors:** 422: `Only pending or parked orders can be rejected.`

---

## POST /orders/{order}/park

**Authenticated (seal_order.park, IL only).** Parks a pending order (awaiting additional info).

**Request:**

```json
{
    "remarks": "string (optional, nullable, max:2000)"
}
```

**Response 200:** Updated SealOrderResource  
**Errors:** 422: `Only pending orders can be parked.`

---

## POST /orders/{order}/seals

**Authenticated (seal_order.approve, IL only).** Manually ingests seal numbers after Sepio dispatches the order.
Typically done automatically by `SepioSealAllocationPollJob`.

**Request:**

```json
{
    "seal_numbers": [
        "SPPL10009259",
        "SPPL10009260"
    ],
    "dispatched_at": "date (required)"
}
```

`seal_numbers`: array (required, min:1). Each entry must be a distinct string (max:100). Count must exactly match
`order.quantity`.

**Response 200:**

```json
{
    "message": "100 seals ingested successfully.",
    "order_ref": "IL0000001"
}
```

**Errors:** 422: `Seal count (X) does not match order quantity (Y).`

---

---

# SEALS

## GET /seals

**Authenticated (seal.view, onboarded).** Lists seals. TenantScope applied for client users.

**Allowed Filters:** `filter[customer_id]` (IL only), `filter[status]`, `filter[sepio_status]`, `filter[seal_order_id]`,
`filter[trip_id]`,
`filter[search]` (seal_number)  
**Allowed Sorts:** `seal_number`, `status`, `last_scan_at`, `created_at`  
**Allowed Includes:** `customer`, `order`, `trip`  
**Default Sort:** `-created_at`  
**Default per_page:** 50

**Response 200:** Paginated SealResource[]

SealResource:

```json
{
    "id": 1,
    "seal_number": "SPPL10009259",
    "status": "in_inventory",
    "sepio_status": "unknown",
    "last_scan_at": null,
    "delivered_at": null,
    "order": {
        "id": 1,
        "order_ref": "IL0000001"
    },
    "customer": {
        "id": 2,
        "company_name": "Verma Logistics"
    },
    "trip": null,
    "created_at": "2025-04-05T10:00:00.000000Z",
    "updated_at": "2025-04-05T10:00:00.000000Z"
}
```

---

## GET /seals/{seal}

**Authenticated (seal.view, onboarded).** Returns a single seal with order and trip.

**Response 200:** SealResource with `order` and `trip` loaded.

---

## GET /seals/{seal}/check-availability

**Authenticated (seal.view, onboarded).** Checks if a seal is available locally and verifies with Sepio API.

**Business Rules:**

- Returns immediately if seal is not `in_inventory` (no Sepio call)
- If customer not registered on Sepio, returns local status only

**Response 200:**

```json
{
    "available": true,
    "message": "Seal available.",
    "order_id": "ORD-SEPIO-12345",
    "ports": [
        "Nhava Sheva (INNSA1)"
    ],
    "icds": [
        "Pune ICD (INPNQ1)"
    ],
    "iec": "IEC1234567"
}
```

Or when not available:

```json
{
    "available": false,
    "message": "Seal is not available (status: assigned)."
}
```

---

## GET /seals/{seal}/status-history

**Authenticated (seal.view, onboarded).** Returns paginated Sepio scan history logs for a seal.

**Response 200:** Paginated SealStatusLogResource[] (newest first, per_page: 50)

SealStatusLogResource:

```json
{
    "id": 1,
    "status": "valid",
    "scan_location": "Nhava Sheva (INNSA1)",
    "scanned_lat": "18.9500000",
    "scanned_lng": "72.9400000",
    "scanned_by": "inspector@port.com",
    "checked_at": "2025-04-10T08:00:00.000000Z"
}
```

---

---

# TRIPS

## GET /trips

**Authenticated (trip.view, onboarded).** Lists trips. TenantScope applied for client users.

**Allowed Filters:**

- `filter[customer_id]` — (IL only)
- `filter[status]` — TripStatus enum
- `filter[trip_type]` — import|export|domestic
- `filter[transport_mode]` — road|sea|multimodal
- `filter[search]` — trip_ref, container_number, vehicle_number, bill_of_lading
- `filter[dispatch_date_from]` — date (YYYY-MM-DD)
- `filter[dispatch_date_to]` — date (YYYY-MM-DD)

**Allowed Sorts:** `trip_ref`, `status`, `dispatch_date`, `created_at`  
**Allowed Includes:** `customer`, `seal`, `createdBy`, `documents`, `segments`  
**Default Sort:** `-created_at`  
**Default per_page:** 20

**Response 200:** Paginated TripResource[]

---

## POST /trips

**Authenticated (trip.create, onboarded).** Creates a new trip in `draft` status. Optionally assigns a seal.
Auto-creates a matching Route and upserts consignor/consignee records from contact details.

**Business Rules (required fields by transport_mode):**

- `road` / `multimodal`: driver fields, vehicle fields, dispatch location, delivery location
- `sea` / `multimodal`: container_number, container_type, origin_port, destination_port, carrier_scac
- All modes: cargo fields, invoice fields

**Request:**

```json
{
    "customer_id": "integer (required for platform users)",
    "trip_type": "import|export|domestic (required)",
    "transport_mode": "road|sea|multimodal (required)",
    "seal_id": "integer (required, must exist and be in_inventory)",
    "driver_name": "string (required for road/multimodal, max:255)",
    "driver_phone": "string (required for road/multimodal, max:20)",
    "driver_license": "string (optional, nullable, max:50)",
    "driver_aadhaar": "string (optional, nullable, max:20)",
    "is_driver_license_verified": "boolean (optional)",
    "is_driver_aadhaar_verified": "boolean (optional)",
    "driver_license_verification_payload": "json string (optional, nullable)",
    "driver_aadhaar_verification_payload": "json string (optional, nullable)",
    "vehicle_number": "string (required for road/multimodal, max:50)",
    "vehicle_type": "truck|trailer|container_carrier (required for road/multimodal)",
    "is_rc_verified": "boolean (optional)",
    "is_verification_done": "boolean (optional)",
    "rc_verification_payload": "json string (optional, nullable)",
    "container_number": "string (required, max:50)",
    "container_type": "string (required, max:20)",
    "cargo_type": "string (required, max:100)",
    "cargo_description": "string (required)",
    "invoice_number": "string (required, max:100)",
    "invoice_date": "date (required)",
    "eway_bill_number": "string (optional, nullable, 7-15 numeric digits)",
    "eway_bill_validity_date": "date (required)",
    "hs_code": "string (optional, nullable, max:20)",
    "gross_weight": "decimal (optional, nullable, min:0)",
    "net_weight": "decimal (optional, nullable, min:0)",
    "weight_unit": "string (optional, nullable, max:10)",
    "quantity": "integer (optional, nullable, min:1)",
    "quantity_unit": "string (optional, nullable, max:50)",
    "declared_cargo_value": "decimal (optional, nullable, min:0)",
    "dispatch_location_name": "string (optional, nullable, max:255)",
    "dispatch_address": "string (required for road/multimodal)",
    "dispatch_city": "string (required for road/multimodal, max:100)",
    "dispatch_state": "string (required for road/multimodal, max:100)",
    "dispatch_pincode": "string (required for road/multimodal, max:10)",
    "dispatch_country": "string (required for road/multimodal, max:100)",
    "dispatch_contact_person": "string (required for road/multimodal, max:255)",
    "dispatch_contact_number": "string (required for road/multimodal, max:20)",
    "dispatch_contact_email": "email (required for road/multimodal)",
    "dispatch_lat": "decimal (optional, nullable)",
    "dispatch_lng": "decimal (optional, nullable)",
    "delivery_location_name": "string (optional, nullable, max:255)",
    "delivery_address": "string (required for road/multimodal)",
    "delivery_city": "string (required for road/multimodal, max:100)",
    "delivery_state": "string (required for road/multimodal, max:100)",
    "delivery_pincode": "string (required for road/multimodal, max:10)",
    "delivery_country": "string (required for road/multimodal, max:100)",
    "delivery_contact_person": "string (required for road/multimodal, max:255)",
    "delivery_contact_number": "string (required for road/multimodal, max:20)",
    "delivery_contact_email": "email (required for road/multimodal)",
    "delivery_lat": "decimal (optional, nullable)",
    "delivery_lng": "decimal (optional, nullable)",
    "origin_port_name": "string (required for sea/multimodal, max:255)",
    "origin_port_code": "string (required for sea/multimodal, max:20)",
    "origin_port_category": "string (required for sea/multimodal, max:20)",
    "destination_port_name": "string (required for sea/multimodal, max:255)",
    "destination_port_code": "string (required for sea/multimodal, max:20)",
    "destination_port_category": "string (required for sea/multimodal, max:20)",
    "dispatch_date": "date (required)",
    "expected_delivery_date": "date (required, after_or_equal: dispatch_date)",
    "vessel_name": "string (optional, nullable, max:255)",
    "vessel_imo_number": "string (optional, nullable, max:20)",
    "voyage_number": "string (optional, nullable, max:100)",
    "bill_of_lading": "string (optional, nullable, max:100)",
    "eta": "date (optional, nullable)",
    "etd": "date (optional, nullable)",
    "carrier_scac": "string (required for sea/multimodal, 2-10 uppercase alphanumeric)",
    "segments": [
        {
            "sequence": 1,
            "source_name": "Pune Factory",
            "destination_name": "Nhava Sheva Port",
            "transport_mode": "road",
            "tracking_source": "driver_mobile",
            "notes": "Optional notes"
        }
    ]
}
```

Trip ref is auto-generated: `TR` + 7-digit padded number.

**Response 201:** TripResource with `seal`, `createdBy`, `segments`

---

## GET /trips/{trip}

**Authenticated (trip.view, onboarded).** Returns a single trip with all relations.

**Response 200:** TripResource with `seal`, `createdBy`, `documents`, `segments`, `containerTracking`,
`container_tracking`

TripResource (abbreviated):

```json
{
    "id": 1,
    "trip_ref": "TR0000001",
    "status": "in_transit",
    "trip_type": "export",
    "transport_mode": "multimodal",
    "risk_score": null,
    "driver_name": "Suresh Driver",
    "driver_license": "MH1220200012345",
    "driver_phone": "9876543210",
    "vehicle_number": "MH12AB1234",
    "vehicle_type": "truck",
    "tracking_token": "abc123...",
    "last_known_lat": "18.9500000",
    "last_known_lng": "72.8400000",
    "last_known_source": "driver_mobile",
    "last_tracked_at": "2025-04-10T08:00:00.000000Z",
    "container_number": "MSKU1234567",
    "container_type": "40HC",
    "cargo_type": "Textiles",
    "cargo_description": "Cotton fabric bales",
    "hs_code": "52081100",
    "gross_weight": "15000.000",
    "net_weight": "14500.000",
    "weight_unit": "kg",
    "quantity": 50,
    "quantity_unit": "bales",
    "declared_cargo_value": "500000.00",
    "invoice_number": "INV-2025-001",
    "invoice_date": "2025-04-01",
    "eway_bill_number": "1234567890123",
    "eway_bill_validity_date": "2025-04-15",
    "dispatch": {
        "location_name": "Pune Factory",
        "address": "Plot 12, Industrial Area",
        "city": "Pune",
        "state": "Maharashtra",
        "pincode": "411001",
        "country": "India",
        "contact_person": "Ramesh",
        "contact_number": "9876543211",
        "contact_email": "ramesh@factory.com",
        "lat": "18.5204",
        "lng": "73.8567"
    },
    "delivery": {
        "location_name": "Nhava Sheva Port",
        "address": "Gate 3, JNCH",
        "city": "Mumbai",
        "state": "Maharashtra",
        "pincode": "400707",
        "country": "India",
        "contact_person": null,
        "contact_number": null,
        "contact_email": null,
        "lat": "18.9500",
        "lng": "72.9400"
    },
    "origin_port": {
        "name": "Nhava Sheva",
        "code": "INNSA1",
        "category": "port"
    },
    "destination_port": {
        "name": "Colombo",
        "code": "LKCMB",
        "category": "port"
    },
    "vessel_name": "MV Ocean Star",
    "vessel_imo_number": "9876543",
    "voyage_number": "VY2025001",
    "bill_of_lading": "BL-MAEU-20250001",
    "carrier_scac": "MAEU",
    "customs_hold": false,
    "last_vessel_position_at": "2025-04-10T06:00:00.000000Z",
    "eta": "2025-04-20T14:00:00.000000Z",
    "etd": "2025-04-11T08:00:00.000000Z",
    "dispatch_date": "2025-04-01",
    "trip_start_time": "2025-04-01T10:00:00.000000Z",
    "expected_delivery_date": "2025-04-25",
    "actual_delivery_date": null,
    "trip_end_time": null,
    "epod_status": "pending",
    "epod_confirmed_at": null,
    "epod_confirmation_notes": null,
    "seal": {
        ...SealResource
    },
    "created_by": {
        "id": 5,
        "name": "Rajesh Sharma"
    },
    "documents": [
        ...TripDocumentResource
        []
    ],
    "segments": [
        ...TripSegmentResource
        []
    ],
    "container_tracking": {
        ...TripContainerTrackingResource
    },
    "customer": {
        "id": 2,
        "company_name": "Verma Logistics"
    },
    "created_at": "2025-04-01T09:00:00.000000Z",
    "updated_at": "2025-04-10T08:00:00.000000Z"
}
```

Note: `tracking_token` is only returned to users in the same customer org or platform users.

---

## PUT/PATCH /trips/{trip}

**Authenticated (trip.update, onboarded).** Updates trip fields. Cannot update a completed trip. Seal changes must go
through the dedicated `/seal` endpoint. Status can only be set to intermediate values: `at_port`, `on_vessel`,
`vessel_arrived`, `delivered`.

**Response 200:** TripResource with `seal`

---

## POST /trips/{trip}/start

**Authenticated (trip.update, onboarded).** Starts a trip (draft → in_transit). Must have a seal assigned. Calls Sepio
`installSeal()`. For sea/multimodal trips with container info, dispatches `RegisterContainerTrackingJob`.

**Request:**

```json
{
    "dispatch_date": "date (optional)"
}
```

**Response 200:** TripResource with `seal`

**Errors:**

- 422: `Only Draft trips can be started.`
- 422: `A seal must be assigned before starting the trip.`
- 422/502: Sepio error message if seal installation fails (trip rolled back to draft)

---

## PATCH /trips/{trip}/seal

**Authenticated (trip.update, onboarded).** Replaces the assigned seal. Only allowed in `draft` status. Releases the old
seal back to inventory and assigns the new one (with Sepio availability check).

**Request:**

```json
{
    "seal_id": "integer (required, exists in seals)"
}
```

**Response 200:** TripResource with `seal`  
**Errors:** 422: `Seal can only be changed while the trip is in Draft status.`

---

## POST /trips/{trip}/vessel-info

**Authenticated (trip.update, onboarded).** Adds or updates vessel information. Can be called at any non-completed
status.

**Request:**

```json
{
    "vessel_name": "string (required, max:255)",
    "vessel_imo_number": "string (optional, nullable, max:20)",
    "voyage_number": "string (optional, nullable, max:100)",
    "bill_of_lading": "string (optional, nullable, max:100)",
    "eta": "date (optional, nullable)",
    "etd": "date (optional, nullable)"
}
```

**Response 200:** TripResource

---

## POST /trips/{trip}/confirm-epod

**Authenticated (trip.destination_confirm, onboarded).** Confirms ePOD and completes the trip. Sets seal status to
`used`. Locks the trip.

**Request:**

```json
{
    "notes": "string (optional, nullable, max:2000)",
    "actual_delivery_date": "date (optional, nullable)"
}
```

**Response 200:** TripResource with `seal`

---

## GET /trips/{trip}/seal-status

**Authenticated (trip.view, onboarded).** Returns the current seal status and latest scan log for the trip's assigned
seal.

**Response 200:**

```json
{
    "seal_number": "SPPL10009259",
    "status": "in_transit",
    "sepio_status": "valid",
    "last_scan_at": "2025-04-10T08:00:00.000000Z",
    "latest_log": {
        "id": 5,
        "status": "valid",
        "scan_location": "Nhava Sheva (INNSA1)",
        "scanned_lat": "18.9500000",
        "scanned_lng": "72.9400000",
        "scanned_by": "port.officer@example.com",
        "checked_at": "2025-04-10T08:00:00.000000Z"
    }
}
```

**Response 404:** `{ "message": "No seal assigned to this trip." }`

---

## GET /trips/{trip}/timeline

**Authenticated (trip.view, onboarded).** Returns all trip events in chronological order.

**Response 200:**

```json
[
    {
        "id": 1,
        "trip_id": 1,
        "event_type": "trip_created",
        "previous_status": null,
        "new_status": "draft",
        "event_data": {
            "trip_ref": "TR0000001"
        },
        "actor_type": "user",
        "actor_id": 5,
        "created_at": "2025-04-01T09:00:00.000000Z"
    },
    {
        "id": 2,
        "event_type": "seal_assigned",
        "event_data": {
            "seal_number": "SPPL10009259"
        },
        "actor_type": "user",
        "actor_id": 5,
        "created_at": "2025-04-01T09:01:00.000000Z"
    },
    {
        "id": 3,
        "event_type": "status_changed",
        "previous_status": "draft",
        "new_status": "in_transit",
        "event_data": {},
        "actor_type": "user",
        "actor_id": 5,
        "created_at": "2025-04-01T10:00:00.000000Z"
    }
]
```

Event types: `trip_created`, `seal_assigned`, `status_changed`, `trip_started`, `vessel_info_added`, `epod_confirmed`

---

---

# TRIP SEGMENTS

Segments define the individual legs of a multimodal trip (e.g. road leg from factory to port, then sea leg).

## GET /trips/{trip}/segments

**Authenticated (trip.view, onboarded).** Returns all segments for a trip, ordered by sequence.

**Response 200:** TripSegmentResource[]

TripSegmentResource:

```json
{
    "id": 1,
    "sequence": 1,
    "source_name": "Pune Factory",
    "destination_name": "Nhava Sheva Port",
    "transport_mode": "road",
    "tracking_source": "driver_mobile",
    "notes": null,
    "created_at": "2025-04-01T09:00:00.000000Z"
}
```

---

## POST /trips/{trip}/segments

**Authenticated (trip.update, onboarded).** Adds a new segment. Cannot add to a completed trip.

**Request:**

```json
{
    "sequence": "integer (required, min:1)",
    "source_name": "string (required, max:255)",
    "destination_name": "string (required, max:255)",
    "transport_mode": "road|sea (required)",
    "tracking_source": "gps|tcl_tracker|e_lock|driver_mobile|driver_sim|fast_tag (required for road transport)",
    "notes": "string (optional, nullable)"
}
```

**Response 201:** TripSegmentResource  
**Errors:** 403: `Cannot modify segments of a completed trip.`

---

## PUT /trips/{trip}/segments/{segment}

**Authenticated (trip.update, onboarded).** Updates a segment. Cannot modify segments of a completed trip. Segment must
belong to the trip.

**Request:** Same fields as POST, all optional with same constraints.

**Response 200:** Updated TripSegmentResource  
**Errors:** 403: `Cannot modify segments of a completed trip.` / 404: segment not found in this trip.

---

## DELETE /trips/{trip}/segments/{segment}

**Authenticated (trip.update, onboarded).** Removes a segment. Cannot modify segments of a completed trip.

**Response 200:**

```json
{
    "message": "Segment removed."
}
```

---

---

# TRIP TRACKING

## GET /trips/{trip}/tracking

**Authenticated (trip.view, onboarded).** Returns paginated tracking history for a trip.

**Query Parameters:**
| Param | Description |
|---|---|
| `source` | Filter by tracking source enum value |
| `from` | ISO datetime string |
| `to` | ISO datetime string |
| `per_page` | Items per page (default: 100) |

**Response 200:** Paginated TripTrackingPointResource[]

TripTrackingPointResource:

```json
{
    "id": 1,
    "source": "driver_mobile",
    "lat": "18.5204000",
    "lng": "73.8567000",
    "speed": "45.50",
    "heading": 270,
    "accuracy": 10,
    "location_name": null,
    "recorded_at": "2025-04-01T10:30:00.000000Z"
}
```

---

## GET /trips/{trip}/tracking/latest

**Authenticated (trip.view, onboarded).** Returns the latest known position for a trip.

**Response 200:**

```json
{
    "last_known_lat": "18.9500000",
    "last_known_lng": "72.8400000",
    "last_known_source": "fast_tag",
    "last_tracked_at": "2025-04-10T08:00:00.000000Z",
    "latest_point": {
        ...TripTrackingPointResource
    }
}
```

---

## POST /trips/{trip}/location

**Auth: Sanctum token OR `X-Tracking-Token` header (tracking_token from trip).** Pushes a GPS location from driver's
mobile app. Only accepted for trips with status `in_transit` or `at_port`.

**Headers:**

```
X-Tracking-Token: {trip.tracking_token}
```

**Request:**

```json
{
    "lat": "decimal (required, -90 to 90)",
    "lng": "decimal (required, -180 to 180)",
    "speed": "decimal (optional, nullable, 0-300 km/h)",
    "heading": "integer (optional, nullable, 0-359 degrees)",
    "accuracy": "integer (optional, nullable, in meters)",
    "recorded_at": "datetime (optional, defaults to now)"
}
```

**Response 201/200:**

```json
{
    "message": "Location recorded.",
    "point": {
        ...TripTrackingPointResource
    }
}
```

**Errors:**

- 422: `Location updates are only accepted for active trips.`
- 401: `Invalid tracking token.`

---

## POST /tracking/driver-mobile

**Public (token-only, no Sanctum required).** Driver pushes location using only the trip's `tracking_token`. The trip is
resolved from the token. This is the preferred endpoint for driver mobile apps.

**Request:**

```json
{
    "tracking_token": "string (required if not in X-Tracking-Token header)",
    "lat": "decimal (required, -90 to 90)",
    "lng": "decimal (required, -180 to 180)",
    "speed": "decimal (optional, nullable, 0-300 km/h)",
    "heading": "integer (optional, nullable, 0-359 degrees)",
    "accuracy": "integer (optional, nullable, in meters)",
    "recorded_at": "datetime (optional, defaults to now)"
}
```

**Headers (alternative to body field):**

```
X-Tracking-Token: {tracking_token}
```

**Response 201/200:**

```json
{
    "message": "Location recorded."
}
```

**Errors:** 401: `Tracking token is required.` / `Invalid tracking token.`

---

---

# CONTAINER TRACKING (Sea / Multimodal)

## GET /trips/{trip}/milestones

**Authenticated (trip.view, onboarded).** Returns all shipment milestones for a sea/multimodal trip, ordered by
sequence.

**Response 200:** TripShipmentMilestoneResource[]

TripShipmentMilestoneResource:

```json
{
    "event_type": "load",
    "event_classifier": "actual",
    "location": {
        "name": "Nhava Sheva",
        "unlocode": "INNSA1",
        "country": "India",
        "lat": "18.9500000",
        "lng": "72.9400000",
        "terminal": "NSIGT",
        "type": "port_of_loading"
    },
    "vessel": {
        "name": "MV Ocean Star",
        "imo": "9876543",
        "voyage": "VY2025001"
    },
    "sequence_order": 1,
    "occurred_at": "2025-04-11T06:00:00.000000Z"
}
```

---

## GET /trips/{trip}/container-tracking (via GET /trips/{trip} with containerTracking)

Container tracking data is returned as part of the trip show response when loaded:

TripContainerTrackingResource (embedded in TripResource):

```json
{
    "tracking_status": "active",
    "failed_reason": null,
    "transportation_status": "IN_TRANSIT",
    "arrival_delay_days": 2,
    "initial_carrier_eta": "2025-04-18T00:00:00.000000Z",
    "has_rollover": false,
    "pol": {
        "name": "Nhava Sheva",
        "unlocode": "INNSA1"
    },
    "pod": {
        "name": "Colombo",
        "unlocode": "LKCMB"
    },
    "current_vessel": {
        "name": "MV Ocean Star",
        "imo": "9876543",
        "lat": "7.8731000",
        "lng": "79.8612000",
        "speed_knots": 18.5,
        "heading": 225,
        "geo_area": "Indian Ocean",
        "position_at": "2025-04-10T06:00:00.000000Z"
    },
    "last_synced_at": "2025-04-10T06:05:00.000000Z"
}
```

Tracking is registered automatically when a sea/multimodal trip is started. Status progresses:
`not_registered → pending → active` (via Kpler webhook). Milestones are synced whenever the shipment is updated via
webhook or polling.

---

---

# TRIP DOCUMENTS

## GET /trips/{trip}/documents

**Authenticated (trip.view, onboarded).** Returns all documents for a trip with uploader info.

**Response 200:** TripDocumentResource[]

TripDocumentResource:

```json
{
    "id": 1,
    "doc_type": "e_way_bill",
    "file_name": "eway_bill_123.pdf",
    "url": "https://s3.../signed-url?expires=...",
    "uploaded_by": {
        "id": 5,
        "name": "Rajesh Sharma"
    },
    "created_at": "2025-04-02T06:00:00.000000Z"
}
```

---

## POST /trips/{trip}/documents

**Authenticated (document.upload, onboarded).** Uploads a document to a trip. Cannot upload to a completed trip. Accepts
`multipart/form-data`.

**Request (form-data):**

```
doc_type: e_way_bill|e_invoice|e_pod|supporting (required)
file:     file (required, mimes: pdf/jpg/jpeg/png, max:10240 KB)
```

**Response 201:** TripDocumentResource with `uploadedBy`  
**Errors:** 403: `Cannot upload documents to a completed trip.`

---

## DELETE /trips/{trip}/documents/{document}

**Authenticated (document.delete, onboarded).** Deletes a document and removes the stored file. Cannot delete from a
completed trip.

**Response 200:**

```json
{
    "message": "Document deleted."
}
```

**Errors:** 403: `Cannot delete documents from a completed trip.`

---

---

# DASHBOARD & REPORTS

## GET /dashboard/stats

**Authenticated (onboarded).** Returns summary statistics. Platform users receive platform-wide counts; client users
receive tenant-scoped counts with wallet summary.

**Platform User Response 200:**

```json
{
    "customers": {
        "total": 25,
        "pending": 5,
        "submitted": 3,
        "il_approved": 2,
        "il_parked": 1,
        "completed": 14
    },
    "orders": {
        "total": 150,
        "il_pending": 8,
        "il_approved": 12,
        "in_transit": 5,
        "completed": 125
    },
    "trips": {
        "total": 500,
        "draft": 10,
        "in_transit": 45,
        "at_port": 20,
        "on_vessel": 30,
        "vessel_arrived": 15,
        "delivered": 8,
        "completed": 372
    },
    "seals": {
        "total": 15000,
        "in_inventory": 8000,
        "assigned": 600,
        "in_transit": 400,
        "used": 5900,
        "tampered": 30,
        "lost": 70
    },
    "recent_orders": [
        {
            "id": 1,
            "order_ref": "IL0000150",
            "customer_id": 7,
            "status": "completed",
            "total_amount": "40710.00",
            "ordered_at": "2025-04-10T09:00:00.000000Z",
            "customer": {
                "id": 7,
                "company_name": "Sharma Exports",
                "primary_contact_name": "Rajesh"
            }
        }
    ],
    "recent_trips": [
        {
            "id": 5,
            "seal_id": 10,
            "trip_ref": "TR0000005",
            "customer_id": 7,
            "status": "in_transit",
            "trip_type": "export",
            "dispatch_date": "2025-04-08",
            "created_at": "2025-04-08T08:00:00.000000Z",
            "seal": {
                "id": 10,
                "seal_number": "SPPL10009260",
                "status": "in_transit"
            }
        }
    ],
    "tampered_seals": [
        {
            "id": 3,
            "seal_number": "SPPL10009270",
            "customer_id": 7,
            "trip_id": 2,
            "last_scan_at": "2025-04-09T15:00:00.000000Z"
        }
    ]
}
```

**Client User Response 200:** Same structure, scoped to own customer, plus `wallet` summary and `tampered_seals` limited
to 5:

```json
{
    "orders": {
        ...
    },
    "seals": {
        ...
    },
    "trips": {
        "total": 50,
        "active": 12,
        "completed": 35,
        "draft": 3
    },
    "wallet": {
        "costing_type": "credit",
        "cost_balance": "75000.00",
        "credit_used": "45000.00",
        "credit_capping": "200000.00",
        "policy_expiry": "2026-03-31"
    },
    "recent_trips": [
        ...
    ],
    "recent_orders": [
        ...
    ],
    "tampered_seals": [
        ...
    ]
}
```

---

## GET /reports/trips

**Authenticated (trip.view, onboarded).** Paginated trip report with summary. Client users automatically scoped to own
customer.

**Query Parameters:**
| Param | Description |
|---|---|
| `customer_id` | integer (IL only) |
| `status` | TripStatus enum value |
| `trip_type` | import\|export\|domestic |
| `transport_mode` | road\|sea\|multimodal |
| `from` | date (YYYY-MM-DD) — filters on dispatch_date |
| `to` | date (YYYY-MM-DD) |

**Response 200:**

```json
{
    "summary": {
        "total": 500,
        "completed": 372,
        "active": 118,
        "tampered_seal": 8
    },
    "trips": {
        ...paginated
        TripResource[] with seal and customer }
}
```

---

## GET /reports/seals

**Authenticated (seal.view, onboarded).** Paginated seal report with summary. Client users auto-scoped.

**Query Parameters:** `customer_id`, `status` (SealStatus), `sepio_status` (SepioSealStatus), `from`, `to`

**Response 200:**

```json
{
    "summary": {
        "total": 15000,
        "in_inventory": 8000,
        "assigned": 600,
        "used": 5900,
        "tampered": 30,
        "lost": 70
    },
    "seals": {
        ...paginated
        SealResource[] with order and customer }
}
```

---

## GET /reports/orders

**Authenticated (seal_order.view, onboarded).** Paginated order report with financial summary. Client users auto-scoped.

**Query Parameters:** `customer_id`, `status` (SealOrderStatus), `payment_type`, `from`, `to` (filters on ordered_at)

**Response 200:**

```json
{
    "summary": {
        "total_orders": 150,
        "total_seals": "12500",
        "total_value": "4850000.00",
        "total_seal_cost": "4000000.00",
        "total_freight": "312500.00",
        "total_gst": "537500.00"
    },
    "orders": {
        ...paginated
        SealOrderResource[] with customer and orderedBy }
}
```

---

---

# WEBHOOKS

## POST /webhooks/container-tracking

**Public (IP-whitelisted).** Receives webhook events from Kpler (MarineTraffic container tracking API). In production,
only accepts connections from Kpler IP addresses: `3.251.15.122`, `52.215.44.244`, `54.195.123.104`.

**Supported event types:**

- `shipment_updated` — updates container tracking snapshot and dispatches milestone sync
- `tracking_request_succeeded` — marks tracking as active, links shipment ID
- `tracking_request_failed` — marks tracking as failed with reason

**Response 200:**

```json
{
    "message": "ok"
}
```

---

---

# INSPECTOR ENDPOINTS (Non-Production Only)

These endpoints are only available in non-production environments. They return 404 in production.

## Sepio Inspector — GET /sepio-inspector/customers

**Authenticated (sepio.inspect).** Returns all customers with Sepio-related fields for debugging.

## Sepio Inspector — POST /sepio-inspector/proxy

**Authenticated (sepio.inspect).** Proxies any Sepio API call for debugging.

**Request:**

```json
{
    "endpoint": "/some/sepio/path",
    "method": "post|get",
    "customer_id": 7,
    "authenticated": true,
    "payload": "{\"key\": \"value\"}"
}
```

## Sepio Inspector — POST /sepio-inspector/proxy-file

**Authenticated (sepio.inspect).** Proxies a file upload to Sepio. Accepts `multipart/form-data`.

## Sepio Inspector — POST /sepio-inspector/refresh-token

**Authenticated (sepio.inspect).** Manually refreshes the Sepio JWT for a customer.

**Request:**

```json
{
    "customer_id": 7
}
```

## MarineTraffic Inspector — POST /marine-traffic-inspector/container

**Authenticated (marinetraffic.inspect).** Proxies a request to the Kpler container tracking API.

## MarineTraffic Inspector — POST /marine-traffic-inspector/vessel

**Authenticated (marinetraffic.inspect).** Proxies a request to the MarineTraffic AIS vessel API.

## MarineTraffic Inspector — GET /marine-traffic-inspector/active-trackings

**Authenticated (marinetraffic.inspect).** Returns all active/pending container tracking records.

## MarineTraffic Inspector — GET /marine-traffic-inspector/active-vessels

**Authenticated (marinetraffic.inspect).** Returns all trips that are on-vessel or in-transshipment.

**All inspector responses return:**

```json
{
    "status": 200,
    "body": {
        ...API
        response
    },
    "elapsed_ms": 342
}
```

---

---

# BACKGROUND JOBS & SCHEDULED TASKS

These run automatically — no direct API endpoints — but affect resource states visible through the API.

| Job                              | Schedule                               | Purpose                                                                                               |
|----------------------------------|----------------------------------------|-------------------------------------------------------------------------------------------------------|
| `SepioOnboardCustomerJob`        | On customer approval                   | Registers company on Sepio, syncs all locations, uploads all KYC docs                                 |
| `SepioSyncLocationJob`           | On location create/update              | Syncs a single location to Sepio billing + shipping addresses                                         |
| `SepioUploadDocumentJob`         | On document upload (post-registration) | Uploads a single KYC doc to Sepio                                                                     |
| `SepioPlaceOrderJob`             | On order IL approval                   | Places seal order on Sepio manufacturing system                                                       |
| `SepioVerificationStatusPollJob` | Every 30 minutes                       | Polls Sepio for KYC verification → sets `onboarding_status: completed` on VERIFIED                    |
| `SepioOrderStatusSyncJob`        | Every 15 minutes                       | Syncs order status from Sepio (mfg_pending → order_placed → in_progress → in_transit → mfg_completed) |
| `SepioSealAllocationPollJob`     | Hourly                                 | Polls Sepio for seal allocations → expands seal range → ingests seals → status: completed             |
| `SepioSealStatusSyncJob`         | Every 15 minutes                       | Pulls Sepio scan history for all active trip seals → updates `sepio_status` and `seal_status_logs`    |
| `FastTagPollJob`                 | Every 15 minutes                       | Polls ULIP FastTag API for toll hits on active road/multimodal trips → records tracking points        |
| `VesselAisPollJob`               | Every 30 minutes                       | Polls MarineTraffic AIS for vessel position on active sea/multimodal trips → records tracking points  |
| `RegisterContainerTrackingJob`   | On trip start (sea/multimodal)         | Registers container with Kpler for tracking; syncs milestones if shipment ID available                |
| `SyncContainerMilestonesJob`     | On Kpler webhook / on registration     | Fetches full transportation timeline and upserts milestones                                           |
| `SeedSepioPortsCommand`          | Weekly, Sunday 02:00                   | Syncs master port/ICD/CFS list from Sepio API into the `ports` table                                  |

**Geofence Auto-Advance (real-time):**
When a tracking point is recorded within 5 km of the delivery location (road) or origin port (multimodal),
`VehicleArrivedAtDestination` event is fired → `AdvanceTripStatusOnArrival` listener advances `in_transit → delivered` (
road) or `in_transit → at_port` (multimodal).

**Seal Scan Auto-Advance (real-time):**
When seal scan history is synced and the scan location matches the trip's `origin_port_code`, status is auto-advanced
from `in_transit → at_port`.

---

---

# PERMISSION MATRIX

| Permission                               | Platform Admin |     Customer Admin     |
|------------------------------------------|:--------------:|:----------------------:|
| customer.view                            |       ✓        |      ✓ (own only)      |
| customer.create                          |       ✓        |           ✗            |
| customer.update                          |       ✓        |      ✓ (own only)      |
| customer.approve / reject / park         |       ✓        |           ✗            |
| user.view                                |       ✓        |      ✓ (own org)       |
| user.create                              |       ✓        |      ✓ (own org)       |
| user.update                              |       ✓        |      ✓ (own org)       |
| user.delete                              |       ✓        | ✓ (own org, not self)  |
| port.view                                |       ✓        |           ✓            |
| port.manage                              |       ✓        |           ✗            |
| location.view / create / update / delete |       ✓        |        ✓ (own)         |
| route.view / create / update / delete    |       ✓        |        ✓ (own)         |
| wallet.view                              |       ✓        |        ✓ (own)         |
| wallet.manage                            |       ✓        |           ✗            |
| pricing.view                             |       ✓        |        ✓ (own)         |
| pricing.manage                           |       ✓        |           ✗            |
| seal_order.view                          |       ✓        |        ✓ (own)         |
| seal_order.create                        |       ✓        |           ✓            |
| seal_order.approve / reject / park       |       ✓        |           ✗            |
| seal.view                                |       ✓        |        ✓ (own)         |
| seal.assign                              |       ✓        |        ✓ (own)         |
| trip.view                                |       ✓        |        ✓ (own)         |
| trip.create                              |       ✓        |           ✓            |
| trip.update                              |       ✓        | ✓ (own, not completed) |
| trip.complete                            |       ✓        |        ✓ (own)         |
| trip.destination_confirm                 |       ✓        |        ✓ (own)         |
| document.upload                          |       ✓        |           ✓            |
| document.delete                          |       ✓        |        ✓ (own)         |
| sepio.inspect                            |       ✓        |           ✗            |
| marinetraffic.inspect                    |       ✓        |           ✗            |

---

---

# IMPORTANT BUSINESS RULES

1. **Trip Status Machine** — Transitions are strictly enforced:
   `draft → in_transit → at_port → on_vessel → vessel_arrived → delivered → completed`. Only the `PATCH /trips/{trip}`
   endpoint can set intermediate statuses (`at_port`, `on_vessel`, `vessel_arrived`, `delivered`). `in_transit` is set
   by `POST /trips/{trip}/start`. `completed` is set by `POST /trips/{trip}/confirm-epod`. The system also auto-advances
   via geofence events and seal scan location matching.

2. **Completed trips are fully locked** — No status changes, no document uploads, no document deletes, no segment
   modifications.

3. **Seal assignment flow** — Only `in_inventory` seals can be assigned. On assignment, Sepio `checkSealAvailability` is
   called (if customer has Sepio registration). On trip start, Sepio `installSeal` is called — failure rolls back the
   trip to `draft`.

4. **Onboarding lock** — Once `submitted`, `il_approved`, or `completed`, client users cannot modify onboarding data.
   Platform users can always modify.

5. **Tenant isolation** — All TenantScope models silently return empty/404 for cross-tenant access. There is no 403 from
   the scope — the record simply doesn't exist for that user.

6. **Wallet debit** — `advance_balance` orders are debited at order creation time. Rejection by IL auto-refunds the
   balance and logs a `credit` transaction.

7. **Pricing tier validation** — The requested quantity must fall within at least one active pricing tier (checked at
   order creation and cost preview). Gaps in coverage return 422.

8. **Sepio address prerequisite for orders** — `billing_location_id` and `shipping_location_id` must have
   `sepio_billing_address_id` and `sepio_shipping_address_id` populated (set asynchronously after `SepioSyncLocationJob`
   completes). Orders placed before sync fail with 422.

9. **Seal ingestion count** — `POST /orders/{order}/seals` requires `seal_numbers` count to exactly match
   `order.quantity`. No partial ingestion allowed.

10. **Customer port uniqueness** — Each master port can only be added once per customer. Duplicates return 422.

11. **Minimum 20 seals per order** — Seal orders have a minimum quantity of 20.

12. **Container tracking registration** — Only triggered for `sea` and `multimodal` trips that have both
    `container_number` and `carrier_scac` set at trip start. SCAC must be 2-10 uppercase alphanumeric characters.

13. **Sepio registration prerequisites** — At least 1 `port` category port and at least 1 `icd` category port must be
    assigned to the customer before `registerCompany` is called. Missing these returns 422 from
    `SepioOnboardCustomerJob`.

14. **Driver tracking token** — The `tracking_token` field in TripResource is only returned to users in the same
    customer organization or platform users. It is used by the driver mobile app to push location without Sanctum
    authentication.

15. **FastTag deduplication** — FastTag tracking points use `seqNo` as `external_id`. Duplicate toll hits for the same
    trip/source/external_id are silently skipped.
