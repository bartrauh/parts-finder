# Parts Finder: Fullstack Technical Assignment

## Goal

Build a small but complete Parts Finder: a Nuxt frontend backed by your own Laravel API, in which a mechanic or workshop locates the right truck part and compiles a quote list. The quote is a server-side resource that belongs to a workshop, follows B2B rules (prices excl. VAT, workshop discount, backorders, price snapshot on submit) and is never a shopping cart with a checkout.

You own the full slice: data model, migrations and seed data, the API contract, the business rules, the UI and the tests. We assess how you split responsibilities between frontend and backend as much as what you build.

## Required stack

| Layer | Requirement |
| --- | --- |
| Frontend | Nuxt 4 + Vue 3, Tailwind CSS, fully responsive |
| Backend | Laravel 12 (PHP 8.3 or newer), REST JSON API under `/api` |
| Database | MySQL 8 via migrations and seeders; SQLite is acceptable for the automated test run only |
| Local setup | `docker compose up` (or an equivalent single command) brings up frontend, API and database |
| Testing | Backend feature tests (PHPUnit or Pest) and frontend unit/component tests (Vitest); see Testing |
| Repository | One Git repository with `frontend/` and `backend/`, incremental commit history |

The frontend talks to the API over HTTP only. No shared database access, no Nuxt server routes that bypass Laravel.

## Functional scope: frontend

All data comes from your own API. The frontend does not filter, sort or compute totals itself; it passes the user's intent to the API and renders the result.

1. **Overview page.** A list or grid of part cards fetched from `GET /api/parts`, with server-side pagination. Each card shows at minimum: name, OEM number, brand, condition, price (excl. VAT), stock indicator. Show loading, empty and error states.
2. **Search, filter and sort.** Search matches on part name or OEM number. Filters: brand (multi-select) and condition. Sorting: price high to low and low to high. The active search, filters, sort and page live in the URL so a filtered view can be shared and survives a refresh.
3. **Detail page.** Extended part information with a larger image (placeholder is fine), the available colours when applicable, a quantity input and an Add to Quote button.
4. **Quote list.** Visible as a sidebar, mini drawer or separate route, backed by the quote endpoints. Shows the lines with quantity, the backorder flag where applicable, and the totals exactly as the API returns them (subtotal, discount, VAT, total). The user can change a quantity, remove a line and submit the quote. After submission the quote is shown read-only and the user can start a new one.

Persistence bonus from the original assignment, adapted: remember the active quote id in `localStorage` so a refresh or a new tab restores the open draft from the API.

## Functional scope: backend API

A JSON REST API in Laravel. Every request carries `Authorization: Bearer <workshop token>`; two workshops with a token each come from the seeder, and the frontend reads its token from an environment variable. No login UI, no user registration. A missing or unknown token returns 401; a quote that belongs to another workshop returns 404.

| Method and path | Purpose | Notes |
| --- | --- | --- |
| `GET /api/parts` | List parts | Query params: `q` (name or OEM), `brand[]`, `condition`, `sort` (`price_asc`, `price_desc`), `page`, `per_page` (max 50). Returns `data` plus `meta` with total and page info |
| `GET /api/parts/{id}` | Part detail | Includes `colours` and `stockQuantity` |
| `GET /api/brands` | Brand list for the filter | Bonus: counts that respect the active `q` and `condition` |
| `POST /api/quotes` | Create a draft quote for the workshop | Returns the empty quote with totals at zero |
| `GET /api/quotes/{id}` | Fetch a quote | Lines, flags, warnings and totals |
| `POST /api/quotes/{id}/lines` | Add a part | Body: `partId`, `quantity`, optional `colour` |
| `PATCH /api/quotes/{id}/lines/{lineId}` | Change quantity or colour | |
| `DELETE /api/quotes/{id}/lines/{lineId}` | Remove a line | |
| `POST /api/quotes/{id}/submit` | Submit the quote | Applies the submit rules, locks the quote |

Validation errors return 422 with Laravel's standard error shape. Mutations on a submitted quote return 409. Totals are computed on the server only and returned with every quote response, so the frontend never adds numbers up.

## Data model and seed data

Model these entities with migrations, foreign keys and indexes on the columns you search and filter on. Field names are yours to choose; the behaviour below is not.

| Entity | Must hold | Notes |
| --- | --- | --- |
| Workshop | name, API token, discount percentage | Seed two: one with 0% discount, one with 10% |
| Part | name, OEM number, brand, condition (`new` or `used`), price excl. VAT, stock quantity, category, image path, available colours | `inStock` is derived from stock quantity, not stored separately |
| Quote | workshop, status (`draft` or `submitted`), submitted timestamp, totals | Totals are stored at submit time and recomputed live while draft |
| Quote line | quote, part, quantity, chosen colour, backorder flag, unit price snapshot | The snapshot is filled on submit and is the price shown afterwards |

