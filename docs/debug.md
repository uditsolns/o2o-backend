BASE URL: https://api-test.sepioproducts.com

1. /api/v1/seal/scan-history/pull

Request:

```json
{
    "seal_no": null,
    "from_datetime": "2026-04-14 07:09:43.962"
}
```

Response:

```json
{
    "status": "Not Verified",
    "statusCode": 400,
    "message": "seal_no and from_datetime are mandatory",
    "seal_dtls": null,
    "scan_history": []
}
```

2. /installationUser/installseal

Request:

```json
{
    "sealString": "SPPL",
    "sealNo": "10345566",
    "companyId": "102864",
    "createdBy": "web.tarachand@gmail.com",
    "shippingBillNo": [
        "1234569"
    ],
    "shippingBillDate": [
        "01-04-2024"
    ],
    "sealingDate": "2026-04-02",
    "sealingTime": "12:15:25",
    "destinationStation": "Hazira Port Surat (INHZA1)",
    "connectingPort": "Haldia Port (INHAL1)",
    "containerNo": "NEWCONT002",
    "truckNo": "MH04AB1234",
    "orderId": "SPPL108736",
    "sealDraftId": "default",
    "ebnNo": [
        [
            [
                "1234569"
            ],
            [],
            [
                0
            ]
        ]
    ]
}

```

Response:  This is coming because the same container_no is used with different installed seal.

```json
{
    "message": "Error log",
    "error": {
        "message1": " Below consignment details are already been installed.",
        "message2": "kindly cancel the below mentioned seal before proceeding.",
        "errormessage": [
            {
                "sealNo": "SPPL10345566",
                "sbNo": "1234569",
                "sbDate": "01/04/2024",
                "contNo": "NEWCONT002"
            }
        ]
    }
}
```

3. /installationUser/installseal

Request:

```json
{
    "sealString": "SPPL",
    "sealNo": "10345566",
    "companyId": "102864",
    "createdBy": "web.tarachand@gmail.com",
    "shippingBillNo": [
        "1234567890123"
    ],
    "shippingBillDate": [
        "01-04-2024"
    ],
    "sealingDate": "2026-04-02",
    "sealingTime": "12:06:48",
    "destinationStation": "Hazira Port Surat (INHZA1)",
    "connectingPort": "Haldia Port (INHAL1)",
    "containerNo": "NEWCONT002",
    "truckNo": "MH04AB1234",
    "orderId": "SPPL108736",
    "sealDraftId": "default",
    "ebnNo": [
        [
            [
                "1234567890123"
            ],
            [],
            [
                0
            ]
        ]
    ]
}
```

Response:

```json
{
    "message": "Error log",
    "error": {
        "errLog": [
            "Invalid Shipping Bill No 1234567890123"
        ]
    }
}
```

4. /installationUser/installseal

Request:

```json
{
    "sealString": "SPPL",
    "sealNo": "10345565",
    "companyId": "102864"
}
```

Response:

```json
{
    "error": {
        "sealAvailable": false,
        "message": "Seal Already installed"
    }
}
```

5. /installationUser/installseal

Request:

```json
{
    "sealString": "SPPL",
    "sealNo": "10345565",
    "companyId": "102864",
    "createdBy": "web.tarachand@gmail.com",
    "shippingBillNo": [
        "1234567"
    ],
    "shippingBillDate": [
        "01-04-2024"
    ],
    "sealingDate": "2024-04-02",
    "sealingTime": "14:49:22"
    /* ... same as earlier */
}
```

Response:

```json
{
    "message": "Error log",
    "error": {
        "errLog": [
            "Sealing date should be between 2026-03-14 and 2026-05-13"
        ]
    }
}
```

6. /installationUser/installseal

Request:

```json

{
    "sealString": "SPPL",
    "sealNo": "10345565",
    "companyId": "102864",
    "createdBy": "web.tarachand@gmail.com",
    "shippingBillNo": [
        "EXP/2024/00123"
    ],
    "shippingBillDate": [
        "01-04-2024"
    ]
    /* same as earlier */
}
```

Response:

```json
{
    "message": "Error log",
    "error": {
        "errLog": [
            "Invalid Shipping Bill No EXP/2024/00123"
        ]
    }
}
```

7. /installationUser/singleinstallsealcheck

Request:

```json
{
    "sealString": "SPPL",
    "sealNo": "10345569",
    "companyId": "102864"
}
```

Response:

```json
{
    "error": {
        "sealAvailable": false,
        "message": "Seal not Activated"
    }
}
```

7. /installationUser/singleinstallsealcheck

Request:

```json
{
    "sealString": "SPPL",
    "sealNo": "SPPL10345565",
    "companyId": "102864"
}
```

Response:

```json
{
    "error": {
        "sealAvailable": false,
        "message": "Invalid Seal No."
    }
}
```

8. /api/v1/seal/seal-allocation/pull

Request:

```json
{
    "company_id": "102864",
    "start_datetime": "2026-04-07 13:25:40.045",
    "end_datetime": "2026-04-09 18:55:40.045"
}
```

```json
{
    "statusCode": 400,
    "message": "The difference between start_datetime and end_datetime cannot be greater than 48 hours (2 days)",
    "data": []
}
```

9. /companyadmin/placedorder

```json
{
    "sealType": "bolt",
    "companyId": "102864",
    "shippingAddressId": "address00006657",
    "billingAddressId": "address00006656",
    "createdBy": "web.tarachand@gmail.com",
    "orderType": "credit",
    "sealCount": 100,
    "orderPorts": [
        "INBOM1",
        "INAKV6"
    ],
    "unitprice": 320,
    "totalprice": 32000,
    "freight": 3000,
    "tax": 6300,
    "grandtotal": 41300,
    "creditPeriod": 30,
    "distributorId": "D100247",
    "deliveryId": "1",
    "discrate": 0,
    "purchaseOrderNumber": null,
    "isSezUser": 0,
    "sepioURL": "sepio/orders",
    "reqId": "IL0000001",
    "totalRoundOff": 0,
    "shippingInfo": {
        "address": "Plot 12, MIDC Industrial Area, Andheri East",
        "city": "Mumbai",
        "landmark": "Near Andheri Station",
        "state": "MAHARASHTRA",
        "zip": "400093"
    },
    "billingInfo": {
        "billingCompanyName": "Tarachand Exports Pvt Ltd",
        "gstno": "27AABCS1234A1Z5",
        "address": "Plot 12, MIDC Industrial Area, Andheri East",
        "city": "Mumbai",
        "landmark": "Near Andheri Station",
        "state": "MAHARASHTRA",
        "zip": "400093"
    }
}
```

```json
{
    "message": "The reqId must contain numbers only."
}
```

* /api/v1/seal/seal-allocation/pull

```json
{
    "company_id": "102864",
    "start_datetime": "2026-04-09",
    "end_datetime": "2026-04-09"
}
```

```json
{
    "statusCode": 400,
    "message": "Invalid start_datetime format. Expected format: YYYY-MM-DD HH:mm:ss.SSS (e.g., 2024-03-11 08:15:30.865)",
    "data": []
}
```

* /api/v1/seal/seal-allocation/pull

```json
{
    "company_id": "7",
    "start_datetime": "2026-04-09",
    "end_datetime": "2026-04-09"
}
```

```json
{
    "statusCode": 400,
    "message": "Invalid company_id length. Must be between 5 and 10 digits",
    "data": []
}
```

* /registrationModule/registercompany

```json
{
    "companydetailsInfo": {
        "companyName": "Tarachand Exports Pvt Ltd",
        "IEC": "IEC9229932",
        "sealRequest": "1000",
        "port": "Bombay Port Mumbai (INBOM1)",
        "icd": "ICD Ankaleshwar (INAKV6)",
        "cfsLocation": "CFS ICD Irangattukottai (INILP6)",
        "chaUser": "",
        "chaId": "",
        "distributorId": "D100247",
        "sepioURL": "sepio/companies"
    },
    "primaryContactInfo": {
        "fName": "Tarachand",
        "lName": "Test",
        "email": "web.tarachand@gmail.com",
        "contactNo": "9136248458",
        "password": "CDFtm2SoqYq1Wko7",
        "conpassword": "CDFtm2SoqYq1Wko7",
        "isAdmin": true
    },
    "register_from_type": "ILGIC"
}
```

```json
{
    "message": "user web.tarachand@gmail.com already exists."
}
```

- Auto generate trip_ref
- Fields which are managed by backend system, not by crud:
    - created_by_id, trip_ref, status, vessel_tracking_ref, vessel_tracking_data, last_vessel_tracked_at,
      trip_start_time, actual_delivery_date, trip_end_time, epod_status, epod_confirmed_at, epod_confirmed_by_id
