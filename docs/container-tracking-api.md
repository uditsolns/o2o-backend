Title: Container Tracking API Documentation

URL Source: https://container-tracking.marinetraffic.com/

Markdown Content:

# Container Tracking API Documentation

[![Image 1: MarineTraffic logo](https://www.marinetraffic.com/img/logos/Logo.png)](https://www.kpler.com/product/maritime/container-tracking)

Containers API

Search

* Container Intelligence API
    * [Terminal Congestion](https://container-tracking.marinetraffic.com/v2#tag/Terminal-Congestion)
        * [get List terminal congestions](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions)

    * [Shipping Calls](https://container-tracking.marinetraffic.com/v2#tag/Shipping-Calls)
        * [get List shipping calls](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls)

* Container Tracking API
    * [Tracking requests](https://container-tracking.marinetraffic.com/v2#tag/Tracking-requests)
        * [post Create tracking requests](https://container-tracking.marinetraffic.com/v2#operation/createTrackingRequests)
        * [get List tracking requests](https://container-tracking.marinetraffic.com/v2#operation/listTrackingRequests)
        * [get Get tracking request details](https://container-tracking.marinetraffic.com/v2#operation/fetchTrackingRequestById)
        * [post (Un)Archive tracking requests](https://container-tracking.marinetraffic.com/v2#operation/archiveTrackingRequests)

    * [Shipments](https://container-tracking.marinetraffic.com/v2#tag/Shipments)
        * [get List shipments](https://container-tracking.marinetraffic.com/v2#operation/listShipments)
        * [get Get shipment summary](https://container-tracking.marinetraffic.com/v2#operation/fetchShipmentTrackDetails)
        * [get Get shipment detailed milestones](https://container-tracking.marinetraffic.com/v2#operation/fetchShipmentTransportationTimeline)
        * [post Replace all shipment tags](https://container-tracking.marinetraffic.com/v2#operation/syncShipmentTags)
        * [post Add tags to a shipment](https://container-tracking.marinetraffic.com/v2#operation/addShipmentTags)
        * [post Remove tags from a shipment](https://container-tracking.marinetraffic.com/v2#operation/removeShipmentTags)

    * [Webhook events](https://container-tracking.marinetraffic.com/v2#tag/Webhook-events)
        * [Event shipment_updated](https://container-tracking.marinetraffic.com/v2#operation/webhookShipmentUpdated)
        * [Event tracking_requested_succeeded](https://container-tracking.marinetraffic.com/v2#operation/webhookTrackingRequestSucceeded)
        * [Event tracking_requested_failed](https://container-tracking.marinetraffic.com/v2#operation/webhookTrackingRequestFailed)

* Changelog
    * [2026-02-20 (1.3.0)](https://container-tracking.marinetraffic.com/v2#tag/2026-02-20-1.3.0)
    * [2025-11-26 (1.2.2)](https://container-tracking.marinetraffic.com/v2#tag/2025-11-26-1.2.2)
    * [2025-11-05 (1.2.1)](https://container-tracking.marinetraffic.com/v2#tag/2025-11-05-1.2.1)
    * [2025-10-23 (1.2.0)](https://container-tracking.marinetraffic.com/v2#tag/2025-10-23-1.2.0)
    * [2025-09-17 (1.1.0)](https://container-tracking.marinetraffic.com/v2#tag/2025-09-17-1.1.0)
    * [2025-08-22 (1.0.3)](https://container-tracking.marinetraffic.com/v2#tag/2025-08-22-1.0.3)
    * [2025-07-16 (1.0.2)](https://container-tracking.marinetraffic.com/v2#tag/2025-07-16-1.0.2)
    * [2025-07-02 (1.0.1)](https://container-tracking.marinetraffic.com/v2#tag/2025-07-02-1.0.1)
    * [2025-06-17 (1.0.0)](https://container-tracking.marinetraffic.com/v2#tag/2025-06-17-1.0.0)
    * [2025-06-10](https://container-tracking.marinetraffic.com/v2#tag/2025-06-10)
    * [2025-05-26](https://container-tracking.marinetraffic.com/v2#tag/2025-05-26)

# MarineTraffic Containers API(1.3.0)

Download OpenAPI
specification:[Download](blob:https://container-tracking.marinetraffic.com/7883beb5-f07c-4985-b500-08209061a930)

E-mail:[info@marinetraffic.com](mailto:info@marinetraffic.com)
License:[Apache 2.0](http://www.apache.org/licenses/LICENSE-2.0.html)[Terms of Service](https://www.marinetraffic.com/en/p/terms)

### Introduction

The MarineTraffic Containers API is your way to access state-of-the-art real-time visibility for containers logistics.

For more information, please visit
the [solution landing page](https://www.kpler.com/product/maritime/container-tracking).

### Get your API key

To get access to the solution, please reach out to [sales@kpler.com](mailto:sales@kpler.com).

If you have already issued one, sign in to marinetraffic.com and go
to [My API Services page](https://www.marinetraffic.com/en/users/my_account/api/account) to retrieve it.

The API key must be specified in all requests as the value of the API header `X-Container-API-Key`.

### Rate Limits

The API enforces a rate limit of 500 requests per minute.

Exceeding this limit will result in a 429 Too Many Requests HTTP response.

* Authenticated requests are tracked based on the associated API key.
* Unauthenticated requests are monitored based on the originating IP address.

### IP Whitelisting

To enhance network security, you may whitelist the following IP addresses:

* API Requests: All requests to the API will be directed exclusively to the following IP address(es): 13.248.154.30,
  76.223.25.242.
* Webhooks: Webhooks will only be dispatched from the following IP address(es): 3.251.15.122, 52.215.44.244,
  54.195.123.104.

# [](https://container-tracking.marinetraffic.com/v2#tag/Terminal-Congestion)Terminal Congestion

Access near real-time terminal congestion insights including:

* **Live Congestion**: Vessel queues and congestion index.
* **Performance Metrics**: 7d average waiting and stay times, performance index.
* **Carrier / Services Information**: List of carriers operating at the terminal and their services.
* **Related Data**: Associated port and terminal details when requested.

Data is updated every 30 minutes to provide near real-time terminal conditions.

## [](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions)List terminal congestions

Retrieve a list of terminal congestion data with live metrics and performance insights.

The list can be filtered using the following filter groups:

* **Performance Insights**: `performanceIndex`, `avgWaitingTime`, `avgStayTime`

* **Live Metrics**: `congestionIndex`, `vesselsWaiting`, `vesselsWaitingAvgTime`, `vesselsStay`, `vesselsStayAvgTime`

* **Terminal Properties**: `id`, `smdgCode`, `name`

* **Port Properties**: `id`, `unlocode`, `name`

Results are paginated with 50 entries per page.

Security**ApiKeyAuth**

Request

##### query Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[performanceInsights.performanceIndex]&t=request)
filter[performanceInsights.performanceIndex]number[ 0 .. 10 ]

Filter by performance index (0-10).

Example:filter[performanceInsights.performanceIndex]=7.5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[performanceInsights.performanceIndex][operator]&t=request)
filter[performanceInsights.performanceIndex][operator]number[ 0 .. 10 ]

Filter by performance index (0-10). Supported operators: eq, gte, lte.

Example:filter[performanceInsights.performanceIndex][operator]=7.5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[performanceInsights.avgWaitingTime]&t=request)
filter[performanceInsights.avgWaitingTime]number[ 0 .. 10000 ]

Filter by average waiting time in hours (0-10000).

Example:filter[performanceInsights.avgWaitingTime]=12.5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[performanceInsights.avgWaitingTime][operator]&t=request)
filter[performanceInsights.avgWaitingTime][operator]number[ 0 .. 10000 ]

Filter by average waiting time in hours (0-10000). Supported operators eq, gte, lte.

Example:filter[performanceInsights.avgWaitingTime][operator]=12.5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[performanceInsights.avgStayTime]&t=request)
filter[performanceInsights.avgStayTime]number[ 0 .. 10000 ]

Filter by average stay time in hours (0-10000).

Example:filter[performanceInsights.avgStayTime]=48.3
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[performanceInsights.avgStayTime][operator]&t=request)
filter[performanceInsights.avgStayTime][operator]number[ 0 .. 10000 ]

Filter by average stay time in hours (0-10000). Supported operators: eq, gte, lte.

Example:filter[performanceInsights.avgStayTime][operator]=48.3
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.congestionIndex]&t=request)
filter[liveMetrics.congestionIndex]number[ 0 .. 10 ]

Filter by congestion index (0-10).

Example:filter[liveMetrics.congestionIndex]=6.2
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.congestionIndex][operator]&t=request)
filter[liveMetrics.congestionIndex][operator]number[ 0 .. 10 ]

Filter by congestion index (0-10). Supported operators: eq, gte, lte.

Example:filter[liveMetrics.congestionIndex][operator]=6.2
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.vesselsWaiting]&t=request)
filter[liveMetrics.vesselsWaiting]integer[ 0 .. 10000 ]

Filter by number of vessels currently waiting (0-10000).

Example:filter[liveMetrics.vesselsWaiting]=5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.vesselsWaiting][operator]&t=request)
filter[liveMetrics.vesselsWaiting][operator]integer[ 0 .. 10000 ]

Filter by number of vessels currently waiting (0-10000). Supported operators: eq, gte, lte.

Example:filter[liveMetrics.vesselsWaiting][operator]=5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.vesselsWaitingAvgTime]&t=request)
filter[liveMetrics.vesselsWaitingAvgTime]number

Filter by average waiting time in hours of the vessels currently waiting to berth at this terminal.

Example:filter[liveMetrics.vesselsWaitingAvgTime]=12.5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.vesselsWaitingAvgTime][operator]&t=request)
filter[liveMetrics.vesselsWaitingAvgTime][operator]number

Filter by average waiting time in hours of the vessels currently waiting to berth at this terminal. Supported operators:
eq, gt, gte, lt, lte.

Example:filter[liveMetrics.vesselsWaitingAvgTime][operator]=12.5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.vesselsStay]&t=request)
filter[liveMetrics.vesselsStay]integer

Filter by number of vessels currently staying at the terminal.

Example:filter[liveMetrics.vesselsStay]=5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.vesselsStay][operator]&t=request)
filter[liveMetrics.vesselsStay][operator]integer

Filter by number of vessels currently staying at the terminal. Supported operators: eq, gt, gte, lt, lte.

Example:filter[liveMetrics.vesselsStay][operator]=5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.vesselsStayAvgTime]&t=request)
filter[liveMetrics.vesselsStayAvgTime]number

Filter by average stay time in hours of the vessels currently staying at the terminal.

Example:filter[liveMetrics.vesselsStayAvgTime]=12.5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[liveMetrics.vesselsStayAvgTime][operator]&t=request)
filter[liveMetrics.vesselsStayAvgTime][operator]number

Filter by average stay time in hours of the vessels currently staying at the terminal. Supported operators: eq, gt, gte,
lt, lte.

Example:filter[liveMetrics.vesselsStayAvgTime][operator]=12.5
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[terminal.id]&t=request)
filter[terminal.id]string[ 1 .. 9 ] characters^[1-9]\d*$

Filter by terminal MT ID, exact match.

Example:filter[terminal.id]=101
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[terminal.id][]&t=request)
filter[terminal.id][]Array of integers<= 5 items

Filter by terminal MT IDs (max 5 values, no leading zeros), exact match only.

Example:filter[terminal.id][]=101&filter[terminal.id][]=102&filter[terminal.id][]=103
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[terminal.name]&t=request)
filter[terminal.name]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by terminal name. Exact match only.

Example:filter[terminal.name]=HAMBURG
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[terminal.name][operator]&t=request)
filter[terminal.name][operator]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by terminal name. Supported operators: eq (exact), contains (partial match), ne (not equals)

Example:filter[terminal.name][operator]=HAMBURG
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[terminal.name][]&t=request)
filter[terminal.name][]Array of strings<= 5 items

Filter by terminal name (max 5 values, exact match only).
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[terminal.smdgCode]&t=request)
filter[terminal.smdgCode]string[ 3 .. 6 ] characters^[A-Za-z0-9]+$

Filter by terminal SMDG code. Exact match only.

Example:filter[terminal.smdgCode]=HTG
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[terminal.smdgCode][operator]&t=request)
filter[terminal.smdgCode][operator]string[ 3 .. 6 ] characters^[A-Za-z0-9]+$

Filter by terminal SMDG code. Supported operators: eq (exact), ne (not equals)

Example:filter[terminal.smdgCode][operator]=HTG
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[terminal.smdgCode][]&t=request)
filter[terminal.smdgCode][]Array of strings<= 5 items

Filter by terminal SMDG code (exact match only, max 5 values).

Example:filter[terminal.smdgCode][]=HTG&filter[terminal.smdgCode][]=AET
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[port.id]&t=request)
filter[port.id]string[ 1 .. 9 ] characters^[1-9]\d*$

Filter by port MT ID.

Example:filter[port.id]=202
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[port.id][]&t=request)
filter[port.id][]Array of strings<= 5 items

Filter by port MT IDs (max 5 values, no leading zeros)

Example:filter[port.id][]=201&filter[port.id][]=202&filter[port.id][]=203
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[port.name]&t=request)
filter[port.name]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by port name. Exact match only.

