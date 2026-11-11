# Pioneer Public Library System

IS 6465 Web Based Applications, University of Utah
Group 1: Brandon Hilton, Andy Huo, Cody Schlawin

Site source for the Pioneer Public Library System, a PHP web application. The
repository root **is** the site root: `index.php` sits at the top level, so the
project opens and runs without a nested folder.

Plain PHP 8, hand-written CSS, no framework, no Composer, and no build step.
Members, inventory, and checkouts are stored in **SQLite** (`DB/library.db`)
through PDO prepared statements. Staff check copies out and record returns at
the circulation desk pages. Sign-in still uses the two
demo accounts in `security/users.php`; moving login to the database is planned
for Phase 2.

---

## Running it

### In GitHub Codespaces (recommended)

Open the repository, choose **Code → Codespaces → Create codespace on main**, and
wait for the container to build. The dev container starts the PHP server on port
8000 by itself and forwards it, so the site opens in a browser tab as soon as the
container is ready. On start it runs `php DB/setup.php --if-missing`, which
builds `DB/library.db` only if the file is missing (it is committed, so normally
nothing happens).

If the tab does not appear, run this in the Codespace terminal:

```bash
php -S 0.0.0.0:8000 router.php
```

then open the forwarded port 8000 from the **Ports** panel.

### Locally

```bash
git clone https://github.com/cschlawin/pioneer-public-library.git
cd pioneer-public-library
php -S localhost:8000 router.php
```

Then open <http://localhost:8000/>. PHP 8.0 or newer with the `pdo_sqlite`
extension is required (`php -m | grep pdo_sqlite`); the official PHP images and
the Codespaces container include it.

Always start the server with `router.php`. The router returns 404 for the
`DB/` folder, `*.db`, `*.sql` and `*.md` files, `tests/`, `security/`,
`includes/`, dotfiles, and path traversal, so the database file can never be
downloaded. Real pages and assets are served normally.

### Demo accounts

These are the required test users for this project.

| Username | Password | Role | Display name |
|---|---|---|---|
| `bsmith` | `mysecret` | admin | B. Smith |
| `pjones` | `acrobat` | member | P. Jones |

Only password hashes are stored, in `security/users.php`, as `password_hash()`
output and checked with `password_verify()`. No page on the site displays a
password, and no file compares a plaintext password (BR 14, FR 33).

- **bsmith** can use every page, including both CRUD sets.
- **pjones** can sign in and see the home page, and is refused with a 403 page
  on every administrative screen. That is the role check working (FR 29, BR 10).

---

## Database: where the data lives

Members, inventory, and checkouts live in the SQLite file **`DB/library.db`**, which is
committed to the repository already seeded. The schema and seed
rows are in `DB/setup-database.sql`:

| Table | Holds | Notes |
|---|---|---|
| `Member` | One row per member | `Email` is `UNIQUE` (case-insensitive, FR 18); `Member_Status` is `active` or `inactive` (BR 06) |
| `Item` | One row per title | `Item_Type` picks the subtype; `Is_Deleted` is the soft delete (DR 14) |
| `Book`, `Music`, `Film`, `Periodical`, `Audiobook` | The descriptive fields for each media type | Keyed on `Item_ID`, as in the ERD |
| `Copy` | One row per physical copy | `Copy_Status` is `available`, `checked out`, or `missing` (BR 08); `Barcode` is unique |
| `Checkout` | One row per loan of one copy (DR 06) | `Check_Num` is `L-#####`; `Check_In_Date` is empty while the copy is out; a partial unique index allows at most one open loan per copy (BR 04); due date is 30 days after checkout (BR 01) |

`Member_ID` and `Item_ID` keep the `M-01045` / `I-04412` form the wireframes
show, and the seed rows are the same members, items and copies the Phase 1
pages always had. The seeded loans match the copies the seed marks checked
out and the history drawn on WF 05. `Checkout_Emp_ID` and `Checkin_Emp_ID`
record the staff account's username until an Employee table exists. Accounts,
employees, fees and payments are not created yet; the fees on a seeded
member's detail page are a read-only fixture in `DB/members.php` until fees are
built.