- only following fields should be optional:
    - voyage_number, bill_of_lading, eta, etd
    - hs_code, gross_weight, net_weight, weight_unit, quantity, quantity_unit, declared_cargo_value
- Add dispatch_contact_email, delivery_contact_email, epod_confirmation_notes
- Remove route_id, transporter_name, transporter_id, destination_confirmed_by_id, destination_confirmed_at,
  destination_confirmation_notes
- We have to merge destination confirmation and epod to only epod.
- We have to validate fields according to transport_mode, we will be required only dispatch and destination locations,
  if it is sea then will only need port info and will need both the info for multimodal.
- We have to provide a separate apis for tasks like starting a trip, etc. Instead of inspecting status change in the
  trip update request.
- We have customer_routes CRUD but need changes in its behaviour, Whenever a trips gets created, we will create a new
  route in customer_routes if the trip has different route details than already existing routes. Now whenever a user
  creates a trip he can prefill trip details from previously created existing routes or he can fill up all the details
  manually.
- We want following information in the route:
    - trip_type, transport_mode, dispatch location details, delivery location details, origin and destination port
      details,
    - We don't have to store dispatch_location_id, delivery_location_id, origin_port_id, destination_port_id, we will
      store the copy the values only same as we are storing in trips. trips should also copy only values from the routes
      instead of storing route_id.
    - User can create routes or it should be created automatically after trip creation if the route does not already
      exist.
- We have to create two tables, customer_consignors & customer_consignees table for storing trip consignor and consignee
  data, While trip creation, user can type the consignee name and we will show existing customer consignees, he can
  select the consignee and data will be pre-filled otherwise he can type manually. Same applies to consignor as well.
- When creating a trip our backend can check if the consignee and consignor already exists and if does not exists it
  will create automatically. Same flow should happen for customer_routes CRUD and creation from Trips. We have to store
  only values of consignor & consignee details not the ids in both customer_routes and trips.
- We want to allow users to optionally add intermediatory routes of a trip as:
    - Source, Destination, Mode of transportation (road / sea), tracking source (GPS / TCL Tracker / E-Lock /
      Driver Mobile / Driver SIM / Fast Tag )
      they can define the entire trip journey in 2 to 3 sub routes like trip will start from pune to mumbai port by road
      on a
      truck and will tracked by Driver Mobile (gps info taken from the mobile app) and then from Mumbai Port to X
      Destination Port by sea.
    - The tracking source should be asked only for road transportation mode, because sea tracking will be done by only a
      third party vessel tracking api so that will be default for sea transportation.
- This intermediatory routes is completely optional, everything else should work normally irrespective of these routes.
- Users should not be able to login if customer is not active and should not be able to perform any action if already
  logged in. prevent all the actions from the backend by sending appropriate meaningful message.
- use seals_dispatched_at & seals_delivered_at columns
- move trip to at_port from in_transit, when seal is scanned at origin port during the resposne:

```json
  {
    "status": "Verified",
    "statusCode": 200,
    "message": "TID Matched",
    "seal_dtls": "decryptData",
    "scan_history": [
        {
            "scanDetailsId": "195447",
            "location": "Hazira Port Surat (INHZA1)",
            "createdAt": "2026-04-15T09:13:43.992Z",
            "createdBy": "ashcustom@gmail.com",
            "sealDetailsId": "sealDtlsId00179711",
            "deviceId": "10120f3cc1acbe53",
            "sealNo": "SPPL10345566",
            "tId": "E28068902992374AS783J9796",
            "sealStatus": "Success",
            "installationSealType": 1,
            "sealType": "bolt",
            "reason": null,
            "coFileStatus": null,
            "cfsScan": null,
            "portScan": null,
            "latitude": 10.0737946,
            "longitude": 76.54419,
            "imeiNo": null,
            "macAddress": null
        }
    ]
}
```

- customers, ports, customer_ports, customer_locations, customer_routes
- I want explanation on wallet usage from all type of coasting types.
- gps tracking and FasTag

```python

customer_id
created_by_id
seal_id
route_id
trip_ref        -  Auto Generated Field -> "ILTR000000238"
status          -  draft / in_transit / at_port / on_vessel / vessel_arrived / delivered / completed
trip_type       -  Export / Domestic / Import
transport_mode  -  road / sea / multimodal

// Dispatch Location
dispatch_location_name
dispatch_address
dispatch_city
dispatch_state
dispatch_pincode
dispatch_country
dispatch_contact_person    # remove ?
dispatch_contact_email
dispatch_contact_number    # remove ?
dispatch_lat
dispatch_lng

// Delivery Location
delivery_location_name
delivery_address
delivery_city
delivery_state
delivery_pincode
delivery_country
delivery_contact_person
delivery_contact_email    # add ?
delivery_contact_number
delivery_lat
delivery_lng

// Origin port 
origin_port_name
origin_port_code
origin_port_category

// Destination port
destination_port_name
destination_port_code
destination_port_category

// Container
container_number
container_type
# seal_issue_date

// Driver
driver_name
driver_license
driver_aadhaar
driver_phone
is_driver_license_verified
is_driver_aadhaar_verified
driver_license_verification_payload
driver_aadhaar_verification_payload

// Vehicle
vehicle_number
vehicle_type
transporter_name          -  remove ?
transporter_id            -  remove ?
is_rc_verified
rc_verification_payload
is_verification_done

// Vessel 
vessel_name
vessel_imo_number
voyage_number
bill_of_lading
vessel_tracking_ref
vessel_tracking_data
eta
etd
last_vessel_tracked_at

// Cargo
cargo_type
cargo_description
hs_code
gross_weight
net_weight
weight_unit
quantity
quantity_unit
declared_cargo_value

// At trip creation
invoice_number
invoice_date
eway_bill_number
eway_bill_validity_date

// Timeline
dispatch_date
trip_start_time
expected_delivery_date
actual_delivery_date
trip_end_time

// ePOD
epod_status
epod_confirmed_at
epod_confirmed_by_id
epod_confirmation_notes
```

Intermediatory Locations

```json
[
    {
        "source_name": "",
        "destination_name": "",
        "transportation_mode": "road/sea",
        "tracking_type": "",
        "order_no": ""
    }
]
```

# AIS API

## Vessel Positions

Endpoint: https://services.marinetraffic.com/api/exportvessels/{api_key}

Track vessels of interest anywhere in the world.

- Information about AIS-transmitted data:
  > A functioning AIS transponder keeps transmitting information even when the subject vessel is anchored. The
  information
  > contained in each AIS-data packet (or message) can be divided into the following two main categories:

  > Dynamic Information
  > (such information is automatically transmitted every 2 to 10 seconds depending on the vessel's speed and course
  while
  > underway and every 6 minutes while anchored from vessels equipped with Class A transponders)
  > Maritime Mobile Service Identity number (MMSI) - a unique identification number for each vessel station (the
  vessel's
  > flag can also be deducted from it)
  > AIS Navigational Status (read more on the subject)
  > Rate of Turn - right or left (0 to 720 degrees per minute)
  > Speed over Ground - 0 to 102 knots (0.1-knot resolution)
  > Position Coordinates (latitude/longitude - up to 0.0001 minutes accuracy)
  > Course over Ground - up to 0.1° relative to true north
  > Heading - 0 to 359 degrees
  > Bearing at own position - 0 to 359 degrees
  > UTC seconds - the seconds field of the UTC time when the subject data-packet was generated.
  > Static & Voyage related Information
  > (such information is provided by the subject vessel's crew and is transmitted every 6 minutes regardless of the
  > vessel's
  > movement status)
  > International Maritime Organisation number (IMO) - note that this number remains the same upon transfer of the
  subject
  > vessel's registration to another country (flag)
  > Call Sign - international radio call sign assigned to the vessel by her country of registry
  > Name - up to 20 characters
  > Type (or cargo type) - the AIS ID of the subject vessel's shiptype
  > Dimensions - approximated to the nearest metre (based on the position of the AIS Station on the vessel)
  > Location of the positioning system's antenna on board the vessel
  > Type of positioning system (GPS, DGPS, Loran-C)
  > Load Condition - Draught - 0.1 to 25.5 metres
  > Destination - up to 20 characters
  > ETA (estimated time of arrival) - UTC month/date hours:minutes
  > It is important to notice that the vessel's crew or the accountable vessel's officer should make sure that they
  > provide
  > the system with the correct information regarding all static and voyage-related fields.

  > Note also that Class B transponders transmit a reduced set of data compared to Class A (IMO number, Draught,
  > Destination, ETA, Rate of Turn, Navigational Status are not included). The reporting intervals from Class B
  > transponders
  > are also scarcer compared to those of Class A transponders (30 seconds minimum).