Example:filter[port.name]=Hamburg
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[port.name][operator]&t=request)
filter[port.name][operator]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by port name. Supported operators: eq (exact), contains (partial match), ne (not equals)

Example:filter[port.name][operator]=Hamburg
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[port.name][]&t=request)
filter[port.name][]Array of strings<= 5 items

Filter by port name (max 5 values, exact match only).

Example:filter[port.name][]=Hamburg&filter[port.name][]=Rotterdam
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[port.unlocode]&t=request)
filter[port.unlocode]string= 5 characters^[A-Z0-9]+$

Filter by port UN/LOCODE. Exact match only.

Example:filter[port.unlocode]=DEHAM
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[port.unlocode][operator]&t=request)
filter[port.unlocode][operator]string= 5 characters^[A-Z0-9]+$

Filter by port UN/LOCODE. Supported operators: eq (exact), ne (not equals)

Example:filter[port.unlocode][operator]=DEHAM
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=filter[port.unlocode][]&t=request)
filter[port.unlocode][]Array of strings<= 5 items

Filter by port UN/LOCODE (exact match only, max 5 values)

Example:filter[port.unlocode][]=DEHAM&filter[port.unlocode][]=BEANR
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=include&t=request)
include string

Comma-separated list of related resources to include in the response.

Enum:"terminals""ports""terminals,ports"

Example:include=terminals,ports
[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=query&path=page&t=request)page
integer>= 1

Default:1

Page number for pagination

Example:page=1

##### header Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/listTerminalCongestions!in=header&path=Accept&t=request)
Accept string

Specify the desired response format.

* For JSON responses (JsonAPI standard), use `application/vnd.api+json`, `application/json` or `*/*`.
* For CSV responses, use `text/csv`.

If not specified, the response will be in `application/vnd.api+json` format.

Enum:"*/*""application/vnd.api+json""application/json""text/csv"

Example:application/vnd.api+json

Responses

200
A list of terminal congestion data

422
Validation error

500
Internal Server Error

get/terminal-congestions

Try it

Request samples

* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + GuzzleHttp

1 more

Copy

const axios = require('axios');

const API_KEY = 'API_KEY';

const params = {
'filter[performanceInsights.performanceIndex][gte]': 5,
'filter[performanceInsights.avgStayTime][lte]': 100,
'filter[performanceInsights.avgWaitingTime][gte]': 4,
'filter[liveMetrics.congestionIndex][gte]': 5,
'filter[liveMetrics.vesselsWaiting][gte]': 2,
'filter[port.name][contains]': 'HAM',
'filter[port.unlocode]': ['DEHAM', 'BEANR'],
include: 'ports,terminals'
};

axios.get('https://api.kpler.com/v1/logistics/containers/terminal-congestions', {
headers: {
'Accept': 'application/vnd.api+json',
'X-Container-API-Key': API_KEY
},
params
})
.then(response => {
console.dir(response.data, {depth:null});
})
.catch(error => {
if (error.response) {
console.dir(error.response.data, {depth:null});
} else {
console.error('Request failed:', error.message);
}
});

Response samples

* 200
* 422
* 500

application/json

Copy

Expand all Collapse all

`{"data": [{"type": "terminal_congestion","id": 123,"attributes": {"performanceInsights": {"performanceIndex": 7.5,"avgWaitingTime": 12.3,"avgStayTime": 24.6,"lastUpdatedAt": "2024-01-01T00:00:00Z"},"liveMetrics": {"congestionIndex": 6.2,"vesselsWaiting": 3,"vesselsWaitingAvgTime": 12.3,"vesselsStay": 2,"vesselsStayAvgTime": 24.6,"lastUpdatedAt": "2024-01-01T00:00:00Z"},"carriers": [{"scac": "MSCU","name": "Mediterranean Shipping Company","services": ["Service 1","Service 2"]}]},"relationships": {"port": {"data": {"type": "port","id": 456}},"terminal": {"data": {"type": "terminal","id": 123}}}}],"links": {"self": "https://api.kpler.com/v1/logistics/containers/terminal-congestions?page=2","first": "https://api.kpler.com/v1/logistics/containers/terminal-congestions?page=1","prev": "https://api.kpler.com/v1/logistics/containers/terminal-congestions?page=1","next": "https://api.kpler.com/v1/logistics/containers/terminal-congestions?page=3","last": "https://api.kpler.com/v1/logistics/containers/terminal-congestions?page=10"},"included": [{"type": "terminal","id": 3732,"attributes": {"name": "ANTWERP EUROTERMINAL NV K1329-K1347","smdgCode": "AET","lat": 51.2725,"lon": 4.2992}}]}`

# [](https://container-tracking.marinetraffic.com/v2#tag/Shipping-Calls)Shipping Calls

## [](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls)List shipping calls

Retrieve a list of shipping calls with vessel call data including waiting times, arrival and departure details.

The list can be filtered using the following filter groups:

* **Call Status**: `status`

* **Metrics**: `waitingTime`, `stayTime`

* **Waiting Start**: `waitingStart.timestampUtc`

* **Arrival**: `arrival.timestampUtc`, `arrival.services.carrierScac`, `arrival.services.code`

* **Departure**: `departure.timestampUtc`, `departure.services.carrierScac`, `departure.services.code`

* **Last Updated**: `lastUpdatedAt`

* **Vessel Properties**: `id`, `imo`, `mmsi`, `name`, `teuCapacity`

* **Terminal Properties**: `id`, `name`, `smdgCode`

* **Port Properties**: `id`, `unlocode`, `name`

Results are paginated with 50 entries per page.

Security**ApiKeyAuth**

Request

