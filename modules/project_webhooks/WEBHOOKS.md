# Project Webhooks — Event & Payload Reference

The CRM sends an HTTP `POST` to your configured endpoint whenever a project is
created, updated, changes status, or has members added or removed. Deliveries
are queued and sent in the background by cron.

Events: `project.created`, `project.updated`, `project.status_changed`,
`project.member_added`, `project.member_removed`.

- **Method:** `POST`
- **URL:** the value of `PROJECT_WEBHOOK_URL` (in `modules/project_webhooks/project_webhooks.php`)
- **Content-Type:** `application/json`
- **Body:** raw JSON (read it from the raw request body, not form fields)

## Request headers

| Header | Value |
|--------|-------|
| `Content-Type` | `application/json` |
| `X-Webhook-Event` | the event name, e.g. `project.status_changed` |

## Common payload envelope

Every event shares the same envelope keys — `event`, `occurred_at`, `project`,
`data`, `triggered_by`. The **`project` object and the `data` object vary per
event** (see each event below and the per-event `project`-shape table).

Example (a `project.created` payload, which carries the fullest `project`):

```json
{
  "event": "project.created",
  "occurred_at": "2026-07-08T17:35:11+05:30",
  "project": {
    "id": 12,
    "name": "Website Redesign",
    "website_url": "https://example.com",
    "clientid": 5,
    "status": 4,
    "status_name": "Completed",
    "company": "Acme Ltd",
    "start_date": "2026-01-10",
    "deadline": "2026-03-01"
  },
  "data": {
    "members": [
      { "staff_id": 17, "email": "jaymin.patel@concatstring.com", "name": "Jaymin Patel", "role_id": 1, "role": "Team Leaders" }
    ]
  },
  "triggered_by": { "type": "staff", "id": 3, "name": "Jaymin Patel" }
}
```

### Envelope fields

| Field | Type | Description |
|-------|------|-------------|
| `event` | string | Event name (see below). Also sent in `X-Webhook-Event`. |
| `occurred_at` | string | ISO-8601 timestamp with timezone offset (PHP `date('c')`). |
| `project` | object\|null | Project details captured **when the event occurred** (stored with the queued event, not re-read at delivery). `null` only if the project could not be found at that moment. |
| `data` | object | Event-specific fields (see each event). May be empty `{}`. |
| `triggered_by` | object | Who caused the change. |

### `project` object

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | Project id |
| `name` | string | Project name |
| `website_url` | string\|null | The project's Website URL (form field below the customer); `null` if unset |
| `clientid` | int | Customer id |
| `status` | int | Status id (see status table) |
| `status_name` | string | Human label for the status |
| `company` | string | Customer company (falls back to primary contact name) |
| `start_date` | string\|null | `YYYY-MM-DD` |
| `deadline` | string\|null | `YYYY-MM-DD` |

**The `project` object shape differs per event** (only the relevant fields are sent):

| Event | `project` fields |
|-------|------------------|
| `project.created` | all fields above |
| `project.updated` | `id`, `name`, `clientid`, `website_url` |
| `project.status_changed` | `id`, `name` |
| `project.member_added` | `id`, `name` |
| `project.member_removed` | `id`, `name` |

### `triggered_by` object

| Field | Type | Notes |
|-------|------|-------|
| `type` | string | `staff`, `contact`, `cron`, or `system` |
| `id` | int | Staff/contact id (`0` for cron/system) |
| `name` | string | Display name (`[CRON]` for cron runs) |

### Project status ids

| id | Name | id | Name |
|----|------|----|------|
| 1 | Planning | 6 | Active |
| 2 | In Progress | 7 | Approved |
| 3 | On Hold | 8 | In Testing |
| 4 | Completed | 9 | On Track |
| 5 | Invoiced | 10 | Blocked |

---

## Events

### `project.created`
Fired when a new project is created.

- `X-Webhook-Event: project.created`
- `data.members` (array): the project's initial members, each with:
  - `staff_id` (int)
  - `email` (string)
  - `name` (string)
  - `role_id` (int) — the staff role id (`tblstaff.role`; `0` if none)
  - `role` (string\|null) — the staff role name (`tblroles.name`).
    Falls back to `"Administrator"` for admins with no role, else `null`.

```json
{
  "event": "project.created",
  "occurred_at": "2026-07-08T17:35:11+05:30",
  "project": { "id": 20, "name": "Full Cycle Test", "website_url": "https://example.com", "clientid": 8, "status": 2, "status_name": "In Progress", "company": "Acme Ltd", "start_date": "2026-01-10", "deadline": null },
  "data": {
    "members": [
      { "staff_id": 17, "email": "jaymin.patel@concatstring.com", "name": "Jaymin Patel", "role_id": 1, "role": "Team Leaders" },
      { "staff_id": 4, "email": "bansari.radadiya@concatstring.com", "name": "Bansari Radadiya", "role_id": 8, "role": "Employee" }
    ]
  },
  "triggered_by": { "type": "staff", "id": 3, "name": "Jaymin Patel" }
}
```

> `data.members` is sent **only** on `project.created`. Members added later
> arrive as `project.member_added` events; members removed arrive as
> `project.member_removed`.

### `project.updated`
Fired when a project is edited (edit form save).

