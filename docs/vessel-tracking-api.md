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

## RAW Page Deatils:

Title: AIS API Documentation | AIS Marine Traffic

URL Source: https://servicedocs.marinetraffic.com/tag/AIS-API/

Markdown Content:

# AIS API Documentation | AIS Marine Traffic

[![Image 1: MarineTraffic logo](https://www.marinetraffic.com/img/logos/Logo.png)](https://www.marinetraffic.com/en/ais/home/centerx:-12.0/centery:25.0/zoom:4)

* Overview
* Solutions
    * Containers API
    * MT Inbox

* Vessel Positions
    * AIS API
        * get Vessel Positions
        * get Vessel Positions in an Area of Interest
        * get Single Vessel Positions

    * Vessel Historical Track
        * get Single Vessel Historical Positions
        * get Vessel Historical Positions in an Area

    * Vessel Positions (Legacy API)
        * get Vessel Positions of a Static Fleet
        * get Vessel Positions of a Dynamic Fleet
        * get Vessel Positions within a Port
        * get Vessel Positions in a Predefined Bounding Box
        * get Vessel Positions in a Dynamic Bounding Box
        * get Single Vessel Positions
        * get Vessel Positions in a Custom Area

* Events
    * Single Vessel Events
        * get Single Vessel Port Calls
        * get Single Vessel Events
        * get Single Vessel Berth Calls

    * Port Events
        * get Port Calls
        * get Berth Calls

* Vessels Data
    * Vessel Information
        * get Vessel Photo
        * get Vessel Particulars (Legacy API)

    * Search Vessel
        * get Search Vessel by Identifier
        * get Search Vessel by Name

* Voyage Info
    * Voyage Information
        * get Single Vessel Voyage Forecast
        * get Fleet Voyage Forecast
        * get Single Vessel Predictive Destinations
        * get Fleet Predictive Destinations
        * get Vessel ETA to Port

    * Ports Information
        * get Expected Port Arrivals
        * get Expected Country Arrivals
        * get Predictive Port Arrivals
        * get Port Congestion

    * Routing Information
        * get Vessel Route to Port
        * get Distance to Port

* Geographical Info
    * Reverse Geocoding
        * get Reverse Geocoding of a Single Point

* Power User
    * Fleets
        * get Modify Fleet
        * get Vessels in Fleet
        * get List Fleets
        * get Clear Fleet

    * Balances
        * get Credits Balance

    * Passage Plans
        * post Import Passage Plan

# [](https://servicedocs.marinetraffic.com/tag/AIS-API)AIS API

## [](https://servicedocs.marinetraffic.com/tag/AIS-API#operation/exportvessels______)Vessel Positions

**The latest version of this API endpoint, including significant improvements, is
available [here](https://developers.kpler.com/spec/dfdcdd47-ed9d-4fe1-bcc1-1f736a0a0266).

Please contact your Customer Success Manager to request access to the new endpoint.**

Track vessels of interest anywhere in the world.

**Notes**

* Information
  about [AIS-transmitted data](https://support.marinetraffic.com/en/articles/9552860-what-kind-of-information-is-ais-transmitted)
* **SPEED** speed over ground returned in (knots x10)
* **SPEED** and **COURSE** values for base stations are represented by zero (0)
* **TIMESTAMP** UTC second when the report was generated (0-59)
* **HEADING** true heading in degrees (0-359) (values -1 or 511 indicate lack of data)
* **HEADING** for SAR Aircrafts contains the Altitude of the aircraft (“STATUS” = 97)
* **DSRC** describes whether the transmitted AIS message was received by a terrestrial (TER), satellite (SAT) or
  roaming (ROAM) AIS antenna.
* The **timespan** parameter lets you specify how far back in time the API should look for the latest available vessel
  positions. Default value is set to 5 minutes and can be adjusted to a maximum of 1,440 minutes (24 hours).
* More information about response
  parameters: [STATUS](https://support.marinetraffic.com/en/articles/9552867-what-is-the-significance-of-the-ais-navigational-status-values), [SHIPTYPE](https://support.marinetraffic.com/en/articles/9552866-what-is-the-significance-of-the-ais-shiptype-number)
* The **frequency of allowed API calls** is specific to your API key and is detailed in your contract as a number of
  successful calls per time period. For example “2 calls per minute”.

Regardless of this agreed limit, each API key is technically restricted to a maximum of 100 total (including successful
and unsuccessful) requests per minute to ensure system stability.

##### path Parameters

api_key

required string

API key: 40-character hexadecimal number

##### query Parameters

v

required integer

Use latest version **9**
timespan integer

Default:5

Overrides the default timespan
vesseltypeid integer

Filter vessels based on vessel types, comma separated ids supported

[more](https://support.marinetraffic.com/en/articles/9552888-what-is-the-significance-of-the-marinetraffic-ship-types)
cursor string

The pagination cursor provided in the metadata section of the previous response
limit integer

Default:2000

The limit of vessels per page (min=1000, max=5000)
protocol string

Default:"jsono"

Response type. Use one of the following:

* jsono
* csv

### Responses

**200**
Successful Response

**429**
Too Many Requests

get/exportvessels/{api_key}

https://services.marinetraffic.com/api/exportvessels/{api_key}

Try it ➔

### Response samples

* 200
* 429

Content type

application/json

Copy

Expand all Collapse all

`{"DATA": [{"MMSI": "538003913","IMO": "9470959","SHIP_ID": "713139","LAT": "37.388430","LON": "23.871230","SPEED": "6","HEADING": "104","COURSE": "41","STATUS": "0","TIMESTAMP": "2020-10-15T12:21:44.000Z","DSRC": "TER","UTC_SECONDS": "45","MARKET": "SUPPORTING VESSELS","SHIPNAME": "SUNNY STAR","SHIPTYPE": "89","CALLSIGN": "V7TZ6","FLAG": "MH","LENGTH": "184","WIDTH": "27.43","GRT": "23313","DWT": "37857","DRAUGHT": "95","YEAR_BUILT": "2010","SHIP_COUNTRY": "Marshall Is","SHIP_CLASS": "HANDYSIZE","ROT": "0","TYPE_NAME": "Oil/Chemical Tanker","AIS_TYPE_SUMMARY": "Tanker","DESTINATION": "FOR ORDERS","ETA": "2020-10-14T12:00:00.000Z","L_FORE": "5","W_LEFT": "5","CURRENT_PORT": "","LAST_PORT": "AGIOI THEODOROI","LAST_PORT_TIME": "2020-10-13T23:39:00.000Z","CURRENT_PORT_ID": "","CURRENT_PORT_UNLOCODE": "","CURRENT_PORT_COUNTRY": "","LAST_PORT_ID": "29","LAST_PORT_UNLOCODE": "GRAGT","LAST_PORT_COUNTRY": "GR","NEXT_PORT_ID": "","NEXT_PORT_UNLOCODE": "","NEXT_PORT_NAME": "","NEXT_PORT_COUNTRY": "","ETA_CALC": "","ETA_UPDATED": "","DISTANCE_TO_GO": "0","DISTANCE_TRAVELLED": "74","AVG_SPEED": "12.6","MAX_SPEED": "13.2"}],"METADATA": {"CURSOR": "abcdef","DATE_FROM": "2023-11-20 15:57:00","DATE_TO": "2023-11-20 16:57:00"}}`

## [](https://servicedocs.marinetraffic.com/tag/AIS-API#operation/exportvessels-custom-area_)Vessel Positions in an Area of Interest

**The latest version of this API endpoint, including significant improvements, is
available [here](https://developers.kpler.com/spec/dfdcdd47-ed9d-4fe1-bcc1-1f736a0a0266).

Please contact your Customer Success Manager to request access to the new endpoint.**

Track Vessels of interest in a predefined area of interest.

**Notes**

* Information
  about [AIS-transmitted data](https://support.marinetraffic.com/en/articles/9552860-what-kind-of-information-is-ais-transmitted)
* **SPEED** speed over ground returned in (knots x10)
* **SPEED** and **COURSE** values for base stations are represented by zero (0)
* **TIMESTAMP** UTC second when the report was generated (0-59)
* **HEADING** true heading in degrees (0-359) (values -1 or 511 indicate lack of data)
* **HEADING** for SAR Aircrafts contains the Altitude of the aircraft (“STATUS” = 97)
* **DSRC** describes whether the transmitted AIS message was received by a terrestrial (TER), satellite (SAT) or
  roaming (ROAM) AIS antenna.
* The **timespan** parameter lets you specify how far back in time the API should look for the latest available vessel
  positions. Default value is set to 5 minutes and can be adjusted to a maximum of 1,440 minutes (24 hours).
* More information about response
  parameters: [STATUS](https://support.marinetraffic.com/en/articles/9552867-what-is-the-significance-of-the-ais-navigational-status-values), [SHIPTYPE](https://support.marinetraffic.com/en/articles/9552866-what-is-the-significance-of-the-ais-shiptype-number)
* The **frequency of allowed API calls** is specific to your API key and is detailed in your contract as a number of
  successful calls per time period. For example “2 calls per minute”.

Regardless of this agreed limit, each API key is technically restricted to a maximum of 100 total (including successful
and unsuccessful) requests per minute to ensure system stability.

##### path Parameters

api_key

required string

API key: 40-character hexadecimal number

##### query Parameters

v

required integer

Use latest version **2**
timespan integer

Default:5

Overrides the default timespan
vesseltypeid integer

Filter vessels based on vessel types, comma separated ids supported

[more](https://support.marinetraffic.com/en/articles/9552888-what-is-the-significance-of-the-marinetraffic-ship-types)
cursor string

The pagination cursor provided in the metadata section of the previous response
limit integer

Default:2000

The limit of vessels per page (min=1000, max=5000)
protocol string

Default:"jsono"

Response type. Use one of the following:

* jsono
* csv

### Responses

**200**
Successful Response

**429**
Too Many Requests

get/exportvessels-custom-area/{api_key}

https://services.marinetraffic.com/api/exportvessels-custom-area/{api_key}

Try it ➔

### Response samples

* 200
* 429

Content type

application/json

Copy

Expand all Collapse all

`{"DATA": [{"MMSI": "538003913","IMO": "9470959","SHIP_ID": "713139","LAT": "37.388430","LON": "23.871230","SPEED": "6","HEADING": "104","COURSE": "41","STATUS": "0","TIMESTAMP": "2020-10-15T12:21:44.000Z","DSRC": "TER","UTC_SECONDS": "45","MARKET": "SUPPORTING VESSELS","SHIPNAME": "SUNNY STAR","SHIPTYPE": "89","CALLSIGN": "V7TZ6","FLAG": "MH","LENGTH": "184","WIDTH": "27.43","GRT": "23313","DWT": "37857","DRAUGHT": "95","YEAR_BUILT": "2010","SHIP_COUNTRY": "Marshall Is","SHIP_CLASS": "HANDYSIZE","ROT": "0","TYPE_NAME": "Oil/Chemical Tanker","AIS_TYPE_SUMMARY": "Tanker","DESTINATION": "FOR ORDERS","ETA": "2020-10-14T12:00:00.000Z","L_FORE": "5","W_LEFT": "5","CURRENT_PORT": "","LAST_PORT": "AGIOI THEODOROI","LAST_PORT_TIME": "2020-10-13T23:39:00.000Z","CURRENT_PORT_ID": "","CURRENT_PORT_UNLOCODE": "","CURRENT_PORT_COUNTRY": "","LAST_PORT_ID": "29","LAST_PORT_UNLOCODE": "GRAGT","LAST_PORT_COUNTRY": "GR","NEXT_PORT_ID": "","NEXT_PORT_UNLOCODE": "","NEXT_PORT_NAME": "","NEXT_PORT_COUNTRY": "","ETA_CALC": "","ETA_UPDATED": "","DISTANCE_TO_GO": "0","DISTANCE_TRAVELLED": "74","AVG_SPEED": "12.6","MAX_SPEED": "13.2"}],"METADATA": {"CURSOR": "abcdef","DATE_FROM": "2023-11-20 15:57:00","DATE_TO": "2023-11-20 16:57:00"}}`

## [](https://servicedocs.marinetraffic.com/tag/AIS-API#operation/exportvessel_)Single Vessel Positions

**The latest version of this API endpoint, including significant improvements, is
available [here](https://developers.kpler.com/spec/dfdcdd47-ed9d-4fe1-bcc1-1f736a0a0266).

Please contact your Customer Success Manager to request access to the new endpoint.**

Get the latest available position and voyage information for a single vessel.

**Notes**

* Information
  about [AIS-transmitted data](https://support.marinetraffic.com/en/articles/9552860-what-kind-of-information-is-ais-transmitted)
* **SPEED** speed over ground returned in (knots x10)
* **SPEED** and **COURSE** values for base stations are represented by zero (0)
* **TIMESTAMP** UTC second when the report was generated (0-59)
* **HEADING** true heading in degrees (0-359) (values -1 or 511 indicate lack of data)
* **HEADING** for SAR Aircrafts contains the Altitude of the aircraft (“STATUS” = 97)
* **DSRC** describes whether the transmitted AIS message was received by a terrestrial (TER), satellite (SAT) or
  roaming (ROAM) AIS antenna.
* The **timespan** parameter lets you specify how far back in time the API should look for the latest available vessel
  positions. Default value is set to 5 minutes and can be adjusted to a maximum of 1,440 minutes (24 hours).
* More information about response
  parameters: [STATUS](https://support.marinetraffic.com/en/articles/9552867-what-is-the-significance-of-the-ais-navigational-status-values), [SHIPTYPE](https://support.marinetraffic.com/en/articles/9552866-what-is-the-significance-of-the-ais-shiptype-number)
* The **frequency of allowed API calls** is specific to your API key and is detailed in your contract as a number of
  successful calls per time period. For example “2 calls per minute”.

Regardless of this agreed limit, each API key is technically restricted to a maximum of 100 total (including successful
and unsuccessful) requests per minute to ensure system stability.

##### path Parameters

api_key

required string

API key: 40-character hexadecimal number

##### query Parameters

v

required integer

Use latest version **6**
shipid

required integer

A uniquely assigned ID by MarineTraffic for the subject vessel

You can **instead** use imo or mmsi
imo integer

The International Maritime Organization (IMO) number of the vessel you wish to track
mmsi integer

The Maritime Mobile Service Identity (MMSI) of the vessel you wish to track
timespan integer

Default:5

Overrides the default timespan
protocol string

Default:"jsono"

Response type. Use one of the following:

* jsono
* csv

### Responses

**200**
Successful Response

**400**
Bad Request

**429**
Too Many Requests

get/exportvessel/{api_key}

https://services.marinetraffic.com/api/exportvessel/{api_key}

Try it ➔

### Response samples

* 200
* 400
* 429

Content type

application/json

Copy

Expand all Collapse all

`[{"MMSI": "538003913","IMO": "9470959","SHIP_ID": "713139","LAT": "37.388430","LON": "23.871230","SPEED": "6","HEADING": "104","COURSE": "41","STATUS": "0","TIMESTAMP": "2020-10-15T12:21:44.000Z","DSRC": "TER","UTC_SECONDS": "45","MARKET": "SUPPORTING VESSELS","SHIPNAME": "SUNNY STAR","SHIPTYPE": "89","CALLSIGN": "V7TZ6","FLAG": "MH","LENGTH": "184","WIDTH": "27.43","GRT": "23313","DWT": "37857","DRAUGHT": "95","YEAR_BUILT": "2010","SHIP_COUNTRY": "Marshall Is","SHIP_CLASS": "HANDYSIZE","ROT": "0","TYPE_NAME": "Oil/Chemical Tanker","AIS_TYPE_SUMMARY": "Tanker","DESTINATION": "FOR ORDERS","ETA": "2020-10-14T12:00:00.000Z","L_FORE": "5","W_LEFT": "5","CURRENT_PORT": "","LAST_PORT": "AGIOI THEODOROI","LAST_PORT_TIME": "2020-10-13T23:39:00.000Z","CURRENT_PORT_ID": "","CURRENT_PORT_UNLOCODE": "","CURRENT_PORT_COUNTRY": "","LAST_PORT_ID": "29","LAST_PORT_UNLOCODE": "GRAGT","LAST_PORT_COUNTRY": "GR","NEXT_PORT_ID": "","NEXT_PORT_UNLOCODE": "","NEXT_PORT_NAME": "","NEXT_PORT_COUNTRY": "","ETA_CALC": "","ETA_UPDATED": "","DISTANCE_TO_GO": "0","DISTANCE_TRAVELLED": "74","AVG_SPEED": "12.6","MAX_SPEED": "13.2"}]`

➔ Next to **Vessel Historical Track**