##### query Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[status]&t=request)
filter[status]string

Filter by call status.

Enum:"future""ongoing""past"

Example:filter[status]=ongoing
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[status][operator]&t=request)
filter[status][operator]string

Filter by call status. Supported operators: eq (exact), ne (not equals).

Enum:"future""ongoing""past"

Example:filter[status][operator]=ongoing
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[status][]&t=request)
filter[status][]Array of strings<= 3 items

Filter by call status (max 3 values).

Items Enum:"future""ongoing""past"

Example:filter[status][]=ongoing&filter[status][]=past
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[metrics.waitingTime]&t=request)
filter[metrics.waitingTime]number[ 0 .. 10000 ]

Filter by waiting time in hours (0-10000).

Example:filter[metrics.waitingTime]=12.5
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[metrics.waitingTime][operator]&t=request)
filter[metrics.waitingTime][operator]number[ 0 .. 10000 ]

Filter by waiting time in hours (0-10000). Supported operators eq, gte, lte. Range filtering can be achieved by
combining the gte and lte operators

Example:filter[metrics.waitingTime][operator]=12.5
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[metrics.stayTime]&t=request)
filter[metrics.stayTime]number[ 0 .. 10000 ]

Filter by stay time in hours (0-10000).

Example:filter[metrics.stayTime]=24.6
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[metrics.stayTime][operator]&t=request)
filter[metrics.stayTime][operator]number[ 0 .. 10000 ]

Filter by stay time in hours (0-10000). Supported operators: eq, gte, lte. Range filtering can be achieved by combining
the gte and lte operators.

Example:filter[metrics.stayTime][operator]=24.6
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[waitingStart.timestampUtc]&t=request)
filter[waitingStart.timestampUtc]string<date-time>

Filter by waiting start timestamp in UTC timezone, ISO 8601 format.

Example:filter[waitingStart.timestampUtc]=2025-01-01T00:00:00Z
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[waitingStart.timestampUtc][operator]&t=request)
filter[waitingStart.timestampUtc][operator]string<date-time>

Filter by waiting start timestamp in UTC timezone, ISO 8601 format. Supported operators: eq, gt, gte, lt, lte. Range
filtering can be achieved by combining the gte and lte operators.

Example:filter[waitingStart.timestampUtc][operator]=2025-01-01T00:00:00Z
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[arrival.timestampUtc]&t=request)
filter[arrival.timestampUtc]string<date-time>

Filter by arrival timestamp in UTC timezone, ISO 8601 format.

Example:filter[arrival.timestampUtc]=2025-01-01T00:00:00Z
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[arrival.timestampUtc][operator]&t=request)
filter[arrival.timestampUtc][operator]string<date-time>

Filter by arrival timestamp in UTC timezone, ISO 8601 format. Supported operators: eq, gt, gte, lt, lte. Range filtering
can be achieved by combining the gte and lte operators.

Example:filter[arrival.timestampUtc][operator]=2025-01-01T00:00:00Z
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[departure.timestampUtc]&t=request)
filter[departure.timestampUtc]string<date-time>

Filter by departure timestamp in UTC timezone, ISO 8601 format.

Example:filter[departure.timestampUtc]=2025-01-01T00:00:00Z
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[departure.timestampUtc][operator]&t=request)
filter[departure.timestampUtc][operator]string<date-time>

Filter by departure timestamp in UTC timezone, ISO 8601 format. Supported operators: eq, gt, gte, lt, lte. Range
filtering can be achieved by combining the gte and lte operators.

Example:filter[departure.timestampUtc][operator]=2025-01-01T00:00:00Z
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.id]&t=request)
filter[vessel.id]string[ 1 .. 9 ] characters^[1-9]\d*$

Filter by vessel MT ID, exact match.

Example:filter[vessel.id]=258634
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.id][]&t=request)
filter[vessel.id][]Array of strings<= 5 items

Filter by vessel MT IDs (max 5 values, no leading zeros), exact match only.

Example:filter[vessel.id][]=258634&filter[vessel.id][]=258635&filter[vessel.id][]=258636
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.imo]&t=request)
filter[vessel.imo]string= 7 characters^[0-9]{7}$

Filter by vessel IMO number, exact match only.

Example:filter[vessel.imo]=9387425
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.imo][]&t=request)
filter[vessel.imo][]Array of strings<= 5 items

Filter by vessel IMO numbers (exact match only, max 5 values).

Example:filter[vessel.imo][]=9387425&filter[vessel.imo][]=9387426
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.mmsi]&t=request)
filter[vessel.mmsi]string= 9 characters^[0-9]{9}$

Filter by vessel MMSI, exact match only.

Example:filter[vessel.mmsi]=245258000
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.mmsi][]&t=request)
filter[vessel.mmsi][]Array of strings<= 5 items

Filter by vessel MMSI numbers (exact match only, max 5 values).

Example:filter[vessel.mmsi][]=245258000&filter[vessel.mmsi][]=245258001
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.name]&t=request)
filter[vessel.name]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by vessel name. Exact match only.

Example:filter[vessel.name]=EMPIRE
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.name][operator]&t=request)
filter[vessel.name][operator]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by vessel name. Supported operators: eq (exact), contains (partial match), ne (not equals)

Example:filter[vessel.name][operator]=EMPIRE
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.name][]&t=request)
filter[vessel.name][]Array of strings<= 5 items

Filter by vessel name (max 5 values, exact match only).

Example:filter[vessel.name][]=EMPIRE&filter[vessel.name][]=MAERSK
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.teuCapacity]&t=request)
filter[vessel.teuCapacity]integer[ 0 .. 100000 ]

Filter by vessel TEU capacity.

Example:filter[vessel.teuCapacity]=1400
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[vessel.teuCapacity][operator]&t=request)
filter[vessel.teuCapacity][operator]integer[ 0 .. 100000 ]

Filter by vessel TEU capacity using operators eq, gte, lte. Range filtering can be achieved by combining the gte and lte
operators

Example:filter[vessel.teuCapacity][operator]=1400
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[terminal.id]&t=request)
filter[terminal.id]string[ 1 .. 9 ] characters^[1-9]\d*$

Filter by terminal MT ID, exact match.

Example:filter[terminal.id]=216
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[terminal.id][]&t=request)
filter[terminal.id][]Array of strings<= 5 items

Filter by terminal MT IDs (max 5 values, no leading zeros), exact match only.

Example:filter[terminal.id][]=216&filter[terminal.id][]=217&filter[terminal.id][]=218
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[terminal.name]&t=request)
filter[terminal.name]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by terminal name. Exact match only.

Example:filter[terminal.name]=HAMBURG
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[terminal.name][operator]&t=request)
filter[terminal.name][operator]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by terminal name. Supported operators: eq (exact), contains (partial match), ne (not equals)

Example:filter[terminal.name][operator]=HAMBURG
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[terminal.name][]&t=request)
filter[terminal.name][]Array of strings<= 5 items

Filter by terminal name (max 5 values, exact match only).

Example:filter[terminal.name][]=HAMBURG&filter[terminal.name][]=ROTTERDAM
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[terminal.smdgCode]&t=request)
filter[terminal.smdgCode]string[ 3 .. 6 ] characters^[A-Za-z0-9]+$

Filter by terminal SMDG code. Exact match only.

Example:filter[terminal.smdgCode]=HTG
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[terminal.smdgCode][operator]&t=request)
filter[terminal.smdgCode][operator]string[ 3 .. 6 ] characters^[A-Za-z0-9]+$

Filter by terminal SMDG code. Supported operators: eq (exact), ne (not equals)

Example:filter[terminal.smdgCode][operator]=HTG
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[terminal.smdgCode][]&t=request)
filter[terminal.smdgCode][]Array of strings<= 5 items

Filter by terminal SMDG code (exact match only, max 5 values).

Example:filter[terminal.smdgCode][]=HTG&filter[terminal.smdgCode][]=AET
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[port.id]&t=request)
filter[port.id]string[ 1 .. 9 ] characters^[1-9]\d*$

Filter by port MT ID.

Example:filter[port.id]=172
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[port.id][]&t=request)
filter[port.id][]Array of strings<= 5 items

Filter by port MT IDs (max 5 values, no leading zeros)

Example:filter[port.id][]=172&filter[port.id][]=173&filter[port.id][]=174
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[port.name]&t=request)
filter[port.name]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by port name. Exact match only.

Example:filter[port.name]=HAMBURG
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[port.name][operator]&t=request)
filter[port.name][operator]string[ 2 .. 50 ] characters^[A-Za-z0-9\s\-]+$

Filter by port name. Supported operators: eq (exact), contains (partial match), ne (not equals)

Example:filter[port.name][operator]=HAMBURG
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[port.name][]&t=request)
filter[port.name][]Array of strings<= 5 items

Filter by port name (max 5 values, exact match only).

Example:filter[port.name][]=HAMBURG&filter[port.name][]=ROTTERDAM
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[port.unlocode]&t=request)
filter[port.unlocode]string= 5 characters^[A-Z0-9]+$

Filter by port UN/LOCODE. Exact match only.

Example:filter[port.unlocode]=DEHAM
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[port.unlocode][operator]&t=request)
filter[port.unlocode][operator]string= 5 characters^[A-Z0-9]+$

Filter by port UN/LOCODE. Supported operators: eq (exact), ne (not equals)

Example:filter[port.unlocode][operator]=DEHAM
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[port.unlocode][]&t=request)
filter[port.unlocode][]Array of strings<= 5 items