- `X-Webhook-Event: project.updated`
- `project`: **reduced** object — only `id`, `name`, `clientid`, `website_url`
- `data`: `{}`

```json
{
  "event": "project.updated",
  "occurred_at": "2026-07-08T17:36:02+05:30",
  "project": { "id": 12, "name": "Website Redesign v2", "clientid": 5, "website_url": "https://example.com" },
  "data": {},
  "triggered_by": { "type": "staff", "id": 3, "name": "Jaymin Patel" }
}
```

### `project.status_changed`
Fired when the project status changes (edit form or inline status dropdown).

- `X-Webhook-Event: project.status_changed`
- `project`: minimal identity — only `id`, `name`
- `data.status` (int): the new status id
- `data.status_name` (string): the new status label

```json
{
  "event": "project.status_changed",
  "occurred_at": "2026-07-08T17:38:20+05:30",
  "project": { "id": 12, "name": "Website Redesign" },
  "data": { "status": 4, "status_name": "Completed" },
  "triggered_by": { "type": "staff", "id": 3, "name": "Jaymin Patel" }
}
```

### `project.member_added`
Fired **once per save**, carrying **all** members added in that save. Adding
three members at once produces one webhook with three entries in `data.members`.

- `X-Webhook-Event: project.member_added`
- `project`: minimal identity — only `id`, `name`
- `data.members` (array): the added members, **same shape as the
  `project.created` members** — `staff_id`, `email`, `name`, `role_id`, `role`

```json
{
  "event": "project.member_added",
  "occurred_at": "2026-07-08T17:40:05+05:30",
  "project": { "id": 12, "name": "Website Redesign" },
  "data": {
    "members": [
      { "staff_id": 7, "email": "riya.shah@concatstring.com", "name": "Riya Shah", "role_id": 8, "role": "Employee" },
      { "staff_id": 9, "email": "dhruv.bhavsar@concatstring.com", "name": "Dhruv Bhavsar", "role_id": 5, "role": "Project Manager" }
    ]
  },
  "triggered_by": { "type": "staff", "id": 3, "name": "Jaymin Patel" }
}
```

> Members present at project creation are **not** re-sent as `project.member_added`
> (that moment is covered by `project.created`).

### `project.member_removed`
Fired **once per save**, carrying **all** members removed in that save.

- `X-Webhook-Event: project.member_removed`
- `project`: minimal identity — only `id`, `name`
- `data.members` (array): the removed members, same shape as `project.member_added`
  (`staff_id`, `email`, `name`, `role_id`, `role`). Details are read from the
  staff record, since the member no longer belongs to the project.

```json
{
  "event": "project.member_removed",
  "occurred_at": "2026-07-08T17:42:10+05:30",
  "project": { "id": 12, "name": "Website Redesign" },
  "data": {
    "members": [
      { "staff_id": 9, "email": "dhruv.bhavsar@concatstring.com", "name": "Dhruv Bhavsar", "role_id": 5, "role": "Project Manager" }
    ]
  },
  "triggered_by": { "type": "staff", "id": 3, "name": "Jaymin Patel" }
}
```

**Identifying a removal:** check `event === "project.member_removed"` (or the
`X-Webhook-Event` header). The removed staff are in `data.members[].staff_id`.

---

## Expected response from your endpoint

| Your response | CRM behaviour |
|---------------|---------------|
| **HTTP 2xx** (200–299) | Marked `sent`. Done. |
| Any non-2xx (4xx/5xx), timeout, or connection error | Marked for **retry**. |

- Only the **status code** matters. The response body is stored (truncated to
  1000 chars) for debugging but is otherwise ignored.
- Respond quickly. The CRM waits at most **15s** (connect timeout 5s); a slower
  response is treated as a failure and retried.
- Return `2xx` **as soon as you have accepted the event**; do heavy processing
  asynchronously on your side so you don't hit the timeout.

### Recommended success response
```json
{ "ok": true }
```
(Any 2xx works — an empty `200` is fine too.)

## Retries & delivery semantics

- **At-least-once delivery.** A slow/failed ack can cause the same event to be
  re-sent, so make your handler **idempotent** (e.g. de-dupe on
  `event` + `project.id` + `occurred_at`, or your own event id).
- **Retry schedule:** exponential backoff of `2^attempts` minutes (2, 4, 8, 16 …)
  up to **5 attempts**, after which the event is marked `failed` and not retried.
- **Ordering is not guaranteed.** Use `occurred_at` if you need to order events.
- **Timing:** events are delivered by cron, so expect up to ~5 minutes latency
  (the CRM cron throttle) plus your server's cron interval.

## Security note

Deliveries are currently **unsigned** (no HMAC/token). Restrict your endpoint
to a trusted network, or ask to enable request signing if it must be public.

## Receiving example (Express / Node)

```js
app.post('/api/v1/admin/webhook', express.json(), (req, res) => {
  const event = req.header('X-Webhook-Event'); // or req.body.event
  console.log(event, req.body);
  // ...handle idempotently...
  res.sendStatus(200); // acknowledge (2xx) so it isn't retried
});
```

## Receiving example (PHP)

```php
$event = $_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? '';
$data  = json_decode(file_get_contents('php://input'), true);
// ...handle idempotently...
http_response_code(200);
echo json_encode(['ok' => true]);
```