Every query lives in `DB/members.php`, `DB/inventory.php` and
`DB/checkouts.php` and uses PDO
prepared statements with `?` placeholders. `DB/db.php` opens the connection
with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, and `PRAGMA foreign_keys = ON`.
Writes that touch several rows, such as an item plus its copies, or a loan
plus its copy status, run in one transaction and roll back completely on any
error. The page files call the same
functions they did in Phase 1, so no view needed rewriting.

To rebuild the database from the SQL file:

```bash
php DB/setup.php                                  # works anywhere PHP runs
sqlite3 DB/library.db < DB/setup-database.sql     # same result, if sqlite3 is installed
```

`php DB/setup.php` deletes and recreates the file. The `sqlite3` command only
adds what is missing, because every `CREATE` is `IF NOT EXISTS` and every seed
row is `INSERT OR IGNORE`; delete `DB/library.db` first for a clean rebuild.

The **Reset demo data** button in the footer reseeds the member, inventory and
checkout tables. It is a POST with a CSRF token, so a link or a prefetch can never wipe
the data. Sessions are still used, but only for sign-in, CSRF tokens and flash
messages; no record data is kept in `$_SESSION` any more.

### Tests

```bash
php tests/integration-tests.php
```

The integration tests build a temporary database from `DB/setup-database.sql`
(they never touch `DB/library.db`) and exercise add, view, update and delete
for both CRUD sets: member deactivation (BR 06), duplicate email (FR 18), soft
delete and its refusal while a copy is checked out (BR 07, DR 14), the
checked-out status rule and invalid copy statuses (BR 08), transaction
rollback, foreign keys, and reset. For checkout and return they cover the
30-day due date (BR 01), every refusal (inactive member, fees at or above
$10.00, unknown barcode, copy already out or missing, deleted item, return with
no open loan), rollback of a failed checkout and a failed return, the one
open loan per copy index, and late days on a late return.

---

## Folder map

```
pioneer-public-library/
  index.php                 Home page: hero, catalog search over inventory, media tiles, get started, events (WF 01)
  login.php                 Staff sign-in, POST-only, one generic failure message (WF 02)
  logout.php                Destroys the session (FR 28)
  reset.php                 Reseeds the member, inventory and checkout tables, POST + CSRF
  router.php                Router for php -S: blocks DB/, *.db, *.sql, *.md, tests/, traversal
  phase2.php                Placeholder for Employees / Reports / Accounts, which are out of Phase 1 scope
  README.md                 This file
  .devcontainer/            Codespaces container: PHP 8.3 image, port 8000 forwarded, server auto-started with router.php

  includes/
    header.php              Doctype, head, CSS link, and the site header in public or admin mode
    footer.php              Closes the page, carries the Reset demo data action
    flash.php               One-shot flash messages, set before a redirect and rendered once

  security/
    auth.php                Session start, security headers, sign-in, sign-out, require_login(), require_admin(), 403 page
    csrf.php                csrf_token(), csrf_field(), csrf_check()
    sanitize.php            e() escape helper plus every validate_* function and the field/aria helpers
    users.php               The two demo accounts. Bcrypt hashes only, no plaintext

  DB/
    library.db              The SQLite database, committed already seeded
    setup-database.sql      Schema (CREATE TABLE IF NOT EXISTS) and seed rows
    setup.php               CLI: php DB/setup.php rebuilds library.db from the SQL file
    db.php                  openDatabase() and db(): the PDO connection, plus db_transaction()
    members.php             Member queries: find, insert, update, set status, search, email check, reset
    inventory.php           Item, subtype and copy queries: find, derived copy counts, insert, update, soft delete, search, reset
    checkouts.php           Checkout and return: loan lookups, checkout_create(), checkout_return(), the loan rules
    reference.php           Media types, copy conditions, copy statuses, member statuses

  members/
    list.php                UC 10  View and search members (WF 03)
    add.php                 UC 11  Add a member (WF 04)
    view.php                UC 12  Member detail with history and the Deactivate/Reactivate control (WF 05)
    edit.php                UC 13  Update a member (WF 05, edit state)
    delete.php              UC 13  Deactivate or reactivate. POST only; never removes the row

  inventory/
    list.php                UC 14  View and search inventory (WF 06)
    add.php                 UC 15  Add an item and create its copies (WF 07)
    view.php                UC 16  Item detail, copy table, Delete control (WF 08)
    edit.php                UC 17  Update an item and its copies (WF 08, edit state)
    delete.php              UC 17  Soft delete. POST only; refused while a copy is checked out

  circulation/
    checkout.php            UC 04  Check out an item at the staff desk (WF 20)
    return.php              UC 06  Return an item at the staff desk (WF 21)

  images/
    logo.svg                Wordmark with the book mark
    placeholder-cover.svg   Neutral catalogue cover tile
    favicon.svg             Green square with the open book
    README.md               Note that this folder holds all site imagery

  css/
    site.css                The whole stylesheet. Palette in CSS variables

  js/
    site.js                 data-confirm prompts and the media-type field switch. No inline script anywhere

  tests/
    integration-tests.php   CLI tests for both CRUD sets and checkout/return on a temporary copy of the database
```