Filter by port UN/LOCODE (exact match only, max 5 values)

Example:filter[port.unlocode][]=DEHAM&filter[port.unlocode][]=BEANR
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[arrival.services.carrierScac]&t=request)
filter[arrival.services.carrierScac]string[ 2 .. 4 ] characters^[A-Z0-9]+$

Filter by carrier SCAC code on arrival. Exact match only.

Example:filter[arrival.services.carrierScac]=MSCU
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[arrival.services.carrierScac][operator]&t=request)
filter[arrival.services.carrierScac][operator]string[ 2 .. 4 ] characters^[A-Z0-9]+$

Filter by carrier SCAC code on arrival. Supported operators: eq (exact), contains (partial match), ne (not equals)

Example:filter[arrival.services.carrierScac][operator]=MSCU
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[arrival.services.carrierScac][]&t=request)
filter[arrival.services.carrierScac][]Array of strings<= 5 items

Filter by carrier SCAC code on arrival (max 5 values, exact match only).

Example:filter[arrival.services.carrierScac][]=MSCU&filter[arrival.services.carrierScac][]=MAEU
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[arrival.services.code]&t=request)
filter[arrival.services.code]string[ 2 .. 30 ] characters^[A-Z0-9][A-Z0-9\s\-)(]*[A-Z0-9)]$

Filter by service code on arrival. Exact match only.

Example:filter[arrival.services.code]=AE1
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[arrival.services.code][operator]&t=request)
filter[arrival.services.code][operator]string[ 2 .. 30 ] characters^[A-Z0-9][A-Z0-9\s\-)(]*[A-Z0-9)]$

Filter by service code on arrival. Supported operators: eq (exact), contains (partial match), ne (not equals)

Example:filter[arrival.services.code][operator]=AE1
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[arrival.services.code][]&t=request)
filter[arrival.services.code][]Array of strings<= 5 items

Filter by service code on arrival (max 5 values, exact match only).

Example:filter[arrival.services.code][]=AE1&filter[arrival.services.code][]=AE2
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[departure.services.carrierScac]&t=request)
filter[departure.services.carrierScac]string[ 2 .. 4 ] characters^[A-Z0-9]+$

Filter by carrier SCAC code on departure. Exact match only.

Example:filter[departure.services.carrierScac]=MSCU
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[departure.services.carrierScac][operator]&t=request)
filter[departure.services.carrierScac][operator]string[ 2 .. 4 ] characters^[A-Z0-9]+$

Filter by carrier SCAC code on departure. Supported operators: eq (exact), contains (partial match), ne (not equals)

Example:filter[departure.services.carrierScac][operator]=MSCU
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[departure.services.carrierScac][]&t=request)
filter[departure.services.carrierScac][]Array of strings<= 5 items

Filter by carrier SCAC code on departure (max 5 values, exact match only).

Example:filter[departure.services.carrierScac][]=MSCU&filter[departure.services.carrierScac][]=MAEU
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[departure.services.code]&t=request)
filter[departure.services.code]string[ 2 .. 30 ] characters^[A-Z0-9][A-Z0-9\s\-)(]*[A-Z0-9)]$

Filter by service code on departure. Exact match only.

Example:filter[departure.services.code]=AE1
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[departure.services.code][operator]&t=request)
filter[departure.services.code][operator]string[ 2 .. 30 ] characters^[A-Z0-9][A-Z0-9\s\-)(]*[A-Z0-9)]$

Filter by service code on departure. Supported operators: eq (exact), contains (partial match), ne (not equals)

Example:filter[departure.services.code][operator]=AE1
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[departure.services.code][]&t=request)
filter[departure.services.code][]Array of strings<= 5 items

Filter by service code on departure (max 5 values, exact match only).

Example:filter[departure.services.code][]=AE1&filter[departure.services.code][]=AE2
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[lastUpdatedAt]&t=request)
filter[lastUpdatedAt]string<date-time>

Filter by last update timestamp in UTC timezone, ISO 8601 format.

Example:filter[lastUpdatedAt]=2025-01-01T00:00:00Z
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=filter[lastUpdatedAt][operator]&t=request)
filter[lastUpdatedAt][operator]string<date-time>

Filter by last update timestamp in UTC timezone, ISO 8601 format. Supported operators: eq, gt, gte, lt, lte. Range
filtering can be achieved by combining the gte and lte operators.

Example:filter[lastUpdatedAt][operator]=2025-01-01T00:00:00Z
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=include&t=request)include
string

Comma-separated list of related resources to include in the response.

Enum:"vessels""terminals""ports""vessels,terminals""vessels,ports""terminals,ports""vessels,terminals,ports"

Example:include=vessels,terminals,ports
[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=query&path=page&t=request)page
integer>= 1

Default:1

Page number for pagination

Example:page=1

##### header Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/listShippingCalls!in=header&path=Accept&t=request)Accept
string

Specify the desired response format.

* For JSON responses (JsonAPI standard), use `application/vnd.api+json`, `application/json` or `*/*`.
* For CSV responses, use `text/csv`.

If not specified, the response will be in `application/vnd.api+json` format.

Enum:"*/*""application/vnd.api+json""application/json""text/csv"

Example:application/vnd.api+json

Responses

200
A list of shipping call data

422
Validation error

500
Internal Server Error

get/shipping/calls

Try it

Request samples

* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + GuzzleHttp

1 more

Copy

const axios = require('axios');

const API_KEY = 'API_KEY';

const params = {
'filter[status]': 'ongoing',
'filter[metrics.waitingTime][gte]': 10,
'filter[metrics.stayTime][lte]': 50,
'filter[vessel.name][contains]': 'EMPIRE',
'filter[port.unlocode]': ['DEHAM', 'BEANR'],
include: 'vessels,ports,terminals'
};

axios.get('https://api.kpler.com/v1/logistics/containers/shipping/calls', {
headers: {
'Accept': 'application/vnd.api+json',
'X-Container-API-Key': API_KEY
},
params
})
.then(response => {
console.dir(response.data, {depth:null});
})
.catch(error => {
if (error.response) {
console.dir(error.response.data, {depth:null});
} else {
console.error('Request failed:', error.message);
}
});

Response samples

* 200
* 422
* 500

application/json

Copy

Expand all Collapse all

`{"data": [{"type": "call","id": "01kh13zn95ac1b1yj13x96sjwx","attributes": {"status": "ongoing","metrics": {"waitingTime": 12.3,"stayTime": 24.6},"waitingStart": {"timestampUtc": "2025-01-01T00:00:00Z","timestampLocal": "2025-01-01T00:01:00+01:00"},"arrival": {"status": "PLANNED","timestampUtc": "2025-01-01T00:00:00Z","timestampLocal": "2025-01-01T00:01:00+01:00","services": [{"code": "JSX","carrierScac": "CMDU"}]},"departure": {"status": "PLANNED","timestampUtc": "2025-01-01T00:00:00Z","timestampLocal": "2025-01-01T00:01:00+01:00","services": [{"code": "JSX","carrierScac": "CMDU"}]},"lastUpdatedAt": "2025-01-01T00:00:00Z"},"relationships": {"vessel": {"data": {"type": "vessel","id": 123}},"terminal": {"data": {"type": "terminal","id": 123}},"port": {"data": {"type": "port","id": 456}}}}],"links": {"self": "https://api.kpler.com/v1/logistics/containers/shipping/calls?page=2","first": "https://api.kpler.com/v1/logistics/containers/shipping/calls?page=1","prev": "https://api.kpler.com/v1/logistics/containers/shipping/calls?page=1","next": "https://api.kpler.com/v1/logistics/containers/shipping/calls?page=3","last": "https://api.kpler.com/v1/logistics/containers/shipping/calls?page=10"},"included": [{"type": "vessel","id": 4838690,"attributes": {"imo": "9778791","name": "Madrid Maersk","mmsi": 219836000,"teuCapacity": 1400,"latestPosition": {"lat": 53.5076,"lon": 9.9373,"heading": 357,"course": 180,"speed": 0,"geographicalArea": "Elbe River","lastUpdatedAt": "2025-10-22T01:41:37Z"}}}]}`

# [](https://container-tracking.marinetraffic.com/v2#tag/Tracking-requests)Tracking requests

### Introduction

Create tracking requests to start your container tracking experience.

### Supported Shipping Lines / Freight Forwarders & SCAC List

