# Pioneer Public Library System

IS 6465 Web Based Applications, University of Utah
Team: Cody Schlawin, Cody Adamson, Jonas Nunn

Site source for the Pioneer Public Library System, a PHP web application. The
repository root **is** the site root: `index.php` sits at the top level, so the
project opens and runs without a nested folder.

Phase 1 coding artifact. Plain PHP 8, hand-written CSS, **no database**, no
framework, no Composer, and no build step.

---

## Running it

### In GitHub Codespaces (recommended)

Open the repository, choose **Code → Codespaces → Create codespace on main**, and
wait for the container to build. The dev container starts the PHP server on port
8000 by itself and forwards it, so the site opens in a browser tab as soon as the
container is ready.

If the tab does not appear, run this in the Codespace terminal:

```bash
php -S 0.0.0.0:8000
```

then open the forwarded port 8000 from the **Ports** panel.

### Locally

```bash
git clone https://github.com/cschlawin/pioneer-public-library.git
cd pioneer-public-library
php -S localhost:8000
```

Then open <http://localhost:8000/>. PHP 8.0 or newer is required, with nothing
else to install or configure.

### Demo accounts

These are the instructor-required test users for this project.

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

## No database: where the data lives

Persistence is **PHP sessions over arrays**. On first use, `DB/members.php` and
`DB/inventory.php` copy their seed arrays into `$_SESSION`. Every read and write
goes through the functions in those two files, so replacing them with SQL in
Phase 2 changes the data layer only — no view has to be touched.

Data survives page navigation within a session. To restore the seed at any
time, use the **Reset demo data** button in the footer. It is a POST with a CSRF
token, so a link or a prefetch can never wipe the session.

---

## Folder map

```
pioneer-public-library/
  index.php                 Home page: hero, catalog search over inventory, media tiles, get started, events (WF 01)
  login.php                 Staff sign-in, POST-only, one generic failure message (WF 02)
  logout.php                Destroys the session (FR 28)
  reset.php                 Restores the seeded demo tables, POST + CSRF
  phase2.php                Placeholder for Employees / Reports / Accounts, which are out of Phase 1 scope
  README.md                 This file
  .devcontainer/            Codespaces container: PHP 8.3 image, port 8000 forwarded, server auto-started

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
    members.php             Member table: seed, boot, load/save, find, insert, update, set status, search
    inventory.php           Inventory table: seed, boot, load/save, find, derived copy counts, insert, update, soft delete, search
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

  images/
    logo.svg                Wordmark with the book mark
    placeholder-cover.svg   Neutral catalogue cover tile
    favicon.svg             Green square with the open book
    README.md               Note that this folder holds all site imagery

  css/
    site.css                The whole stylesheet. Palette in CSS variables

  js/
    site.js                 data-confirm prompts and the media-type field switch. No inline script anywhere
```

---

## Use case map

### Set 1 — Member management (UC 10–UC 13, 3 pts)

| UC | Use case | File | Requirement | What the code does |
|---|---|---|---|---|
| UC 10 | View and search members | `members/list.php` | FR 17 | Server-side search over last name, first name, email, phone, and member ID, plus an Active / Inactive / All filter. Every row carries Edit and Deactivate (or Reactivate). |
| UC 11 | Add member | `members/add.php` | FR 18 | Validates all eight fields on the server, checks email shape with `FILTER_VALIDATE_EMAIL` and uniqueness case-insensitively across the whole table, assigns the next `M-#####` ID and today's date on save. |
| UC 12 | View member detail | `members/view.php` | FR 19 | Contact fields read-only, checkout history and fees, unpaid balance, and the Deactivate control. |
| UC 13 | Update / deactivate | `members/edit.php`, `members/delete.php` | FR 19, FR 20 | Edit changes contact fields only; member ID and start date are read-only (DR 02). Deactivate flips status to inactive and **keeps the row and all history** (BR 06). |

### Set 2 — Inventory management (UC 14–UC 17, 3 pts)

| UC | Use case | File | Requirement | What the code does |
|---|---|---|---|---|
| UC 14 | View and search inventory | `inventory/list.php` | FR 21, FR 22 | Search by title, creator, or ISBN; filter by media type and by copy status. The four counts (Total, Avail, Out, Missing) are **derived from the copy records on every render**, never stored. |
| UC 15 | Add inventory item | `inventory/add.php` | FR 22 | Media type picks which descriptive fields apply (DR 04); the copy count creates that many copy records, each Available (BR 08). |
| UC 16 | View item detail | `inventory/view.php` | FR 23 | Descriptive fields, derived counts, the copy table, and the Delete control. |
| UC 17 | Update / delete item | `inventory/edit.php`, `inventory/delete.php` | FR 23, FR 24 | Edit changes descriptive fields and each copy's condition and status. Delete is a **soft delete**: flagged deleted, copies kept (DR 14), and **refused while any copy is checked out** (BR 07). |

---

## Behaviour the markers should check

**Every mutation is POST-only with a CSRF token.** `members/add.php`,
`members/edit.php`, `members/delete.php`, `inventory/add.php`,
`inventory/edit.php`, `inventory/delete.php`, and `reset.php` all call
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

Phase 1 grades the home page, the login page, and two CRUD sets. Nothing else
was built.

The approved navigation design lists six admin labels, so the header keeps all
six. **Members** and **Inventory** are real, working pages. **Employees**,
**Reports**, and **Accounts** point to `phase2.php`, which says the screen is
scheduled for Phase 2 and holds no data or action.

Deliberately not built: employee management, the reports dashboard, account and
role administration, password reset, the class list and signup, returns and
fines, member profile editing, and any database schema, SQL, PDO, or MySQL work.

---

## Known limits

- Session storage means a second browser, or a cleared cookie, starts from the
  seed again. That is the Phase 1 design: no database.
- The catalog search on the home page reads the inventory table and shows
  availability, but **Add to cart** leads to the login page because checkout is
  a Phase 2 use case.
- `php -S` is single-threaded; it is a development server, not a deployment
  target.