---

## Use case map

### Set 1 — Member management (UC 10–UC 13, 3 pts)

| UC | Use case | File | Requirement | What the code does |
|---|---|---|---|---|
| UC 10 | View and search members | `members/list.php` | FR 17 | Server-side search over last name, first name, email, phone, and member ID, plus an Active / Inactive / All filter. Every row carries Edit and Deactivate (or Reactivate). |
| UC 11 | Add member | `members/add.php` | FR 18 | Validates all eight fields on the server, checks email shape with `FILTER_VALIDATE_EMAIL` and uniqueness case-insensitively across the whole table, assigns the next `M-#####` ID and today's date on save. |
| UC 12 | View member detail | `members/view.php` | FR 19 | Contact fields read-only, current and past loans from the `Checkout` table, fees, unpaid balance, and the Deactivate control. |
| UC 13 | Update / deactivate | `members/edit.php`, `members/delete.php` | FR 19, FR 20 | Edit changes contact fields only; member ID and start date are read-only (DR 02). Deactivate flips status to inactive and **keeps the row and all history** (BR 06). |

### Set 2 — Inventory management (UC 14–UC 17, 3 pts)

| UC | Use case | File | Requirement | What the code does |
|---|---|---|---|---|
| UC 14 | View and search inventory | `inventory/list.php` | FR 21, FR 22 | Search by title, creator, or ISBN; filter by media type and by copy status. The four counts (Total, Avail, Out, Missing) are **derived from the copy records on every render**, never stored. |
| UC 15 | Add inventory item | `inventory/add.php` | FR 22 | Media type picks which descriptive fields apply (DR 04); the copy count creates that many copy records, each Available (BR 08). |
| UC 16 | View item detail | `inventory/view.php` | FR 23 | Descriptive fields, derived counts, the copy table, and the Delete control. |
| UC 17 | Update / delete item | `inventory/edit.php`, `inventory/delete.php` | FR 23, FR 24 | Edit changes descriptive fields and each copy's condition and status. Delete is a **soft delete**: flagged deleted, copies kept (DR 14), and **refused while any copy is checked out** (BR 07). |

### Circulation — Checkout and return at the staff desk (UC 04, UC 06)

| UC | Use case | File | Requirement | What the code does |
|---|---|---|---|---|
| UC 04 | Check out an item | `circulation/checkout.php` | FR 06, FR 07, FR 08 | Staff choose the member and scan the barcode. One transaction writes the loan (due in 30 days, BR 01) and marks the copy checked out. Refused for an inactive member (BR 05), unpaid fees of $10.00 or more (BR 03), an unknown barcode, a copy that is not available (BR 04), or a deleted item. |
| UC 06 | Return an item | `circulation/return.php` | FR 10 | Staff scan the barcode. One transaction records the check-in date and makes the copy available again. Refused when the copy has no open loan. Shows days late; the late fee itself (BR 02, FR 11) is a later step. |

Both pages are admin-only (`require_admin()`, BR 10) and appear in the admin
navigation as **Check out** and **Return**.

---

## Behaviour the markers should check