The list of supported shipping lines and freight forwarders is available
in [this page](https://sites.google.com/kpler.com/container-tracking/supported-carriers).

## [](https://container-tracking.marinetraffic.com/v2#operation/createTrackingRequests)Create tracking requests

Create tracking requests to start your container tracking experience.

Up to 100 tracking requests can be created in the same call.

Security**ApiKeyAuth**

Request

##### Request Body schema: application/json

required

[](https://container-tracking.marinetraffic.com/v2#operation/createTrackingRequests!path=data&t=request)data

required Array of objects (CreateTrackingRequestBody)

Responses

200
Successful operation

400
Bad request

422
Unable to process request

500
Internal Server Error

post/tracking-requests

Try it

Request samples

* Payload
* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

3 more

application/json

Copy

Expand all Collapse all

`{"data": [{"type": "tracking_request","attributes": {"referenceNumberType": "container","referenceNumber": "MEDUPE268513","scac": "MEDU","tags": ["one tag","second tag"]}}]}`

Response samples

* 200
* 400
* 422
* 500

application/json

Copy

Expand all Collapse all

`{"data": [{"type": "tracking_request","trackingRequestId": "01hkz69f0m9vxcmgpyrq50dfsg","attributes": {"referenceNumberType": "container","referenceNumber": "MEDUPE268513","carrier": {"scac": "MEDU","name": "Maersk Line"},"status": "success","failed_reason": null,"tags": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","label": "Tag one"}],"owned": true,"created": "2024-01-01T00:00:00.000Z"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01hkz69f0m9vxcmgpyrq50dfsg"},"relationships": {"shipment": {"data": {"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa"},"links": {"related": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa"}}}}],"errors": [{"status": 400,"code": "DATA_VALIDATION_FAILED","description": "Data validation failed","source": {"pointer": "/data/0/attributes/scac"}}]}`

## [](https://container-tracking.marinetraffic.com/v2#operation/listTrackingRequests)List tracking requests

Retrieve a list of the tracking requests created.

The list can be filtered by reference number, tags and more.

The list is divided into pages of 50 entries each.

Pagination links are provided for easy navigation.

Security**ApiKeyAuth**

Request

##### query Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/listTrackingRequests!in=query&path=filter&t=request)filter
object

Structure that can be used as payload when calling the Tracking Request list endpoint.

When the filters contain values that return no results, an empty response will be sent.

Filters can be applied to reference Number, reference number types, scac and tags.

Responses

200
successful operation

500
Internal Server Error

get/tracking-requests

Try it

Request samples

* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

2 more

Copy

const request = require('request');

const options = {
method: 'GET',
url: 'https://api.kpler.com/v1/logistics/containers/tracking-requests',
qs: {filter: { scac: ['TXZJ', 'LMCU'] }},
headers: {'X-Container-API-Key': 'REPLACE_KEY_VALUE'}
};

request(options, function (error, response, body) {
if (error) throw new Error(error);

    console.log(body);

});

Response samples

* 200
* 500

application/json

Copy

Expand all Collapse all

`{"data": [{"type": "tracking_request","trackingRequestId": "01hkz69f0m9vxcmgpyrq50dfsg","attributes": {"referenceNumberType": "container","referenceNumber": "MEDUPE268513","carrier": {"scac": "MEDU","name": "Maersk Line"},"status": "success","failed_reason": null,"tags": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","label": "Tag one"}],"owned": true,"created": "2024-01-01T00:00:00.000Z"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01hkz69f0m9vxcmgpyrq50dfsg"},"relationships": {"shipment": {"data": {"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa"},"links": {"related": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa"}}}}],"links": {"self": "https://api.kpler.com/v1/logistics/containers/tracking-requests/?page=2","first": "https://api.kpler.com/v1/logistics/containers/tracking-requests/?page=1","prev": "https://api.kpler.com/v1/logistics/containers/tracking-requests/?page=1","next": "https://api.kpler.com/v1/logistics/containers/tracking-requests/?page=3","last": "https://api.kpler.com/v1/logistics/containers/tracking-requests/?page=10"}}`

## [](https://container-tracking.marinetraffic.com/v2#operation/fetchTrackingRequestById)Get tracking request details

Retrieve a specific tracking request and all associated information.

Security**ApiKeyAuth**

Request

##### path Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/fetchTrackingRequestById!in=path&path=trackingRequestId&t=request)
trackingRequestId

required string

Id of Tracking Request to return

Responses

200
successful operation

404
Tracking request not found

500
Internal Server Error

get/tracking-requests/{trackingRequestId}

Try it

Request samples

* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

2 more

Copy

const request = require('request');

const options = {
method: 'GET',
url: 'https://api.kpler.com/v1/logistics/containers/tracking-requests/%7BtrackingRequestId%7D',
headers: {'X-Container-API-Key': 'REPLACE_KEY_VALUE'}
};

request(options, function (error, response, body) {
if (error) throw new Error(error);

console.log(body);
});

Response samples

* 200
* 404
* 500

application/json

Copy

Expand all Collapse all

`{"data": {"type": "tracking_request","trackingRequestId": "01hkz69f0m9vxcmgpyrq50dfsg","attributes": {"referenceNumberType": "container","referenceNumber": "MEDUPE268513","carrier": {"scac": "MEDU","name": "Maersk Line"},"status": "success","failed_reason": null,"tags": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","label": "Tag one"}],"owned": true,"created": "2024-01-01T00:00:00.000Z"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01hkz69f0m9vxcmgpyrq50dfsg"},"relationships": {"shipment": {"data": {"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa"},"links": {"related": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa"}}}}}`

## [](https://container-tracking.marinetraffic.com/v2#operation/archiveTrackingRequests)(Un)Archive tracking requests

(Un)Archive tracking requests and their related shipment.

Security**ApiKeyAuth**

Request

##### path Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/archiveTrackingRequests!in=path&path=operation&t=request)
operation

required string

The operation that will be executed.

Enum:"add""remove"

##### Request Body schema: application/json

required

(Un)Archive the Tracking Requests id list.

[](https://container-tracking.marinetraffic.com/v2#operation/archiveTrackingRequests!path=data&t=request)data object (
ArchiveTrackingRequestsRequestBody)

Responses

204
successful operation

400
Bad request

422
Unable to process request

500
Internal Server Error

post/tracking-requests/archive/{operation}

Try it

Request samples

* Payload
* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

3 more

application/json

Copy

Expand all Collapse all

`{"data": {"type": "tracking_request_archive","attributes": {"list": ["01hkz69f0m9vxcmgpyrq50dfsg"]}}}`

Response samples

* 400
* 422
* 500

application/json

Copy

Expand all Collapse all

`{"errors": [{"status": 400,"code": "DATA_VALIDATION_FAILED","description": "Data validation failed","source": {"pointer": "/data/0/attributes/scac"}}]}`

# [](https://container-tracking.marinetraffic.com/v2#tag/Shipments)Shipments

Access and manage your container tracking data.

## [](https://container-tracking.marinetraffic.com/v2#operation/listShipments)List shipments

Retrieve a list of your shipments.

The list can be filtered by reference number, scac, tags, departure and arrival date range, origin and destination port.

Security**ApiKeyAuth**

Request

##### query Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/listShipments!in=query&path=filter&t=request)filter object

Structure that can be used as payload when calling the Shipment list endpoint.

When the filters contain values that return no results, an empty response will be sent.

Filters can be applied to reference Number, reference number types, scac, tags date ranged for arrival and departure, as
well as origin and destination of a shipment.

Responses

200
successful operation

500
Internal Server Error

get/shipments

Try it

Request samples

* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

2 more

Copy

const request = require('request');

const options = {
method: 'GET',
url: 'https://api.kpler.com/v1/logistics/containers/shipments',
qs: {filter: { scac: ['TXZJ', 'LMCU'] }},
headers: {'X-Container-API-Key': 'REPLACE_KEY_VALUE'}
};

request(options, function (error, response, body) {
if (error) throw new Error(error);

    console.log(body);

});

Response samples

* 200
* 500

application/json

Copy

Expand all Collapse all

`{"data": [{"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa","attributes": {"referenceNumberType": "container","referenceNumber": "MEDUPE268513","carrier": {"scac": "MEDU","name": "Maersk Line"},"tags": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","label": "Tag one"}],"owned": true,"created": "2024-01-01T00:00:00.000Z"},"relationships": {"trackingRequest": {"data": {"type": "tracking_request","trackingRequestId": "01hkz69f0m9vxcmgpyrq50dfsg"},"links": {"related": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01hkz69f0m9vxcmgpyrq50dfsg"}}},"links": {"self": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa","related": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa/trasnportation-timeline"}}],"links": {"self": "https://api.kpler.com/v1/logistics/containers/shipments/?page=2","first": "https://api.kpler.com/v1/logistics/containers/shipments/?page=1","prev": "https://api.kpler.com/v1/logistics/containers/shipments/?page=1","next": "https://api.kpler.com/v1/logistics/containers/shipments/?page=3","last": "https://api.kpler.com/v1/logistics/containers/shipments/?page=10"}}`

## [](https://container-tracking.marinetraffic.com/v2#operation/fetchShipmentTrackDetails)Get shipment summary

Retrieve a specific shipment and a comprehensive summary of all associated information.

Security**ApiKeyAuth**

Request

##### path Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/fetchShipmentTrackDetails!in=path&path=shipmentId&t=request)
shipmentId

required string

Id of Shipment to return

Responses

200
successful operation

400
Bad request

404
Shipment not found

422
Unable to process request

500
Internal Server Error

get/shipments/{shipmentId}

Try it

Request samples

* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

2 more

Copy

const request = require('request');

const options = {
method: 'GET',
url: 'https://api.kpler.com/v1/logistics/containers/shipments/%7BshipmentId%7D',
headers: {'X-Container-API-Key': 'REPLACE_KEY_VALUE'}
};

request(options, function (error, response, body) {
if (error) throw new Error(error);

console.log(body);
});

Response samples

* 200
* 400
* 404
* 422
* 500

application/json

Copy

Expand all Collapse all

`{"data": {"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa","attributes": {"referenceNumberType": "container","referenceNumber": "MEDUPE268513","carrier": {"scac": "MEDU","name": "Maersk Line"},"transportationStatus": "booked","containers": [{"id": "01hnahef5ym5x2jfndgma4kc6q","number": "HLXU1234567","isoCode": "22G1","type": "General purpose container","size": {"length": 20,"height": 8.6}}],"insights": {"arrivalDelayDays": 3,"rollover": [{"initialVessel": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"name": "Madrid Maersk","imo": 9778791,"mmsi": 219836000},"newVessel": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"name": "Madrid Maersk","imo": 9778791,"mmsi": 219836000},"atPort": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": null,"name": null,"smdg": null,"operator": null}},"detectedAt": "2024-01-01T00:00:00.000Z","initialDepartureDate": {"timestamp": "2024-01-01T00:00:00.000Z","localTimeOffset": -3},"newDepartureDate": {"timestamp": "2024-01-01T00:00:00.000Z","localTimeOffset": -3}}],"portOfLoadingChange": [{"initialPort": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": null,"name": null,"smdg": null,"operator": null}},"newPort": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": null,"name": null,"smdg": null,"operator": null}},"detectedAt": "2024-01-01T00:00:00.000Z","initialDepartureDate": {"timestamp": "2024-01-01T00:00:00.000Z","localTimeOffset": -3},"newDepartureDate": {"timestamp": "2024-01-01T00:00:00.000Z","localTimeOffset": -3}}],"portOfDischargeChange": [{"initialPort": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": null,"name": null,"smdg": null,"operator": null}},"newPort": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": null,"name": null,"smdg": null,"operator": null}},"detectedAt": "2024-01-01T00:00:00.000Z","initialArrivalDate": {"timestamp": "2024-01-01T00:00:00.000Z","localTimeOffset": -3},"newArrivalDate": {"timestamp": "2024-01-01T00:00:00.000Z","localTimeOffset": -3}}],"initialCarrierEta": "2021-01-01T00:00:00Z"},"portOfLoading": {"port": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","name": "Piraeus Container terminal","smdg": null,"operator": null}},"departureDate": {"timestamp": "2024-01-01T00:00:00.000Z","status": "actual","localTimeOffset": -3},"loadingVessel": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"name": "Madrid Maersk","imo": 9778791,"mmsi": 219836000},"voyageNumber": "123A"},"portsOfTransshipment": [{"port": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","name": "Piraeus Container terminal","smdg": null,"operator": null}},"arrivalDate": {"timestamp": "2024-01-01T00:00:00.000Z","status": "actual","localTimeOffset": -3},"departureDate": {"timestamp": "2024-01-01T00:00:00.000Z","status": "actual","localTimeOffset": -3},"loadingVessel": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"name": "Madrid Maersk","imo": 9778791,"mmsi": 219836000},"voyageNumber": "123A","sequenceNumber": 1}],"portOfDischarge": {"port": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","name": "Piraeus Container terminal","smdg": null,"operator": null}},"arrivalDate": {"timestamp": "2024-01-01T00:00:00.000Z","status": "actual","localTimeOffset": -3}},"currentVessel": {"operationalStatus": "slow_steaming_open_sea","latestPosition": {"lat": 12.3456,"lon": 78.9012,"geographicalArea": "Mediterranean","heading": 90,"speed": 12.5},"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"name": "Madrid Maersk","imo": 9778791,"mmsi": 219836000},"tags": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","label": "Tag one"}],"owned": true,"updated": "2024-01-01T00:00:00.000Z","created": "2024-01-01T00:00:00.000Z"},"relationships": {"trackingRequest": {"data": {"type": "tracking_request","trackingRequestId": "01hkz69f0m9vxcmgpyrq50dfsg"},"links": {"related": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01hkz69f0m9vxcmgpyrq50dfsg"}}},"links": {"self": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa","related": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa/trasnportation-timeline"}},"meta": {"webViewLink": "https://www.marinetraffic.com/containers/track-shipment?id=01j0qv3jmyete3zwe08nn2dzpa"}}`

## [](https://container-tracking.marinetraffic.com/v2#operation/fetchShipmentTransportationTimeline)Get shipment detailed milestones

Retrieve all milestones, locations and vessels associated with a specific shipment.

Security**ApiKeyAuth**

Request

##### path Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/fetchShipmentTransportationTimeline!in=path&path=shipmentId&t=request)
shipmentId

required string

ID of Shipment to return the associated transportation timeline

Responses

200
successful operation

400
Bad request

404
Shipment not found

422
Unable to process request

500
Internal Server Error

get/shipments/{shipmentId}/transportation-timeline

Try it

Request samples

* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

2 more

Copy

const request = require('request');

const options = {
method: 'GET',
url: 'https://api.kpler.com/v1/logistics/containers/shipments/%7BshipmentId%7D/transportation-timeline',
headers: {'X-Container-API-Key': 'REPLACE_KEY_VALUE'}
};

request(options, function (error, response, body) {
if (error) throw new Error(error);

console.log(body);
});

Response samples

* 200
* 400
* 404
* 422
* 500

application/json

Copy

Expand all Collapse all

`{"data": {"type": "transportation-timeline","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa","attributes": {"containers": [{"id": "01hnahef5ym5x2jfndgma4kc6q","number": "HLXU1234567","isoCode": "22G1","type": "General purpose container","size": {"length": 20,"height": 8.6}}],"equipmentEvents": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","equipmentEventTypeName": "load","eventClassifierCode": "actual","equipmentReference": ["01hnahef5ym5x2jfndgma4kc6q"],"eventDateTime": "2024-01-01T00:00:00.000Z","locationId": "01hkz69f0m9vxcmgpyrq50dfsg","vesselId": "01hkz69f0m9vxcmgpyrq50dfsg","modeOfTransport": "maritime_transport","equipmentEmptyIndicator": "laden","eventOrder": 1}],"locations": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"type": "port_of_Loading","unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"localTimeOffset": -3,"terminal": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","name": "Piraeus Container terminal","smdg": null,"operator": null}}],"transportEvents": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","transportEventTypeName": "departure","eventClassifierCode": "actual","eventDateTime": "2024-01-01T00:00:00.000Z","locationId": "01hkz69f0m9vxcmgpyrq50dfsg","vesselId": "01hkz69f0m9vxcmgpyrq50dfsg","modeOfTransport": "maritime_transport","eventOrder": 1}],"vessels": [{"voyageNumber": "123A","id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"name": "Madrid Maersk","imo": 9778791,"mmsi": 219836000}]},"links": {"self": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa/trasnportation-timeline"}},"meta": {"webViewLink": "https://www.marinetraffic.com/containers/track-shipment?id=01j0qv3jmyete3zwe08nn2dzpa"}}`

## [](https://container-tracking.marinetraffic.com/v2#operation/syncShipmentTags)Replace all shipment tags

Security**ApiKeyAuth**

Request

##### path Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/syncShipmentTags!in=path&path=shipmentId&t=request)
shipmentId

required string

ID of Shipment to update the associated tags

##### Request Body schema: application/json

required

Replace all the existing tags with a new list of tags.

[](https://container-tracking.marinetraffic.com/v2#operation/syncShipmentTags!path=data&t=request)data

required Array of objects (ShipmentTagsRequestBody)

Responses

200
successful operation

400
Bad request

404
Shipment not found

422
Unable to process request

500
Internal Server Error

post/shipments/{shipmentId}/sync-tags

Try it

Request samples

* Payload
* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

3 more

application/json

Copy

Expand all Collapse all

`{"data": [{"type": "tag","attributes": {"name": "tag 1"}}]}`

Response samples

* 200
* 400
* 404
* 422
* 500

application/json

Copy

Expand all Collapse all

`{"data": {"shipmentTags": [{"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa","attributes": {"name": "tag 1"}}]}}`

## [](https://container-tracking.marinetraffic.com/v2#operation/addShipmentTags)Add tags to a shipment

Security**ApiKeyAuth**

Request

##### path Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/addShipmentTags!in=path&path=shipmentId&t=request)
shipmentId

required string

ID of Shipment to add tags to.

##### Request Body schema: application/json

required

Add listed tags to the shipment.

[](https://container-tracking.marinetraffic.com/v2#operation/addShipmentTags!path=data&t=request)data Array of objects (
ShipmentTagsRequestBody)

Responses

200
successful operation

400
Bad request

404
Shipment not found

422
Unable to process request

500
Internal Server Error

post/shipments/{shipmentId}/add-tags

Try it

Request samples

* Payload
* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

3 more

application/json

Copy

Expand all Collapse all

`{"data": [{"type": "tag","attributes": {"name": "tag 1"}}]}`

Response samples

* 200
* 400
* 404
* 422
* 500

application/json

Copy

Expand all Collapse all

`{"data": {"shipmentTags": [{"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa","attributes": {"name": "tag 1"}}]}}`

## [](https://container-tracking.marinetraffic.com/v2#operation/removeShipmentTags)Remove tags from a shipment

Security**ApiKeyAuth**

Request

##### path Parameters

[](https://container-tracking.marinetraffic.com/v2#operation/removeShipmentTags!in=path&path=shipmentId&t=request)
shipmentId

required string

ID of Shipment to remove tags from.

##### Request Body schema: application/json

required

Remove listed tags from the shipment.

[](https://container-tracking.marinetraffic.com/v2#operation/removeShipmentTags!path=data&t=request)data Array of
objects (ShipmentTagsRequestBody)

Responses

200
successful operation

400
Bad request

404
Shipment not found

422
Unable to process request

500
Internal Server Error

post/shipments/{shipmentId}/remove-tags

Try it

Request samples

* Payload
* Node + Request
* Shell + Curl
* Shell + Httpie
* Python + Python3
* Php + Curl
* Php + Http1
* Php + Http2

3 more

application/json

Copy

Expand all Collapse all

`{"data": [{"type": "tag","attributes": {"name": "tag 1"}}]}`

Response samples

* 200
* 400
* 404
* 422
* 500

application/json

Copy

Expand all Collapse all

`{"data": {"shipmentTags": [{"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa","attributes": {"name": "tag 1"}}]}}`

# [](https://container-tracking.marinetraffic.com/v2#tag/Webhook-events)Webhook events

### Introduction

The Webhook API enables your system to subscribe to specific container tracking events and receive realtime updates
about them. The current version supports the following events:

* **shipment_updated**: Triggered when one of your shipment has been updated.
* **tracking_request_succeeded**: Triggered when your tracking request has been successfully processed and the
  underlying shipment successfully created.
* **tracking_request_failed**: Triggered when your tracking request has faild. To start receiving webhook events, reach
  out to get a webhook URL that will receive the event updates.

### Getting Started

To begin using the Webhook API, follow these steps:

1. **Register for the service**: Contact your account manager to register for the Webhook service.
2. **Webhook URL Setup**: You should provide us the URL where you'd like to receive our webhook events and payloads.

> **Important:** Only one URL is supported per user in the current version, and all events will be pushed to this URL.

### Webhook Security

IP Whitelisting: To ensure that the events you receive are from the Webhook API and not from unauthorized sources,
webhooks will only be dispatched from the following IP address(es): 3.251.15.122, 52.215.44.244, 54.195.123.104.

### Error Handling

In the event that your system cannot process a webhook request, ensure your endpoint responds with a `500` HTTP status
code. The system will attempt a limited number of retries in case of failures.

## [](https://container-tracking.marinetraffic.com/v2#operation/webhookShipmentUpdated)shipment_updated Webhook

The event is triggered when the shipment has been updated.

The webhook includes the new version of the shipment.

Security**ApiKeyAuth**

Request

##### Request Body schema: application/json

[](https://container-tracking.marinetraffic.com/v2#operation/webhookShipmentUpdated!path=data&t=request)data

required object
[](https://container-tracking.marinetraffic.com/v2#operation/webhookShipmentUpdated!path=included&t=request)included

required Array of objects (Shipment)

Responses

200
Return a 200 status to indicate that the data was received successfully.

500
Return a 500 status to indicate that the data was not received successfully.

Request samples

* Payload

application/json

Copy

Expand all Collapse all

`{"data": {"type": "webhook_event","id": "01j7k957597bmt6x9t6v4nh1gk","attributes": {"eventType": "shipment_updated","status": "success","createdAt": "2024-09-13T12:10:00Z"},"relationships": {"trackingRequest": {"data": {"type": "tracking_request","id": "01j7k957597bmt6x9t6v4nh1gc"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01j7k957597bmt6x9t6v4nh1gc"}},"shipment": {"data": {"type": "shipment","id": "01j7k95jfkft4bkd6adnf8hwtp"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/shipments/01j7k95jfkft4bkd6adnf8hwtp"}}}},"included": [{"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa","attributes": {"referenceNumberType": "container","referenceNumber": "MEDUPE268513","carrier": {"scac": "MEDU","name": "Maersk Line"},"transportationStatus": "booked","containers": [{"id": "01hnahef5ym5x2jfndgma4kc6q","number": "HLXU1234567","isoCode": "22G1","type": "General purpose container","size": {"length": 20,"height": 8.6}}],"insights": {"arrivalDelayDays": 3,"rollover": [{"initialVessel": {"id": null,"mtId": null,"name": null,"imo": null,"mmsi": null},"newVessel": {"id": null,"mtId": null,"name": null,"imo": null,"mmsi": null},"atPort": {"id": null,"mtId": null,"unlocode": null,"name": null,"country": null,"lat": null,"lon": null,"terminal": null},"detectedAt": "2024-01-01T00:00:00.000Z","initialDepartureDate": {"timestamp": null,"localTimeOffset": null},"newDepartureDate": {"timestamp": null,"localTimeOffset": null}}],"portOfLoadingChange": [{"initialPort": {"id": null,"mtId": null,"unlocode": null,"name": null,"country": null,"lat": null,"lon": null,"terminal": null},"newPort": {"id": null,"mtId": null,"unlocode": null,"name": null,"country": null,"lat": null,"lon": null,"terminal": null},"detectedAt": "2024-01-01T00:00:00.000Z","initialDepartureDate": {"timestamp": null,"localTimeOffset": null},"newDepartureDate": {"timestamp": null,"localTimeOffset": null}}],"portOfDischargeChange": [{"initialPort": {"id": null,"mtId": null,"unlocode": null,"name": null,"country": null,"lat": null,"lon": null,"terminal": null},"newPort": {"id": null,"mtId": null,"unlocode": null,"name": null,"country": null,"lat": null,"lon": null,"terminal": null},"detectedAt": "2024-01-01T00:00:00.000Z","initialArrivalDate": {"timestamp": null,"localTimeOffset": null},"newArrivalDate": {"timestamp": null,"localTimeOffset": null}}],"initialCarrierEta": "2021-01-01T00:00:00Z"},"portOfLoading": {"port": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","name": "Piraeus Container terminal","smdg": null,"operator": null}},"departureDate": {"timestamp": "2024-01-01T00:00:00.000Z","status": "actual","localTimeOffset": -3},"loadingVessel": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"name": "Madrid Maersk","imo": 9778791,"mmsi": 219836000},"voyageNumber": "123A"},"portsOfTransshipment": [{"port": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": null,"name": null,"smdg": null,"operator": null}},"arrivalDate": {"timestamp": "2024-01-01T00:00:00.000Z","status": "actual","localTimeOffset": -3},"departureDate": {"timestamp": "2024-01-01T00:00:00.000Z","status": "actual","localTimeOffset": -3},"loadingVessel": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"name": "Madrid Maersk","imo": 9778791,"mmsi": 219836000},"voyageNumber": "123A","sequenceNumber": 1}],"portOfDischarge": {"port": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"unlocode": "GRPIR","name": "Piraeus","country": "Greece","lat": -19.88327,"lon": 5.713717,"terminal": {"id": "01hkz69f0m9vxcmgpyrq50dfsg","name": "Piraeus Container terminal","smdg": null,"operator": null}},"arrivalDate": {"timestamp": "2024-01-01T00:00:00.000Z","status": "actual","localTimeOffset": -3}},"currentVessel": {"operationalStatus": "slow_steaming_open_sea","latestPosition": {"lat": 12.3456,"lon": 78.9012,"geographicalArea": "Mediterranean","heading": 90,"speed": 12.5},"id": "01hkz69f0m9vxcmgpyrq50dfsg","mtId": 12345,"name": "Madrid Maersk","imo": 9778791,"mmsi": 219836000},"tags": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","label": "Tag one"}],"owned": true,"updated": "2024-01-01T00:00:00.000Z","created": "2024-01-01T00:00:00.000Z"},"relationships": {"trackingRequest": {"data": {"type": "tracking_request","trackingRequestId": "01hkz69f0m9vxcmgpyrq50dfsg"},"links": {"related": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01hkz69f0m9vxcmgpyrq50dfsg"}}},"links": {"self": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa","related": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa/trasnportation-timeline"}}]}`

## [](https://container-tracking.marinetraffic.com/v2#operation/webhookTrackingRequestSucceeded)tracking_requested_succeeded Webhook

The event is triggered when your tracking request has been successfully processed and the underlying shipment has been
successfully created.

Security**ApiKeyAuth**

Request

##### Request Body schema: application/json

[](https://container-tracking.marinetraffic.com/v2#operation/webhookTrackingRequestSucceeded!path=data&t=request)data

required object
[](https://container-tracking.marinetraffic.com/v2#operation/webhookTrackingRequestSucceeded!path=included&t=request)
included

required Array of objects (TrackingRequest)

Responses

200
Return a 200 status to indicate that the data was received successfully.

500
Return a 500 status to indicate that the data was not received successfully.

Request samples

* Payload

application/json

Copy

Expand all Collapse all

`{"data": {"type": "webhook_event","id": "01j7k957597bmt6x9t6v4nh1gk","attributes": {"eventType": "tracking_request_succeeded","status": "success","createdAt": "2024-09-13T12:10:00Z"},"relationships": {"trackingRequest": {"data": {"type": "tracking_request","id": "01j7k957597bmt6x9t6v4nh1gc"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01j7k957597bmt6x9t6v4nh1gc"}},"shipment": {"data": {"type": "shipment","id": "01j7k95jfkft4bkd6adnf8hwtp"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/shipments/01j7k95jfkft4bkd6adnf8hwtp"}}}},"included": [{"type": "tracking_request","trackingRequestId": "01hkz69f0m9vxcmgpyrq50dfsg","attributes": {"referenceNumberType": "container","referenceNumber": "MEDUPE268513","carrier": {"scac": "MEDU","name": "Maersk Line"},"status": "success","failed_reason": null,"tags": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","label": "Tag one"}],"owned": true,"created": "2024-01-01T00:00:00.000Z"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01hkz69f0m9vxcmgpyrq50dfsg"},"relationships": {"shipment": {"data": {"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa"},"links": {"related": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa"}}}}]}`

## [](https://container-tracking.marinetraffic.com/v2#operation/webhookTrackingRequestFailed)tracking_requested_failed Webhook

The event is triggered when your tracking request has failed. It contains details about the failing reason.

Security**ApiKeyAuth**

Request

##### Request Body schema: application/json

[](https://container-tracking.marinetraffic.com/v2#operation/webhookTrackingRequestFailed!path=data&t=request)data

required object
[](https://container-tracking.marinetraffic.com/v2#operation/webhookTrackingRequestFailed!path=included&t=request)
included

required Array of objects (TrackingRequest)

Responses

200
Return a 200 status to indicate that the data was received successfully.

500
Return a 500 status to indicate that the data was not received successfully.

Request samples

* Payload

application/json

Copy

Expand all Collapse all

`{"data": {"type": "webhook_event","id": "01j7k957597bmt6x9t6v4nh1gk","attributes": {"eventType": "tracking_request_failed","status": "failed","createdAt": "2024-09-13T12:10:00Z"},"relationships": {"trackingRequest": {"data": {"type": "tracking_request","id": "01j7k957597bmt6x9t6v4nh1gc"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01j7k957597bmt6x9t6v4nh1gc"}}}},"included": [{"type": "tracking_request","trackingRequestId": "01hkz69f0m9vxcmgpyrq50dfsg","attributes": {"referenceNumberType": "container","referenceNumber": "MEDUPE268513","carrier": {"scac": "MEDU","name": "Maersk Line"},"status": "success","failed_reason": null,"tags": [{"id": "01hkz69f0m9vxcmgpyrq50dfsg","label": "Tag one"}],"owned": true,"created": "2024-01-01T00:00:00.000Z"},"links": {"self": "https://api.kpler.com/v1/logistics/containers/tracking-requests/01hkz69f0m9vxcmgpyrq50dfsg"},"relationships": {"shipment": {"data": {"type": "shipment","shipmentId": "01j0qv3jmyete3zwe08nn2dzpa"},"links": {"related": "https://api.kpler.com/v1/logistics/containers/shipments/01j0qv3jmyete3zwe08nn2dzpa"}}}}]}`

# [](https://container-tracking.marinetraffic.com/v2#tag/2026-02-20-1.3.0)2026-02-20 (1.3.0)

> **DEVELOPER NOTICE | CODEGEN IMPACT**
>
>  Changes in this release may impact generated client libraries.
>
>  Please regenerate your SDKs to ensure full compatibility and benefit from the new features.

### NEW FEATURE: New Filter Operators

The `ne` (not equals) operator has been added to following filters:

**For `/shipping/calls`:**

* `filter[status]`, `filter[vessel.name]`, `filter[terminal.name]`, `filter[terminal.smdgCode]`, `filter[port.name]`,
  `filter[port.unlocode]`

**For `/terminal-congestions`:**

* `filter[terminal.name]`, `filter[terminal.smdgCode]`, `filter[port.name]`, `filter[port.unlocode]`

The `gt` (greater than) and `lt` (less than) operators have been added to all the filters that support the `gte` and
`lte` operators.

### NEW FEATURE: Shipping Calls | Service Filters

New filters have been added to the `/shipping/calls` endpoint to filter by vessel services:

* `filter[arrival.services.carrierScac]` - Filter by carrier SCAC code on arrival. Supported operators: `eq`,
  `contains`, `ne`.
* `filter[arrival.services.code]` - Filter by service code on arrival. Supported operators: `eq`, `contains`, `ne`.
* `filter[departure.services.carrierScac]` - Filter by carrier SCAC code on departure. Supported operators: `eq`,
  `contains`, `ne`.
* `filter[departure.services.code]` - Filter by service code on departure. Supported operators: `eq`, `contains`, `ne`.
* `filter[lastUpdatedAt]` - Filter by last update timestamp. Supported operators: `eq`, `gt`, `gte`, `lt`, `lte`.

### NEW FEATURE: Terminal Congestion | Live Metrics

New attributes have been added to the /terminal-congestions endpoint

* `liveMetrics.vesselsWaitingAvgTime`: the live average waiting time (in hours) of all vessels that are currently
  waiting to enter the terminal.
* `liveMetrics.vesselsStayAvgTime`: the live average stay time (in hours) of all vessels that are currently staying at
  the terminal.
* `liveMetrics.vesselsStay`: number of vessels currently staying at the terminal.

### NEW FEATURE: Vessel course

New attribute `course` has been added to the `ContainerIntelligenceVesselPosition` object.

### DOCUMENTATION UPDATE

* Corrected rate limit documentation from 200 to 500 requests per minute.
* Update the example of the `TerminalCongestionsCsvResponse` object to reflect the actual CSV response.
* Reflect that `heading`, `speed`, `geographicalArea`, `lastUpdatedAt` attributes of the
  `ContainerIntelligenceVesselPosition` object can be null.

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-11-26-1.2.2)2025-11-26 (1.2.2)

### DOCUMENTATION UPDATE:

* Added the whitelisted webhook IPs to the Webhook section; previously, they were only listed in the API Introduction
  section.

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-11-05-1.2.1)2025-11-05 (1.2.1)

### API CHANGES:

* `ShippingCallsCsvResponse` now includes two new service columns: `Arrival Services` and `Departure Services`.
  Therefore, the previous single `Services` column has been removed.

### DOCUMENTATION CHANGES:

> **DEVELOPER NOTICE | CODEGEN IMPACT**
>
>  This documentation fix may impact generated client libraries.
>
>  Please regenerate your SDKs to ensure compatibility.

* `ShippingCallsCsvResponse` example changed to reflect the API changes.

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-10-23-1.2.0)2025-10-23 (1.2.0)

### NEW FEATURE: Container Intelligence API | Shipping Calls

* New endpoint `GET /shipping/calls` to get insights about container vessel calls.
* This new endpoint replaces `/shipping/schedule-events` - it will remain in operation for a while, but going forward
  using `/shipping/calls` is advised.
* Added the latest position object in the vessel resource

### DOCUMENTATION FIX

> **DEVELOPER NOTICE | CODEGEN IMPACT**
>
>  This documentation fix may impact generated client libraries.
>
>  Please regenerate your SDKs to ensure compatibility.

* Fixed the schema of the `VesselResource` object.
* Removed error code `invalid_date` from the `TerminalCongestionsValidationErrorResponse` object.
* Added error code `utc_date_time_format_invalid` to the `TerminalCongestionsValidationErrorResponse` object.
* Added `filter[terminal.name]` to the `/terminal-congestions` endpoint.

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-09-17-1.1.0)2025-09-17 (1.1.0)

### NEW FEATURE: Container Intelligence API | Terminal Congestion

* New endpoint `GET /terminal-congestions` to get insights on terminal congestion.
* Reorganized documentation to include a new "Container Intelligence API" section and product description.

### DOCUMENTATION FIX

> **DEVELOPER NOTICE | CODEGEN IMPACT**
>
>  This documentation update may impact generated client libraries.
>
>  Please regenerate your SDKs to ensure compatibility.

* Fixed the schema of Port and Terminal in the `shipping/schedule-events` endpoint.

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-08-22-1.0.3)2025-08-22 (1.0.3)

### DOCUMENTATION FIX

> **DEVELOPER NOTICE | CODEGEN IMPACT**
>
>  This documentation fix may impact generated client libraries.
>
>  Please regenerate your SDKs to ensure compatibility.

* Added 5 values missing from API documentation for the `equipmentEventTypeName` enum: `customs_released`,
  `customs_selected_for_inspection`, `customs_selected_for_scan`, `inspected`, `received`.

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-07-16-1.0.2)2025-07-16 (1.0.2)

### DOCUMENTATION UPDATE

* Added a relevant `filter[unlocode]` example for the `shipping/schedule-events` endpoint.

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-07-02-1.0.1)2025-07-02 (1.0.1)

### DOCUMENTATION FIX

> **DEVELOPER NOTICE | CODEGEN IMPACT**
>
>  This documentation fix may impact generated client libraries.
>
>  Please regenerate your SDKs to ensure compatibility.

* Added terminal in the `shipping/schedule-events` endpoint.
* Fixed the schema of the `included` attribute in the `shipping/schedule-events` endpoint.

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-06-17-1.0.0)2025-06-17 (1.0.0)

### DOCUMENTATION UPDATE

Reset documentation version to 1.0.0 to align with the API version (/v1/). **No change in functionality.**

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-06-10)2025-06-10

### DOCUMENTATION FIX

> **DEVELOPER NOTICE | CODEGEN IMPACT**
>
>  This documentation fix may impact generated client libraries.
>
>  Please regenerate your SDKs to ensure compatibility.

* Updated the attribute name from `portOfTranshipment` to `portsOfTransshipment` in the `GET /shipments/{shipmentId}`
  endpoint to match the actual API response.

### DOCUMENTATION UPDATE

* Updated code samples to use the correct server URL `https://api.kpler.com/v1/logistics/containers`

# [](https://container-tracking.marinetraffic.com/v2#tag/2025-05-26)2025-05-26

### NEW FEATURE

* New Endpoint, Container Shipping, Get schedule events `GET /shipping/schedule-events`
* New Server URL, `https://api.kpler.com/v1/logistics/containers`
* New API Key Header, `X-Container-API-Key`

### DEPRECATED

The following items are deprecated.

* Server URL, `https://api.kpler.com/v1/logistics/container-tracking`
* API Key Header, `X-Container-Tracking-API-Key`

> **IMPORTANT | MIRATION ACTION REQUIRED** We encourage you to migrate to the new server URL and API key header defined
> above as soon as possible. The old server URL and API key header remain functional. We have not set a removal date yet.

### DOCUMENTATION UPDATE

* Changelog introduction
* Global reorganization with the introduction of the Shipping schedules endpoint.

[](https://container-tracking.marinetraffic.com/)

[](https://container-tracking.marinetraffic.com/)