Seed at least 40 parts across four or more brands, both conditions, several out of stock, and at least five with colours. The seeder may start from a JSON file in this shape:

```json
{
  "id": "2119711",
  "name": "DAF XF 106 Sideskirt set",
  "oem": "2119711",
  "brand": "DAF",
  "condition": "used",
  "price": 795,
  "stockQuantity": 3,
  "category": "Body",
  "image": "/img/placeholder-part.png",
  "colours": ["white", "red", "blue"]
}
```

The `id` and `oem` happen to match in this example. Do not treat that as a rule: a part has its own identity and the OEM number is a searchable attribute.

## Quote workflow and business rules

A quote is a draft until the workshop submits it; after that it is read-only. Implement these rules in the backend, in a place that is independent of the HTTP layer, and prove each one with a test.

1. **Prices and totals.** All prices are excl. VAT. Line total = unit price × quantity. Subtotal = sum of line totals. Discount = subtotal × workshop discount percentage. VAT = 21% over subtotal minus discount. Total = subtotal minus discount plus VAT. Round to cents at each step and return all five amounts.
2. **Quantity.** An integer of 1 or more. For a part in stock, quantity may not exceed the stock quantity. For a part out of stock, any quantity is allowed and the line is flagged `backorder: true`; the quote response carries a warning listing its backorder lines.
3. **Merging lines.** Adding a part that is already on the quote with the same colour increases that line's quantity instead of creating a second line. A different colour is a separate line.
4. **Colour.** A line's colour must be one of the part's available colours, or empty when the part has none.
5. **Submit.** Requires at least one line. Re-validates every in-stock line against the current stock quantity, since stock may have changed since the line was added. On success: store the unit price snapshot on each line, store the totals, set the status and timestamp. On failure: 422 with the offending lines, and the quote stays a draft.
6. **Immutability.** After submission every mutation returns 409. A later price change on a part does not change the submitted quote.
7. **Isolation.** A workshop only sees and changes its own quotes.

## Testing

Both suites run green with one documented command each. Test behaviour, not implementation.

**Backend (PHPUnit or Pest), required:**

- Feature tests for every endpoint, including the unhappy paths: 401 without a token, 404 for another workshop's quote, 422 on invalid quantity or colour, 409 on a submitted quote.
- A test per business rule in the previous section. At minimum: line merging, backorder flagging, the totals calculation with a 10% workshop, and the price snapshot surviving a later price change.
- Search and filter tests on `GET /api/parts` that assert what is returned and what is excluded.

**Frontend (Vitest), required:**

- Component or composable tests for the quote drawer: rendering lines and totals from an API response, remove and quantity actions calling the right endpoint.
- A test that the overview page reflects the URL state (search, filters, sort, page) in its API request.

**End-to-end (Playwright), nice to have:** one happy path from search to submitted quote against the running stack.

## Nice to have

Pick what shows your strengths; none of these compensate for a missing required part.

- End-to-end tests with Playwright
- "Available in colour" badge on applicable parts, and a colour dropdown on the detail page that visually reflects the selected colour in the UI
- An OpenAPI specification for your API, with the frontend's TypeScript types generated from it
- Brand facet counts on `GET /api/brands` that respect the active search and condition filter
- An Artisan command that imports parts from a JSON or CSV file, idempotent on re-run
- A `.gitlab-ci.yml` that runs both test suites
- Rate limiting on the API and cache headers (ETag or Cache-Control) on `GET /api/parts`
- A surprising element of your own choosing: something not specified here that shows initiative

## Deliverables, constraints and time box

Spend about 8 hours and no more than 12. We prefer a smaller slice that is finished, tested and explained over everything half done. If you cut scope, say what you cut and why.

Deliver a link to a Git repository within 7 calendar days of receiving this assignment, containing:

- `frontend/` and `backend/` with an incremental commit history (no single squash commit)
- `README.md`: how to start the stack and run each test suite, in five commands or fewer
- `DECISIONS.md`: three to five decisions you made, each with the alternative you rejected and why. Examples: where the business rules live, how you handle the price snapshot, how pagination and filtering are shaped
- A short list of what you would do next with another day

AI assistants are allowed. State in the README which tools you used and for what. During the review we go through the code together and expect you to explain and defend every part of it, generated or not.