**Every mutation is POST-only with a CSRF token.** `members/add.php`,
`members/edit.php`, `members/delete.php`, `inventory/add.php`,
`inventory/edit.php`, `inventory/delete.php`, `circulation/checkout.php`,
`circulation/return.php`, and `reset.php` all call
`csrf_check()`. A GET on either delete handler returns **405 Method Not
Allowed**, so a link, a prefetch, or a crawler cannot change anything. After a
successful mutation the handler sets a flash message and redirects with **303**,
so a refresh never re-submits and no POST result is ever re-rendered.

**Server-side validation (FR 32).** Every form re-renders with the submitted
values still in place, a message under each bad field, `aria-invalid="true"`,
and `aria-describedby` pointing at the message. The email message is the exact
wireframe copy: *Enter an email address with an @ and a domain.* A failed
submission returns **422**, which is a validation failure rather than a new
resource.

**Members are deactivated, never deleted (BR 06).** `members/delete.php` is a
status change. The row stays in the list marked Inactive, its checkout and fee
history stays visible, and the button flips from Deactivate to Reactivate. The
UI says so: *Deactivating stops borrowing but keeps all history (BR 06).*

**Inventory delete is refused while a copy is out (BR 07).** The server returns
a message naming how many copies are checked out and what to do. The button on
WF 06 and WF 08 is drawn disabled with a `title` tooltip explaining why.

**Checked out is never set by hand (BR 08).** The copy status dropdown offers
Available and Missing only. A copy that is already checked out is locked: its
control is disabled, and a crafted POST that tries to change it is ignored, so
the copy stays checked out.

**Auth.** Passwords are verified with `password_verify()` against bcrypt hashes.
A successful sign-in calls `session_regenerate_id(true)`. A failed sign-in shows
one message — *The username or password is incorrect.* — that does not reveal
whether the username or the password was wrong. A missing username still runs a
dummy `password_verify` so the two failures take the same time. Logout destroys
the session.

**Escaping.** Every dynamic value that reaches HTML goes through `e()`. A member
named `<script>alert(1)</script>` renders as inert text everywhere.

**Security headers.** `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
and a `Content-Security-Policy` of `default-src 'self'; script-src 'self';
style-src 'self'; img-src 'self' data:; font-src 'self'; form-action 'self';
frame-ancestors 'none'; base-uri 'self'; object-src 'none'`. There is no inline
script and no inline style attribute anywhere, which is why the policy needs no
`unsafe-inline`.

**Accessibility.** Every control has a `label for`. The current nav item carries
`aria-current="page"`. Each table has a `caption` with class `sr-only`. Error
messages are tied to their field with `aria-describedby` and the field gets
`aria-invalid="true"`. There is a skip link, and focus is always visible.

---

## Scope note

Phase 1 covers the home page, the login page, and two CRUD sets. Checkout and
return at the staff desk (UC 04, UC 06) were added on top of that.

The admin header lists Members, Inventory, Check out, Return, Employees,
Reports and Accounts. **Members**, **Inventory**, **Check out** and **Return**
are real, working pages. **Employees**, **Reports**, and **Accounts** point to
`phase2.php`, which says the screen is scheduled for Phase 2 and holds no data
or action.

Deliberately not built: employee management, the reports dashboard, account and
role administration, password reset, the class list and signup, the member
cart, fines and payments, member profile editing, and moving login accounts
into the database (planned for Phase 2).

---

## Known limits

- Everyone using the same running site shares one database, so changes made in
  one browser are visible in another. Use **Reset demo data** to go back to the
  seed.
- Login still uses the two demo accounts in `security/users.php`, not a
  database table, until the Phase 2 authentication work.
- The catalog search on the home page reads the inventory table and shows
  availability, but **Add to cart** leads to the login page because the member
  self-service cart is a Phase 2 use case. Staff check items out at
  **Check out**.
- `php -S` is single-threaded; it is a development server, not a deployment
  target.

AI Disclosure: AI assisted with drafting, organizing, and revising project documentation, diagrams, and code. The team remains responsible for the accuracy, quality, originality, and compliance of the submitted work, including verification of AI-assisted content. Tools Used: ChatGPT, Claude, and xAI.