- **SPEED** speed over ground returned in (knots x10)
- **SPEED** and **COURSE** values for base stations are represented by zero (0)
- **TIMESTAMP** UTC second when the report was generated (0-59)
- **HEADING** true heading in degrees (0-359) (values -1 or 511 indicate lack of data)
- **HEADING** for SAR Aircrafts contains the Altitude of the aircraft (“STATUS” = 97)
- **DSRC** describes whether the transmitted AIS message was received by a terrestrial (TER), satellite (SAT) or
  roaming (ROAM) AIS antenna.
- The **timespan** parameter lets you specify how far back in time the API should look for the latest available vessel
  positions. Default value is set to 5 minutes and can be adjusted to a maximum of 1,440 minutes (24 hours).
- More information about response parameters: STATUS, SHIPTYPE
- The **frequency of allowed API** calls is specific to your API key and is detailed in your contract as a number of
  successful calls per time period. For example “2 calls per minute”.
  Regardless of this agreed limit, each API key is technically restricted to a maximum of 100 total (including
  successful and unsuccessful) requests per minute to ensure system stability.

### Path Parameters

- api_key: required, string: 40-character hexadecimal number

### Query Parameters

- v: required, integer: Use latest version 9
- timespan: default - 5, integer - Overrides the default timespan
- vesseltypeid: integer - Filter vessels based on vessel types, comma separated ids supported, available types - (1,"
  Aggregates Carrier"), (2, "Anchor Handling Vessel"), (3, "Asphalt/Bitumen Tanker"), (4, "Bulk Carrier"), (5, "
  Bunkering Tanker"), (6, "Cable Layer"), (7, "Cargo"), (8, "Cement Carrier"), (9, "Chemical Tanker"), (10, "Container
  Ship"), (11, "Crew Boat"), (12, "Crude Oil Tanker"), (13, "Dredger"), (14, "Drill Ship"), (15, "Fire Fighting
  Vessel"), (16, "Fish Carrier"), (17, "Fishing"), (18, "Fishing Vessel"), (19, "Floating Crane"), (20, "Floating
  Storage/Production"), (21, "General Cargo"), (22, "Heavy Load Carrier"), (23, "High Speed Craft"), (24, "
  Icebreaker"), (25, "Inland Passengers Ship"), (26, "Landing Craft"), (27, "Livestock Carrier"), (28, "LNG Tanker"), (
  29, "LPG Tanker"), (30, "Military Ops"), (31, "Navigation Aids"), (32, "Obo Carrier"), (33, "Offshore Structure"), (
  34, "Offshore Vessel"), (35, "Oil Products Tanker"), (36, "Oil/Chemical Tanker"),, (37, "Ore Carrier"), (38, "Special
  Craft"), (39, "Special Cargo"), (40, "Special Fishing Vessel"), (41, "Special Passenger Vessel"), (42, "Special
  Pleasure Craft"), (43, "Special Tanker"), (44, "Passenger"), (45, "Passenger/Cargo Ship"), (46, "Passenger Vessel"), (
  47, "Patrol Vessel"), (48, "Pilot Boat"), (49, "Platform"), (50, "Pollution Control Vessel"), (51, "Pusher Tug"), (
  52, "Reefer"), (53, "Research/Survey Vessel"), (54, "Ro-Ro/Passenger Vessel"), (55, "Ro-Ro/Vehicles Carrier"), (56, "
  Sailing Vessel"), (57, "Search & Rescue"), (58, "Service Vessel"), (59, "Special Tug"), (60, "Supply Vessel"), (61, "
  Tanker"), (62, "Training Ship"), (63, "Trawler"), (64, "Tug"), (65, "Tug/Supply Vessel"), (66, "Water Tanker"), (67, "
  Yacht"), (68, "Barge"), (69, "Inland Tanker"), (70, "Inland Cargo"), (71, "Inland Tug"), (900, "Unspecified"), (901, "
  Navigation Aids"), (902, "Other Fishing"), (903, "Other Special Craft"), (904, "High Speed Craft"), (906, "Other
  Passenger"), (907, "Other Cargo"), (908, "Other Tanker"), (909, "Other Pleasure Craft")
- cursor: string - The pagination cursor provided in the metadata section of the previous response
- limit: default - 2000, integer - The limit of vessels per page (min=1000, max=5000)
- protocol: default - "jsono", string - Response type. Use one of the following: jsono, csv

### Responses:

1. 200 Successful Response:
   data: Array of objects of following properties:

- MSSI: string - Maritime Mobile Service Identity - a nine-digit number sent in digital form over a radio frequency that
  identifies the vessel's transmitter station
- IMO: string - International Maritime Organisation number - a seven-digit number that uniquely identifies vessels
- SHIP_ID: string - A uniquely assigned ID by MarineTraffic for the subject vessel
- LAT: string - Latitude - a geographic coordinate that specifies the north-south position of the vessel on the Earth's
  surface
- LON: string - Longitude - a geographic coordinate that specifies the east-west position of the vessel on the Earth's
  surface
- SPEED: string - The speed (in knots x10) that the subject vessel is reporting according to AIS transmissions
- HEADING: string - The heading (in degrees) that the subject vessel is reporting according to AIS transmissions
- COURSE: string - The course (in degrees) that the subject vessel is reporting according to AIS transmissions
- STATUS: string - The AIS Navigational Status of the subject vessel as input by the vessel's crew. There might be
  discrepancies with the vessel's detail page when vessel speed is near zero (0) knots.
  Value Meaning:
  0 = under way using engine
  1 = at anchor
  2 = not under command
  3 = restricted maneuverability
  4 = constrained by her draught
  5 = moored
  6 = aground
  7 = engaged in fishing
  8 = under way sailing
  9 = reserved for future amendment of navigational status for ships carrying DG, HS, or MP, or IMO hazard or pollutant
  category C, high-speed craft (HSC)
  10 = reserved for future amendment of navigational status for ships carrying dangerous goods (DG), harmful
  substances (
  HS) or marine pollutants (MP), or IMO hazard or pollutant category A, wing in ground (WIG)
  11 = power-driven vessel towing astern (regional use)
  12 = power-driven vessel pushing ahead or towing alongside (regional use)
  13 = reserved for future use
  14 = AIS-SART (active), MOB-AIS, EPIRB-AIS
  15 = undefined = default (also used by AIS-SART, MOB-AIS and EPIRB-AIS under test)
  Note that, if you are using MarineTraffic API Services, it is possible to get STATUS responses such as the following
  ones (not AIS-derived):
  95 = Base Station
  96 = Class B
  97 = SAR Aircraft
  98 = Aid to Navigation
  99 = Class B

- TIMESTAMP: string <date-time>- The date and time (in UTC) that the subject vessel's position or event was recorded by
  MarineTraffic
- DSRC: string - Describes whether the transmitted AIS message was received by a terrestrial (TER), satellite (SAT) or
  roaming (ROAM) AIS antenna.
- UTC_SECONDS: string - The time slot that the subject vessel uses to transmit information
- MARKET: string - Vessel's commercial market
- SHIPNAME: string - The Shipname of the subject vessel
- SHIPTYPE: string - The Shiptype of the subject vessel according to AIS transmissions
  | SHIPTYPE / VESSEL TYPE NUMBER | TYPE NAME | AIS TYPE SUMMARY | VESSEL TYPE |
  |---|---|---|---|
  | 10-19 | Reserved | Unspecified | |
  | 20-28 | Wing In Grnd | Wing in Grnd | Wing In Ground Effect Vessel |
  | 29 | SAR Aircraft | Search and Rescue | |
  | 30 | Fishing | Fishing | Fishing Vessel, Trawler, Fishery Protection/Research, Fish Carrier, Fish Factory, Factory
  Trawler, Fish Storage Barge, Fishery Research Vessel, Fishery Patrol Vessel, Fishery Support Vessel |
  | 31 | Tug | Tug | Towing Vessel, Tug/Tender, Tug/Supply Vessel, Tug/Fire Fighting Vessel, Tug, Tug/Pilot Ship, Anchor
  Handling Salvage Tug, Towing/Pushing, Tug/Ice Breaker, Tractor Tug, Tug/Support, Articulated Pusher Tug |
  | 32 | Tug | Tug | |
  | 33 | Dredger | Special Craft | Suction Hopper Dredger, Dredger, Drill Ship, Grab Hopper Dredger, Grab Dredger, Sand
  Suction Dredger, Hopper Dredger, Cutter Suction Dredger, Cutter Suction Hopper Dredger, Suction Dredger, Bucket
  Dredger, Trailing Suction Hopper Dredge, Trailing Suction Dredger, Inland Dredger, Drilling Jack Up, Bucket Ladder
  Dredger, Drill Barge, Bucket Hopper Dredger, Bucket Dredger Pontoon, Bucket Wheel Suction Dredger, Dredging Pontoon,
  Backhoe Dredger, Suction Dredger Pontoon, Water Jet Dredging Pontoon, Grab Dredger Pontoon, Kelp Dredger |
  | 34 | Dive Vessel | Special Craft | Diving Support Vessel |
  | 35 | Military Ops | Special Craft | Naval/Naval Auxiliary Vessel, Naval Auxiliary Tug, Logistics Naval Vessel, Mine
  Hunter, Minesweeper, Combat Vessel, Command Vessel, Naval Salvage Vessel, Torpedo Recovery Vessel, Naval Research
  Vessel, Naval Patrol Vessel, Troopship, Radar Vessel |
  | 36 | Sailing Vessel | Sailing Vessel | Sailing Vessel |
  | 37 | Pleasure Craft | Pleasure Craft | Yacht, Museum Ship, Exhibition Ship, Floating Hotel/Restaurant, Theatre
  Vessel |
  | 38 | Reserved | Unspecified | |
  | 39 | Reserved | Unspecified | |
  | 40-49 | High-Speed Craft | High-Speed Craft | Hydrofoil, Hovercraft |
  | 50 | Pilot Vessel | Special Craft | |
  | 51 | SAR | Search and Rescue | Salvage/Rescue Vessel, Offshore Safety Vessel, Standby Safety Vessel |
  | 52 | Tug | Tug | Icebreaker, Inland Tug, Pusher Tug |
  | 53 | Port Tender | Special Craft | Tender, Crew Boat, Pilot Ship, Supply Tender |
  | 54 | Anti-Pollution | Special Craft | Pollution Control Vessel |
  | 55 | Law Enforce | Special Craft | Patrol Vessel |
  | 56 | Local Vessel | Special Craft | |
  | 57 | Local Vessel | Special Craft | |
  | 58 | Medical Trans | Special Craft | Hospital Ship |
  | 59 | Special Craft | Special Craft | Multi Purpose Offshore Vessel, Barge Carrier, Heavy Lift Vessel, Special
  Vessel, Maintenance Vessel, Pipe Layer, Waste Disposal Vessel, Supply Vessel, Training Ship, Floating
  Storage/Production, Radio Ship, Research/Survey Vessel, Repair Ship, Support Vessel, Fire Fighting Tractor Tug,
  Landing Craft, Floating Crane, Fire Fighting/Supply Vessel, Whaler, Multi-Purpose Vessel, Tank-Cleaning Vessel, Mining
  Vessel, Fire Fighting Vessel, Paddle Ship, Anchor Handling Vessel, Nuclear Fuel Carrier, Sludge Carrier, Whale
  Factory, Utility Vessel, Work Vessel, Platform, Mission Ship, Buoy-Laying Vessel, Well Stimulation Vessel, Motor
  Hopper, Cable Layer, Anchor Handling/Fire Fighting, Crane Ship, Inland Supply Vessel, Offshore Supply Ship, Trenching
  Support Vessel, Offshore Construction Jack Up, Pile Driving Vessel, Replenishment Vessel, Construction Support Vessel,
  Pipelay Crane Vessel, Crane Barge, Work Pontoon, Production Testing Vessel, Floating Sheerleg, Mooring Vessel, Diving
  Support Platform, Support Jack Up, Sealer, Trans Shipment Vessel, Floating Linkspan, Crane Jack Up, Pumping Platform,
  Air Cushion Vessel, Power Station Vessel, Supply Jack Up, Radar Platform, Jacket Launching Pontoon, Pipe Layer
  Platform, Pipe Burying Vessel, Air Cushion Patrol Vessel, Air Cushion Work Vessel, Pearl Shells Carrier, Steam Supply
  Pontoon, Incinerator, Jack Up Barge, Desalination Pontoon, Grain Elevating Pontoon |
  | 60-69 | Passenger | Passenger | Passengers Ship, Inland Passengers Ship, Inland Ferry, Floating Hotel, Ferry,
  Ro-Ro/Passenger Ship, Accommodation Ship, Accommodation Barge, Accommodation Jack Up, Accommodation Vessel, Passengers
  Landing Craft, Houseboat, Accommodation Platform, Air Cushion Passenger Ship, Air Cushion Ro-Ro/Passenger Sh |
  | 70 | Cargo | Cargo | Passenger/Cargo Ship, Livestock Carrier, Bulk Carrier, Ore Carrier, General Cargo, Wood Chips
  Carrier, Container Ship, Ro-Ro Cargo, Reefer, Heavy Load Carrier, Barge, Ro-Ro/Container Carrier, Inland Cargo, Cement
  Carrier, Reefer/Containership, Vegetable/Animal Oil Tanker, Obo Carrier, Vehicles Carrier, Inland Ro-Ro Cargo Ship,
  Rail/Vehicles Carrier, Pallet Carrier, Cargo Barge, Hopper Barge, Deck Cargo Ship, Cargo/Containership, Aggregates
  Carrier, Limestone Carrier, Ore/Oil Carrier, Self Discharging Bulk Carrier, Deck Cargo Pontoon, Bulk Carrier With
  Vehicle Deck, Pipe Carrier, Cement Barge, Stone Carrier, Bulk Storage Barge, Aggregates Barge, Timber Carrier, Bulker,
  Trans Shipment Barge, Powder Carrier, Cabu Carrier, Vehicle Carrier, Cargo |
  | 71 | Cargo - Hazard X (Major) | Cargo | |
  | 72 | Cargo - Hazard Y | Cargo | |
  | 73 | Cargo - Hazard Z (Minor) | Cargo | |
  | 74 | Cargo - Hazard OS (Recognizable) | Cargo | |
  | 75-79 | Cargo | Cargo | |
  | 80 | Tanker | Tanker | Tanker, Asphalt/Bitumen Tanker, Chemical Tanker, Crude Oil Tanker, Inland Tanker, Fruit Juice
  Tanker, Bunkering Tanker, Wine Tanker, Oil Products Tanker, Oil/Chemical Tanker, Water Tanker, Tank Barge, Edible Oil
  Tanker, Lpg/Chemical Tanker, Shuttle Tanker, Co2 Tanker |
  | 81 | Tanker - Hazard A (Major) | Tanker | |
  | 82 | Tanker - Hazard B | Tanker | |
  | 83 | Tanker - Hazard C (Minor) | Tanker | |
  | 84 | Tanker - Hazard D (Recognizable) | Tanker | Lng Tanker, Lpg Tanker, Gas Carrier |
  | 85-89 | Tanker | Tanker | |
  | 90-99 | Other | Other | |

  Note that, if you are using MarineTraffic API Services, it is possible to get SHIPTYPE responses such as those
  included in the next table (not AIS-derived):
  | ID | SHIPTYPE |
  |---|---|
  | 100 | Navigation Aid |
  | 101 | Reference Point |
  | 102 | RACON |
  | 103 | OffShore Structure |
  | 104 | Spare |
  | 105 | Light, without Sectors |
  | 106 | Light, with Sectors |
  | 107 | Leading Light Front |
  | 108 | Leading Light Rear |
  | 109 | Beacon, Cardinal N |
  | 110 | Beacon, Cardinal E |
  | 111 | Beacon, Cardinal S |
  | 112 | Beacon, Cardinal W |
  | 113 | Beacon, Port Hand |
  | 114 | Beacon, Starboard Hand |
  | 115 | Beacon, Preferred Channel Port hand |
  | 116 | Beacon, Preferred Channel Starboard hand |
  | 117 | Beacon, Isolated danger |
  | 118 | Beacon, Safe Water |
  | 119 | Beacon, Special Mark |
  | 120 | Cardinal Mark N |
  | 121 | Cardinal Mark E |
  | 122 | Cardinal Mark S |
  | 123 | Cardinal Mark W |
  | 124 | Port Hand Mark |
  | 125 | Starboard Hand Mark |
  | 126 | Preferred Channel Port Hand |
  | 127 | Preferred Channel Starboard Hand |
  | 128 | Isolated Danger |
  | 129 | Safe Water |
  | 130 | Manned VTS |
  | 131 | Light Vessel |

- CALLSIGN: string - A uniquely designated identifier for the vessel's transmitter station
- FLAG: string - The flag of the subject vessel according to AIS transmissions
- LENGTH: string - The overall Length (in metres) of the subject vessel
- WIDTH: string - The Breadth (in metres) of the subject vessel
- GRT: string - Gross Tonnage - unitless measure that calculates the moulded volume of all enclosed spaces of a ship
- DWT: string - Deadweight - a measure (in metric tons) of how much weight a vessel can safely carry (excluding the
  vessel's own weight)
- DRAUGHT: string - The Draught (in metres x10) of the subject vessel according to the AIS transmissions
- YEAR_BUILT: string - The year that the subject vessel was built
- SHIP_COUNTRY: string - The vessel's country
- SHIP_CLASS: string - Vessel's class based on commercial market, capacity and/or dimensions
- ROT: string - Rate of Turn
- TYPE_NAME: string - The MarineTraffic ship type of the vessel
- AIS_TYPE_SUMMARY: string - Further explanation of the SHIPTYPE ID
- DESTINATION: string - The Destination of the subject vessel according to the AIS transmissions
- ETA: string <date-time>- The Estimated Time of Arrival to Destination of the subject vessel according to the AIS
  transmissions
- L_FORE: string - The relative distance from the AIS station of the vessel to the foremost of it (front / bow)
- W_LEFT: string - The relative distance from the AIS station of the vessel to the leftmost of it (left side / port)
- LAST_PORT: string - The Name of the Last Port the vessel has visited
- LAST_PORT_TIME: string <date-time>- The Date and Time (in UTC) that the subject vessel departed from the Last Port
- LAST_PORT_ID: string - A uniquely assigned ID by MarineTraffic for the Last Port
- LAST_PORT_UNLOCODE: string - A uniquely assigned ID by United Nations for the Last Port
- LAST_PORT_COUNTRY: string - The Country that the Last Port is located at
- CURRENT_PORT: string - The name of the Port the subject vessel is currently in (NULL if the vessel is underway)
- CURRENT_PORT_ID: string - A uniquely assigned ID by MarineTraffic for the Current Port
- CURRENT_PORT_UNLOCODE: string - A uniquely assigned ID by United Nations for the Current Port
- CURRENT_PORT_COUNTRY: string - The Country that the Current Port is located at
- NEXT_PORT_ID: string - A uniquely assigned ID by MarineTraffic for the Next Port
- NEXT_PORT_UNLOCODE: string - A uniquely assigned ID by United Nations for the Next Port
- NEXT_PORT_NAME: string - The Name of the Next Port as derived by MarineTraffic based on the subject vessel's reported
  Destination
- NEXT_PORT_COUNTRY: string - The Country that the Next Port is located at
- ETA_CALC: string <date-time>- The Estimated Time of Arrival to Destination of the subject vessel according to the
  MarineTraffic calculations
- ETA_UPDATED: string <date-time>- The date and time (in UTC) that the ETA was calculated by MarineTraffic
- DISTANCE_TO_GO: string - The Remaining Distance (in NM) for the subject vessel to reach the reported Destination
- DISTANCE_TRAVELLED: string - The Distance (in NM) that the subject vessel has travelled since departing from Last Port
- AVG_SPEED: string - The average speed calculated for the subject vessel during the latest voyage (port to port)
- MAX_SPEED: string - The maximum speed reported by the subject vessel during the latest voyage (port to port)

metadata: object with following properties

- CURSOR: string - The pagination cursor that should be provided in the next request
- DATE_FROM: string <date-time> - The starting date of the returned positions
- DATE_TO: string <date-time> - The ending date of the returned positions

**Example**:

```json
{
    "DATA": [
        {
            "MMSI": "538003913",
            "IMO": "9470959",
            "SHIP_ID": "713139",
            "LAT": "37.388430",
            "LON": "23.871230",
            "SPEED": "6",
            "HEADING": "104",
            "COURSE": "41",
            "STATUS": "0",
            "TIMESTAMP": "2020-10-15T12:21:44.000Z",
            "DSRC": "TER",
            "UTC_SECONDS": "45",
            "MARKET": "SUPPORTING VESSELS",
            "SHIPNAME": "SUNNY STAR",
            "SHIPTYPE": "89",
            "CALLSIGN": "V7TZ6",
            "FLAG": "MH",
            "LENGTH": "184",
            "WIDTH": "27.43",
            "GRT": "23313",
            "DWT": "37857",
            "DRAUGHT": "95",
            "YEAR_BUILT": "2010",
            "SHIP_COUNTRY": "Marshall Is",
            "SHIP_CLASS": "HANDYSIZE",
            "ROT": "0",
            "TYPE_NAME": "Oil/Chemical Tanker",
            "AIS_TYPE_SUMMARY": "Tanker",
            "DESTINATION": "FOR ORDERS",
            "ETA": "2020-10-14T12:00:00.000Z",
            "L_FORE": "5",
            "W_LEFT": "5",
            "CURRENT_PORT": "",
            "LAST_PORT": "AGIOI THEODOROI",
            "LAST_PORT_TIME": "2020-10-13T23:39:00.000Z",
            "CURRENT_PORT_ID": "",
            "CURRENT_PORT_UNLOCODE": "",
            "CURRENT_PORT_COUNTRY": "",
            "LAST_PORT_ID": "29",
            "LAST_PORT_UNLOCODE": "GRAGT",
            "LAST_PORT_COUNTRY": "GR",
            "NEXT_PORT_ID": "",
            "NEXT_PORT_UNLOCODE": "",
            "NEXT_PORT_NAME": "",
            "NEXT_PORT_COUNTRY": "",
            "ETA_CALC": "",
            "ETA_UPDATED": "",
            "DISTANCE_TO_GO": "0",
            "DISTANCE_TRAVELLED": "74",
            "AVG_SPEED": "12.6",
            "MAX_SPEED": "13.2"
        }
    ],
    "METADATA": {
        "CURSOR": "abcdef",
        "DATE_FROM": "2023-11-20 15:57:00",
        "DATE_TO": "2023-11-20 16:57:00"
    }
}
```

2. 429 Too Many Requests
   errors: array of objects with properties:

- code: string - Error code
- detail: string - Error message

**Example:**

```json
{
    "errors": [
        {
            "code": "1r",
            "detail": "TOO MANY REQUESTS"
        }
    ]
}
```

## Single Vessel Positions

Endpoint: https://services.marinetraffic.com/api/exportvessel/{api_key}

Everything is same as `/api/exportvessels/{api_key}` api but with query parameters, responses changes as:

### Query Parameters

- v: required, integer: Use latest version 6
- shipid: required, integer - A uniquely assigned ID by MarineTraffic for the subject vessel, You can instead use imo or
  mmsi.
- imo: integer - The International Maritime Organization (IMO) number of the vessel you wish to track
- mmsi: integer - The Maritime Mobile Service Identity (MMSI) of the vessel you wish to track
- timespan: default - 5, integer - Overrides the default timespan
- protocol: default - "jsono", string - Response type. Use one of the following: jsono, csv

### Responses:

1. 200 Successful Response

```json
[
    {
        "MMSI": "538003913",
        "IMO": "9470959",
        "SHIP_ID": "713139",
        "LAT": "37.388430",
        "LON": "23.871230",
        "SPEED": "6",
        "HEADING": "104",
        "COURSE": "41",
        "STATUS": "0",
        "TIMESTAMP": "2020-10-15T12:21:44.000Z",
        "DSRC": "TER",
        "UTC_SECONDS": "45",
        "MARKET": "SUPPORTING VESSELS",
        "SHIPNAME": "SUNNY STAR",
        "SHIPTYPE": "89",
        "CALLSIGN": "V7TZ6",
        "FLAG": "MH",
        "LENGTH": "184",
        "WIDTH": "27.43",
        "GRT": "23313",
        "DWT": "37857",
        "DRAUGHT": "95",
        "YEAR_BUILT": "2010",
        "SHIP_COUNTRY": "Marshall Is",
        "SHIP_CLASS": "HANDYSIZE",
        "ROT": "0",
        "TYPE_NAME": "Oil/Chemical Tanker",
        "AIS_TYPE_SUMMARY": "Tanker",
        "DESTINATION": "FOR ORDERS",
        "ETA": "2020-10-14T12:00:00.000Z",
        "L_FORE": "5",
        "W_LEFT": "5",
        "CURRENT_PORT": "",
        "LAST_PORT": "AGIOI THEODOROI",
        "LAST_PORT_TIME": "2020-10-13T23:39:00.000Z",
        "CURRENT_PORT_ID": "",
        "CURRENT_PORT_UNLOCODE": "",
        "CURRENT_PORT_COUNTRY": "",
        "LAST_PORT_ID": "29",
        "LAST_PORT_UNLOCODE": "GRAGT",
        "LAST_PORT_COUNTRY": "GR",
        "NEXT_PORT_ID": "",
        "NEXT_PORT_UNLOCODE": "",
        "NEXT_PORT_NAME": "",
        "NEXT_PORT_COUNTRY": "",
        "ETA_CALC": "",
        "ETA_UPDATED": "",
        "DISTANCE_TO_GO": "0",
        "DISTANCE_TRAVELLED": "74",
        "AVG_SPEED": "12.6",
        "MAX_SPEED": "13.2"
    }
]
```

2. 400 Bad Request

```json
{
    "errors": [
        {
            "code": "2",
            "detail": "VESSEL MMSI OR IMO OR SHIPID MISSING"
        }
    ]
}
```

3. Too Many Requests

```json
{
    "errors": [
        {
            "code": "1r",
            "detail": "TOO MANY REQUESTS"
        }
    ]
}
```

Here's the **Single Vessel Historical Positions** API documentation in markdown:

---

## Single Vessel Historical Positions

Get all historical positions of a single vessel for a specific period of time.

**Endpoint**

```
GET https://services.marinetraffic.com/api/exportvesseltrack/{api_key}
```

### Notes

- Default resolution for returned positions is up to 2 minutes. Use the `period` parameter to limit the frequency of
  positions
- Weather data is returned once within each hour's results
- **SPEED** — speed over ground returned in (knots x10)
- **TIMESTAMP** — UTC second when the report was generated (0–59)
- Historical positions are available since January 2015
- **Hourly** and **daily** records are the first records received during the hour or the day respectively
- When the `period` is `hourly`, the field **HEADING** is not available for positions older than 2017-11-17
- **Hourly** records are available for dates after 2014-08-28
- Weather data for **winds** are available for dates after 2015-05-27
- Weather data for **waves**, **swells** and **currents** are available for dates after 2021-01-21
- The frequency of allowed API calls is specific to your API key and detailed in your contract (e.g. "2 calls per
  minute"). Each API key is technically restricted to a maximum of **100 total requests per minute**

---

### Path Parameters

| Parameter | Type   | Required | Description                              |
|-----------|--------|----------|------------------------------------------|
| `api_key` | string | ✅        | API key: 40-character hexadecimal number |

---

### Query Parameters

| Parameter  | Type               | Required | Default  | Description                                                                                |
|------------|--------------------|----------|----------|--------------------------------------------------------------------------------------------|
| `v`        | integer            |          | `1`      | Version of the service to be executed. Use version `3` to get the latest                   |
| `shipid`   | integer            | ✅*       |          | A uniquely assigned ID by MarineTraffic for the vessel. Can use `imo` or `mmsi` instead    |
| `mmsi`     | integer            |          |          | The Maritime Mobile Service Identity (MMSI) of the vessel                                  |
| `imo`      | integer            |          |          | The International Maritime Organization (IMO) number of the vessel                         |
| `days`     | integer            | ✅*       |          | Number of days going backwards from time of request. Maximum value: **190**                |
| `fromdate` | string (date-time) |          |          | Use with `todate` **instead** of `days` to get data for a date period                      |
| `todate`   | string (date-time) |          |          | Use with `fromdate` **instead** of `days` to get data for a date period                    |
| `period`   | string             |          |          | Limit positions per vessel. Omit to get all available positions. Values: `hourly`, `daily` |
| `msgtype`  | string             |          | `simple` | Resolution of response. Values: `simple`, `extended` (extended includes weather data)      |
| `protocol` | string             |          | `xml`    | Response format. Values: `xml`, `csv`, `json`, `jsono`                                     |

*\* `shipid` (or `mmsi`/`imo`) and `days` (or `fromdate`/`todate`) are required.*

---

### Responses

| Code  | Description         |
|-------|---------------------|
| `200` | Successful Response |
| `400` | Bad Request         |
| `429` | Too Many Requests   |

---

### Response Sample (200 — Simple, JSON)

```json
[
    {
        "MMSI": "239982500",
        "IMO": "8348678",
        "STATUS": "5",
        "SPEED": "0",
        "LON": "23.726880",
        "LAT": "37.878850",
        "COURSE": "0",
        "HEADING": "320",
        "TIMESTAMP": "2021-02-08T12:57:01.000Z",
        "SHIP_ID": "4317723",
        "WIND_ANGLE": "326",
        "WIND_SPEED": "10",
        "WIND_TEMP": "23",
        "SIGNIFICANT_WAVE_HEIGHT": "2",
        "WIND_WAVE_DIRECTION": "314",
        "WIND_WAVE_HEIGHT": "1",
        "WIND_WAVE_PERIOD": "16",
        "SWELL_HEIGHT": "1",
        "SWELL_PERIOD": "26",
        "CURRENTS_ANGLE": "308",
        "CURRENTS_SPEED": "20",
        "SWELL_DIRECTION": "317"
    },
    {
        "MMSI": "249032000",
        "IMO": "9351098",
        "STATUS": "15",
        "SPEED": "2",
        "LON": "23.548990",
        "LAT": "37.903030",
        "COURSE": "160",
        "HEADING": "160",
        "TIMESTAMP": "2021-02-08T12:57:05.000Z",
        "SHIP_ID": "362849",
        "WIND_ANGLE": "333",
        "WIND_SPEED": "12",
        "WIND_TEMP": "23",
        "SIGNIFICANT_WAVE_HEIGHT": "3",
        "WIND_WAVE_DIRECTION": "320",
        "WIND_WAVE_HEIGHT": "3",
        "WIND_WAVE_PERIOD": "24",
        "SWELL_HEIGHT": "0",
        "SWELL_PERIOD": "31",
        "CURRENTS_ANGLE": "21",
        "CURRENTS_SPEED": "20",
        "SWELL_DIRECTION": "303"
    }
]
```

```json
{
    "errors": [
        {
            "code": "9c",
            "detail": "DAYS ABOVE ALLOWED LIMIT"
        }
    ]
}
```

```json
{
    "errors": [
        {
            "code": "2",
            "detail": "VESSEL MMSI OR IMO OR SHIPID MISSING"
        }
    ]
}
```

```json 
{
    "status": 200,
    "body": {
        "METADATA": {
            "CURSOR": "YEn89c27Kkqqhk_OcCopKldQIwuS9zG0iF6sl00Go-9WlBRsRkl6C_PjgkvG3to421HBszglCmcUopKRzSXAaA",
            "DATE_FROM": "2026-04-20 10:25:29",
            "DATE_TO": "2026-04-20 11:25:29"
        },
        "DATA": [
            {
                "MMSI": "244710331",
                "IMO": null,
                "SHIP_ID": "102986",
                "LAT": "51.115612",
                "LON": "3.749347",
                "SPEED": "70",
                "HEADING": "511",
                "COURSE": "220",
                "STATUS": "0",
                "TIMESTAMP": "2026-04-20T11:24:08",
                "DSRC": "TER",
                "UTC_SECONDS": "8",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "ADELAIDE",
                "SHIPTYPE": "79",
                "CALLSIGN": "PG4655",
                "FLAG": "NL",
                "LENGTH": "86.0",
                "WIDTH": "8.0",
                "GRT": null,
                "DWT": null,
                "DRAUGHT": "25",
                "YEAR_BUILT": null,
                "SHIP_COUNTRY": "NETHERLANDS",
                "SHIP_CLASS": null,
                "ROT": null,
                "TYPE_NAME": "Inland, Motor Freighter",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "OOIGEM",
                "ETA": "2026-04-21T04:09:00",
                "L_FORE": "0",
                "W_LEFT": "0",
                "LAST_PORT": "TERNEUZEN",
                "LAST_PORT_TIME": "2026-04-20T09:54:00",
                "LAST_PORT_ID": "133",
                "LAST_PORT_UNLOCODE": "NLTNZ",
                "LAST_PORT_COUNTRY": "NL",
                "CURRENT_PORT": "GHENT",
                "CURRENT_PORT_ID": "122",
                "CURRENT_PORT_UNLOCODE": "BEGNE",
                "CURRENT_PORT_COUNTRY": "BE",
                "NEXT_PORT_ID": null,
                "NEXT_PORT_UNLOCODE": null,
                "NEXT_PORT_NAME": null,
                "NEXT_PORT_COUNTRY": null,
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "11",
                "AVG_SPEED": "7.5",
                "MAX_SPEED": "8.1000004"
            },
            {
                "MMSI": "203999338",
                "IMO": null,
                "SHIP_ID": "103036",
                "LAT": "48.120365",
                "LON": "16.772617",
                "SPEED": "11",
                "HEADING": "511",
                "COURSE": "284",
                "STATUS": "0",
                "TIMESTAMP": "2026-04-20T11:23:57",
                "DSRC": "TER",
                "UTC_SECONDS": "55",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "GRAFENAU",
                "SHIPTYPE": "79",
                "CALLSIGN": "OED2037",
                "FLAG": "AT",
                "LENGTH": "52.0",
                "WIDTH": "7.0",
                "GRT": null,
                "DWT": null,
                "DRAUGHT": "25",
                "YEAR_BUILT": "1976",
                "SHIP_COUNTRY": "AUSTRIA",
                "SHIP_CLASS": null,
                "ROT": null,
                "TYPE_NAME": "Inland, Pushtow, one cargo barge",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "WILDUNGSMAUER",
                "ETA": "2026-04-22T06:41:00",
                "L_FORE": "8",
                "W_LEFT": "3",
                "LAST_PORT": "WILDUNGSMAUER",
                "LAST_PORT_TIME": "2026-04-20T09:28:00",
                "LAST_PORT_ID": "3805",
                "LAST_PORT_UNLOCODE": "ATWIL",
                "LAST_PORT_COUNTRY": "AT",
                "CURRENT_PORT": null,
                "CURRENT_PORT_ID": null,
                "CURRENT_PORT_UNLOCODE": null,
                "CURRENT_PORT_COUNTRY": null,
                "NEXT_PORT_ID": "3805",
                "NEXT_PORT_UNLOCODE": "ATWIL",
                "NEXT_PORT_NAME": "WILDUNGSMAUER",
                "NEXT_PORT_COUNTRY": "AT",
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "2",
                "AVG_SPEED": "7.0999999",
                "MAX_SPEED": "7.9000001"
            },
            {
                "MMSI": "203999339",
                "IMO": null,
                "SHIP_ID": "103037",
                "LAT": "48.251007",
                "LON": "14.430942",
                "SPEED": "0",
                "HEADING": "511",
                "COURSE": "184",
                "STATUS": "0",
                "TIMESTAMP": "2026-04-20T11:24:14",
                "DSRC": "TER",
                "UTC_SECONDS": "14",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "GREIFENSTEIN",
                "SHIPTYPE": "79",
                "CALLSIGN": "OED2027",
                "FLAG": "AT",
                "LENGTH": "41.0",
                "WIDTH": "6.0",
                "GRT": null,
                "DWT": null,
                "DRAUGHT": "16",
                "YEAR_BUILT": null,
                "SHIP_COUNTRY": "AUSTRIA",
                "SHIP_CLASS": null,
                "ROT": null,
                "TYPE_NAME": "Inland, Pushtow, one cargo barge",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "GREIN",
                "ETA": "",
                "L_FORE": "8",
                "W_LEFT": "4",
                "LAST_PORT": "LINZ",
                "LAST_PORT_TIME": "2026-04-20T10:46:00",
                "LAST_PORT_ID": "17363",
                "LAST_PORT_UNLOCODE": "ATLNZ",
                "LAST_PORT_COUNTRY": "AT",
                "CURRENT_PORT": null,
                "CURRENT_PORT_ID": null,
                "CURRENT_PORT_UNLOCODE": null,
                "CURRENT_PORT_COUNTRY": null,
                "NEXT_PORT_ID": "25738",
                "NEXT_PORT_UNLOCODE": "ATGRN",
                "NEXT_PORT_NAME": "GREIN",
                "NEXT_PORT_COUNTRY": "AT",
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "2",
                "AVG_SPEED": "5.6999998",
                "MAX_SPEED": "6.0"
            },
            {
                "MMSI": "203999361",
                "IMO": null,
                "SHIP_ID": "103059",
                "LAT": "48.192745",
                "LON": "15.062400",
                "SPEED": "1",
                "HEADING": "511",
                "COURSE": "157",
                "STATUS": "5",
                "TIMESTAMP": "2026-04-20T11:06:16",
                "DSRC": "TER",
                "UTC_SECONDS": "14",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "SARMINGSTEIN",
                "SHIPTYPE": "79",
                "CALLSIGN": "OED2305",
                "FLAG": "AT",
                "LENGTH": "30.0",
                "WIDTH": "7.0",
                "GRT": null,
                "DWT": null,
                "DRAUGHT": "21",
                "YEAR_BUILT": null,
                "SHIP_COUNTRY": "AUSTRIA",
                "SHIP_CLASS": null,
                "ROT": null,
                "TYPE_NAME": "Inland, Motor Freighter",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "YBBS",
                "ETA": "2026-04-12T14:00:00",
                "L_FORE": "14",
                "W_LEFT": "5",
                "LAST_PORT": "YBBS PERSENBEUG",
                "LAST_PORT_TIME": "2025-06-27T14:37:00",
                "LAST_PORT_ID": "2755",
                "LAST_PORT_UNLOCODE": "ATPBU",
                "LAST_PORT_COUNTRY": "AT",
                "CURRENT_PORT": "YBBS PERSENBEUG",
                "CURRENT_PORT_ID": "2755",
                "CURRENT_PORT_UNLOCODE": "ATPBU",
                "CURRENT_PORT_COUNTRY": "AT",
                "NEXT_PORT_ID": "2755",
                "NEXT_PORT_UNLOCODE": "ATPBU",
                "NEXT_PORT_NAME": "YBBS PERSENBEUG",
                "NEXT_PORT_COUNTRY": "AT",
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "31",
                "AVG_SPEED": "6.0",
                "MAX_SPEED": "0.0"
            },
            {
                "MMSI": "203999367",
                "IMO": null,
                "SHIP_ID": "103065",
                "LAT": "48.193005",
                "LON": "15.061235",
                "SPEED": "1",
                "HEADING": "511",
                "COURSE": "168",
                "STATUS": "5",
                "TIMESTAMP": "2026-04-20T11:05:30",
                "DSRC": "TER",
                "UTC_SECONDS": "28",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "KRAEMPELSTEIN",
                "SHIPTYPE": "79",
                "CALLSIGN": "OED2311",
                "FLAG": "AT",
                "LENGTH": "28.0",
                "WIDTH": "7.0",
                "GRT": null,
                "DWT": null,
                "DRAUGHT": "17",
                "YEAR_BUILT": null,
                "SHIP_COUNTRY": "AUSTRIA",
                "SHIP_CLASS": null,
                "ROT": null,
                "TYPE_NAME": "Inland, Motor Freighter",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "ASTEN",
                "ETA": "2026-12-19T12:00:00",
                "L_FORE": "11",
                "W_LEFT": "6",
                "LAST_PORT": "YBBS PERSENBEUG",
                "LAST_PORT_TIME": "2025-10-22T16:15:00",
                "LAST_PORT_ID": "2755",
                "LAST_PORT_UNLOCODE": "ATPBU",
                "LAST_PORT_COUNTRY": "AT",
                "CURRENT_PORT": "YBBS PERSENBEUG",
                "CURRENT_PORT_ID": "2755",
                "CURRENT_PORT_UNLOCODE": "ATPBU",
                "CURRENT_PORT_COUNTRY": "AT",
                "NEXT_PORT_ID": null,
                "NEXT_PORT_UNLOCODE": null,
                "NEXT_PORT_NAME": null,
                "NEXT_PORT_COUNTRY": null,
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "54",
                "AVG_SPEED": "7.6999998",
                "MAX_SPEED": "0.0"
            },
            {
                "MMSI": "203999389",
                "IMO": null,
                "SHIP_ID": "103085",
                "LAT": "48.313572",
                "LON": "14.326522",
                "SPEED": "22",
                "HEADING": "511",
                "COURSE": "311",
                "STATUS": "0",
                "TIMESTAMP": "2026-04-20T11:23:36",
                "DSRC": "TER",
                "UTC_SECONDS": "36",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "DEGGENDORF",
                "SHIPTYPE": "79",
                "CALLSIGN": "OED9010",
                "FLAG": "AT",
                "LENGTH": "100.0",
                "WIDTH": "11.0",
                "GRT": null,
                "DWT": null,
                "DRAUGHT": "2",
                "YEAR_BUILT": "1980",
                "SHIP_COUNTRY": "AUSTRIA",
                "SHIP_CLASS": null,
                "ROT": null,
                "TYPE_NAME": "Inland, Pushtow, one cargo barge",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "LINZ",
                "ETA": "2026-03-24T09:00:00",
                "L_FORE": "0",
                "W_LEFT": "5",
                "LAST_PORT": "ENNS",
                "LAST_PORT_TIME": "2026-04-12T18:51:00",
                "LAST_PORT_ID": "18986",
                "LAST_PORT_UNLOCODE": "ATENA",
                "LAST_PORT_COUNTRY": "AT",
                "CURRENT_PORT": "LINZ",
                "CURRENT_PORT_ID": "17363",
                "CURRENT_PORT_UNLOCODE": "ATLNZ",
                "CURRENT_PORT_COUNTRY": "AT",
                "NEXT_PORT_ID": "17363",
                "NEXT_PORT_UNLOCODE": "ATLNZ",
                "NEXT_PORT_NAME": "LINZ",
                "NEXT_PORT_COUNTRY": "AT",
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "68",
                "AVG_SPEED": "5.8000002",
                "MAX_SPEED": "7.5"
            },
            {
                "MMSI": "203999390",
                "IMO": null,
                "SHIP_ID": "103086",
                "LAT": "47.760918",
                "LON": "18.100082",
                "SPEED": "0",
                "HEADING": "511",
                "COURSE": "189",
                "STATUS": "5",
                "TIMESTAMP": "2026-04-20T11:21:42",
                "DSRC": "TER",
                "UTC_SECONDS": "40",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "GRAZ",
                "SHIPTYPE": "79",
                "CALLSIGN": "OED3075",
                "FLAG": "AT",
                "LENGTH": "23.0",
                "WIDTH": "9.0",
                "GRT": null,
                "DWT": null,
                "DRAUGHT": "20",
                "YEAR_BUILT": null,
                "SHIP_COUNTRY": "AUSTRIA",
                "SHIP_CLASS": null,
                "ROT": null,
                "TYPE_NAME": "Inland, Pushtow, one cargo barge",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "KOMARNO",
                "ETA": "2026-04-13T17:00:00",
                "L_FORE": "76",
                "W_LEFT": "15",
                "LAST_PORT": "KOMAROM",
                "LAST_PORT_TIME": "2026-04-13T13:38:00",
                "LAST_PORT_ID": "24575",
                "LAST_PORT_UNLOCODE": "HUKOM",
                "LAST_PORT_COUNTRY": "HU",
                "CURRENT_PORT": "KOMARNO",
                "CURRENT_PORT_ID": "2500",
                "CURRENT_PORT_UNLOCODE": "SKKNA",
                "CURRENT_PORT_COUNTRY": "SK",
                "NEXT_PORT_ID": "2500",
                "NEXT_PORT_UNLOCODE": "SKKNA",
                "NEXT_PORT_NAME": "KOMARNO",
                "NEXT_PORT_COUNTRY": "SK",
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "1",
                "AVG_SPEED": "5.8000002",
                "MAX_SPEED": "7.0"
            },
            {
                "MMSI": "203999392",
                "IMO": null,
                "SHIP_ID": "103088",
                "LAT": "43.860481",
                "LON": "25.960276",
                "SPEED": "0",
                "HEADING": "511",
                "COURSE": "0",
                "STATUS": "0",
                "TIMESTAMP": "2026-04-20T11:21:37",
                "DSRC": "TER",
                "UTC_SECONDS": "37",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "KREMS",
                "SHIPTYPE": "79",
                "CALLSIGN": "OED3005",
                "FLAG": "AT",
                "LENGTH": "55.0",
                "WIDTH": "9.0",
                "GRT": null,
                "DWT": null,
                "DRAUGHT": "2",
                "YEAR_BUILT": null,
                "SHIP_COUNTRY": "AUSTRIA",
                "SHIP_CLASS": null,
                "ROT": null,
                "TYPE_NAME": "Inland, Pushtow, four cargo barges",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "CHERNOVODA",
                "ETA": "2026-11-28T03:00:00",
                "L_FORE": "30",
                "W_LEFT": "4",
                "LAST_PORT": "RUSE",
                "LAST_PORT_TIME": "2026-02-07T05:25:00",
                "LAST_PORT_ID": "18536",
                "LAST_PORT_UNLOCODE": "BGRDU",
                "LAST_PORT_COUNTRY": "BG",
                "CURRENT_PORT": "RUSE",
                "CURRENT_PORT_ID": "18536",
                "CURRENT_PORT_UNLOCODE": "BGRDU",
                "CURRENT_PORT_COUNTRY": "BG",
                "NEXT_PORT_ID": null,
                "NEXT_PORT_UNLOCODE": null,
                "NEXT_PORT_NAME": null,
                "NEXT_PORT_COUNTRY": null,
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "8",
                "AVG_SPEED": "5.5",
                "MAX_SPEED": "5.9000001"
            },
            {
                "MMSI": "205227090",
                "IMO": null,
                "SHIP_ID": "104399",
                "LAT": "51.819801",
                "LON": "4.717825",
                "SPEED": "0",
                "HEADING": "511",
                "COURSE": null,
                "STATUS": "5",
                "TIMESTAMP": "2026-04-20T11:21:55",
                "DSRC": "TER",
                "UTC_SECONDS": "55",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "DONAU",
                "SHIPTYPE": "31",
                "CALLSIGN": "OT2270",
                "FLAG": "BE",
                "LENGTH": "99.0",
                "WIDTH": "21.0",
                "GRT": null,
                "DWT": null,
                "DRAUGHT": "23",
                "YEAR_BUILT": null,
                "SHIP_COUNTRY": "BELGIUM",
                "SHIP_CLASS": null,
                "ROT": null,
                "TYPE_NAME": "Inland, Pushtow, two cargo barges",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "OSS",
                "ETA": "2026-04-13T09:16:00",
                "L_FORE": "176",
                "W_LEFT": "6",
                "LAST_PORT": "WIJK BIJ DUURSTEDE",
                "LAST_PORT_TIME": "2026-04-19T04:55:00",
                "LAST_PORT_ID": "2128",
                "LAST_PORT_UNLOCODE": "NLWBD",
                "LAST_PORT_COUNTRY": "NL",
                "CURRENT_PORT": "DORDRECHT",
                "CURRENT_PORT_ID": "1826",
                "CURRENT_PORT_UNLOCODE": "NLDOR",
                "CURRENT_PORT_COUNTRY": "NL",
                "NEXT_PORT_ID": "22235",
                "NEXT_PORT_UNLOCODE": "NLOSS",
                "NEXT_PORT_NAME": "OSS",
                "NEXT_PORT_COUNTRY": "NL",
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "51",
                "AVG_SPEED": "6.4000001",
                "MAX_SPEED": "6.5999999"
            },
            {
                "MMSI": "205058100",
                "IMO": "9253636",
                "SHIP_ID": "105971",
                "LAT": "50.999283",
                "LON": "4.372317",
                "SPEED": "0",
                "HEADING": "150",
                "COURSE": "153",
                "STATUS": "0",
                "TIMESTAMP": "2026-04-20T11:22:37",
                "DSRC": "TER",
                "UTC_SECONDS": "34",
                "MARKET": "DRY BREAKBULK",
                "SHIPNAME": "LAILA M",
                "SHIPTYPE": "79",
                "CALLSIGN": "OT3793",
                "FLAG": "BE",
                "LENGTH": "135.0",
                "WIDTH": "11.0",
                "GRT": "3461",
                "DWT": "0",
                "DRAUGHT": "18",
                "YEAR_BUILT": "2002",
                "SHIP_COUNTRY": "BELGIUM",
                "SHIP_CLASS": "HANDYSIZE",
                "ROT": "0",
                "TYPE_NAME": "Inland, Motor Freighter",
                "AIS_TYPE_SUMMARY": "Cargo",
                "DESTINATION": "HEFBRUG VILVOORDE",
                "ETA": "2026-04-20T11:17:00",
                "L_FORE": "123",
                "W_LEFT": "8",
                "LAST_PORT": "WINTHAM",
                "LAST_PORT_TIME": "2026-04-20T10:35:00",
                "LAST_PORT_ID": "4233",
                "LAST_PORT_UNLOCODE": "BEWTH",
                "LAST_PORT_COUNTRY": "BE",
                "CURRENT_PORT": "KAPELLE-OP-DEN-BOS",
                "CURRENT_PORT_ID": "4199",
                "CURRENT_PORT_UNLOCODE": "BEKPB",
                "CURRENT_PORT_COUNTRY": "BE",
                "NEXT_PORT_ID": null,
                "NEXT_PORT_UNLOCODE": null,
                "NEXT_PORT_NAME": null,
                "NEXT_PORT_COUNTRY": null,
                "ETA_CALC": null,
                "ETA_UPDATED": null,
                "DISTANCE_TO_GO": "0",
                "DISTANCE_TRAVELLED": "4",
                "AVG_SPEED": "6.5999999",
                "MAX_SPEED": "7.0"
            }
        ]
    },
    "elapsed_ms": 999
}
```

```json
{
    "status": 429,
    "body": {
        "errors": [
            {
                "code": "5",
                "detail": "ABOVE SERVICE CALL LIMIT"
            }
        ]
    },
    "elapsed_ms": 846
}
```

```json
{
    "METADATA": {
        "CURSOR": "",
        "DATE_FROM": "2026-04-20 12:43:50",
        "DATE_TO": "2026-04-20 12:48:50"
    },
    "DATA": []
}
```
