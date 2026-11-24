# Integration API — Finance — Detailed Reference

An extension of the mobile app (Flutter) API: receipt vouchers, expense vouchers, treasury and transfers, a finance summary, and clients. Everything here sits behind the same gate as [`DETAILED_REFERENCE.md`](./DETAILED_REFERENCE.md): Sanctum authentication, restricted to employee accounts (`EnsureEmployeeUser`).

| | |
|---|---|
| **Base URL** | `{{base_url}}/api/integration/v1/finance` |
| **Format** | JSON — header `Accept: application/json` |
| **Auth** | `Authorization: Bearer {token}` |
| **Permissions** | Every route carries a `permission.role:...` that matches its web-screen permission literally — no separate catalog. A user missing the required permission gets `403` before any logic runs. |

## Contents

1. [Implementation principle](#implementation-principle--delegation-not-reimplementation)
2. [Status codes](#status-codes)
3. [Data scope](#data-scope)
4. [Taxes](#taxes)
5. [File attachments](#file-attachments)
6. [Endpoint index](#endpoint-index)
7. [Finance summary](#1-finance-summary)
8. [Receipt vouchers](#2-receipt-vouchers-سندات-القبض)
9. [Expense vouchers](#3-expense-vouchers-سندات-الصرف)
10. [Treasury and transfers](#4-treasury-and-transfers)
11. [Clients](#5-clients)
12. [Developer notes](#developer-notes)

---

## Implementation principle — delegation, not reimplementation

Every write endpoint here (create/update/cancel/transfer) calls the **exact same** method on the existing web controller (`IncomesController` / `ExpensesController` / `TreasuryController`, all under `Modules\Account\Http\Controllers\Finance`) — same validation, same accounting entries, same Arabic error messages. The controller here (`Modules\Integration\Http\Controllers\Api\FinanceController`) doesn't know how a journal entry is built; it only knows how to translate the web response into JSON.

Which means **the response shape is not uniform across endpoints** — documented precisely below rather than glossed over:

| Group | Shape |
|---|---|
| Delegated list endpoints (`GET receipts`, `GET expenses`) | The original web `getData()` shape — **no** `success` / `data` wrapper; the main key is `incomes` or `expenses` directly |
| Delegated write endpoints (`store` / `update` / `cancel` / `delete` / transfer) | `{ success, message }` or `{ success, message, redirect }` — `redirect` is a web-page URL, **ignored** by the mobile client |
| Everything else — endpoints built specifically for this API (summary, create-data, show, clients, statement, treasuries) | The uniform shape `{ success, data }` |

---

## Status codes

| Code | Meaning |
|---|---|
| `200` | Success |
| `401` | Missing or expired access token |
| `402` | Subscription expired (general `EnsureSubscription` middleware) |
| `403` | Not an employee account, permission missing, or the record / client / treasury is outside the caller's scope |
| `404` | Record not found (`findOrFail` on `show` endpoints) |
| `422` | Validation error, or a business error returned by the delegated web function — insufficient balance, a "voucher not found" reported as a generic error by `cancel()` (see [§2.6](#26-cancel)) |

---

## Data scope

Not a parameter you send — resolved from the token holder himself, exactly the same rule the web screens apply:

| User state | What he sees |
|---|---|
| Holds the `data_scope_all` permission | Everything — clients, vouchers, treasuries, no restriction |
| Manages others (has subordinates in the management chain) | His own data **+** everyone he manages |
| A plain employee (no subordinates) | His own data only |

This applies to voucher lists (`created_by`), client accounts on both create screens, the linked-clients list (`GET clients`), and a client's account statement.

> ⚠️ Manually sending an `account_id` / `client_id` outside this scope on a save request is rejected with `403` / `422` — hiding it from the list is not the only guard.

**`can_expense_to_supplier`** (inside [`GET expenses/create-data`](#33-create-screen-data)) is an extra rule on top of the table above: also `true` for an employee with no one above him in the management chain (`direct_manager_id` empty), or for anyone who isn't an employee at all. The app hides the "expense to supplier" option when this is `false` — and the server still refuses it if sent anyway, though not with a clean `403`; see the note under [§3.4](#34-create).

---

## Taxes

Both the receipt and the expense voucher accept up to two taxes on the same document — the fields work identically on both:

| Field | Meaning |
|---|---|
| `tax1`, `tax2` | The **`id`** of a row from the `taxs` list (§2.3 / §3.3) — not a rate. `0` or omitted = no tax on that slot. |
| `tax1_account_id`, `tax2_account_id` | The ledger account the tax amount is posted to — normally the `vat_payable` account (on a receipt) or `vat_paid` account (on an expense) from the accounting settings. |

The amount you send in `amount` is always treated as the **base** (pre-tax) figure. The server computes the tax from the chosen `taxs` row's rate and adds a **third line to the journal entry**, posted to `tax1_account_id` — the entry as a whole still balances. The voucher record ends up with:

- `amount` — the base you sent, unchanged
- `tax1_amount` (and `tax2_amount`) — computed server-side, never trust a client-computed value
- `total_amount` — `amount + tax1_amount + tax2_amount`, what actually left/entered the treasury

**Verified example** (receipt, `amount=100`, `tax1` = the 15% "القيمة المضافة" row, `tax1_account_id` = `vat_payable`): the voucher is stored as `amount=100.00`, `tax1_amount=15.00`, `total_amount=115.00`, and the journal entry has three balanced lines — treasury debited 115, client account credited 100, `vat_payable` credited 15.

---

## File attachments

| Endpoint | Field | Accepts | Storage |
|---|---|---|---|
| `POST receipts`, `PUT receipts/{id}` | `attachments` | **One** file — jpg/jpeg/png/pdf/doc/docx/xls/xlsx, up to 5 MB | `receipts.attachments` stores the generated filename only |
| `POST expenses`, `PUT expenses/{id}` | `attachments[]` | **One or more** files — jpg/jpeg/png/pdf/doc/docx/xls/xlsx, up to 5 MB each | `expenses.attachments` stores a JSON array of relative paths |

Send these as `multipart/form-data`, not JSON — the field(s) sit alongside the other form fields in the same request. Both are verified working through this API: the file is actually written to disk and its name/path recorded on the voucher, exactly as the web screens do (there is no separate upload step or pre-signed URL — the file rides with the create/update request itself).

> ⚠️ The stored path is relative to the **project root**, not `public/` — this deployment serves the repo root directly as the docroot (see the root `CLAUDE.md`). Don't assume a `public/` prefix when building a download URL from `receipts.attachments` / `expenses.attachments`.

---

## Endpoint index

| Method | Path | Permission |
|---|---|---|
| `GET` | `summary` | `account.incomes.view` \| `account.expenses.view` |
| `GET` | `receipts` | `account.incomes.view` |
| `GET` | `receipts/{id}` | `account.incomes.view` |
| `GET` | `receipts/create-data` | `account.incomes.create` |
| `POST` | `receipts` | `account.incomes.create` |
| `PUT` | `receipts/{id}` | `account.incomes.edit` |
| `POST` | `receipts/{id}/cancel` | `account.incomes.cancel` |
| `GET` | `expenses` | `account.expenses.view` |
| `GET` | `expenses/{id}` | `account.expenses.view` |
| `GET` | `expenses/create-data` | `account.expenses.create` |
| `POST` | `expenses` | `account.expenses.create` |
| `PUT` | `expenses/{id}` | `account.expenses.edit` |
| `POST` | `expenses/{id}/cancel` | `account.expenses.cancel` |
| `DELETE` | `expenses/{id}` | `account.expenses.delete` |
| `GET` | `treasuries` | `account.treasury.view` |
| `GET` | `treasuries/{treasuryId}/transfers` | `account.treasury_transfers.view` |
| `POST` | `treasury-transfers` | `account.treasury_transfers.create` |
| `PUT` | `treasury-transfers/{id}` | `account.treasury_transfers.edit` |
| `GET` | `clients` | `account.incomes.view` |
| `GET` | `clients/{clientId}/statement` | `account.incomes.view` |

---

## 1) Finance summary

### 1.1 Summary

`GET /summary`

| Auth | Permission |
|---|---|
| Bearer token | `account.incomes.view` **OR** `account.expenses.view` |

```json
{
    "success": true,
    "data": {
        "period": "2026",
        "receipts_total": 12500.00,
        "expenses_total": 8300.50,
        "treasury_flow": {
            "in": 3096.00,
            "out": 3090.00,
            "net": 6.00
        },
        "treasuries": [
            {
                "id": 2879,
                "name": "خزينة رائد",
                "balance": 6.00
            }
        ],
        "recent_receipts": [
            {
                "id": 8816,
                "code": "REC-000123",
                "date": "2026-09-23",
                "amount": 40.00
            }
        ],
        "recent_expenses": [
            {
                "id": 5521,
                "code": "EXP-000087",
                "date": "2026-09-23",
                "amount": 25.00
            }
        ]
    }
}
```

`receipts_total` / `expenses_total` = net movement for the current accounting period (credit − debit for revenue accounts, debit − credit for expense accounts) — the same calculation as the web dashboard, not just a sum of voucher amounts.

---

## 2) Receipt vouchers (سندات القبض)

### 2.1 List

`GET /receipts` — permission: `account.incomes.view`

| Parameter | Required | Notes |
|---|---|---|
| `keywords`, `category`, `branch`, `sub_account`, `amount_from`, `amount_to`, `created_at_from`, `created_at_to` | ✖ | Filters |
| `per_page` | ✖ | `10` / `25` / `50` / `100` — default `10` |

```json
{
    "incomes": {
        "current_page": 1,
        "last_page": 3,
        "total": 27,
        "data": [
            {
                "id": 8816,
                "code": "REC-000123",
                "amount": 40.00,
                "date": "2026-09-23",
                "account": {},
                "treasury": {}
            }
        ]
    },
    "totals": {
        "totalPeriod": 12500.00,
        "totalLast30Days": 15320.00,
        "totalLast365Days": 88410.00
    },
    "account_setting": {},
    "pagination": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 10,
        "total": 27,
        "has_more_pages": true,
        "on_first_page": true,
        "next_page_url": "...",
        "prev_page_url": null
    }
}
```

> ⚠️ No `success` / `data` wrapper — see the [shape table](#implementation-principle--delegation-not-reimplementation) above.

### 2.2 Details

`GET /receipts/{id}` — permission: `account.incomes.view`

```json
{
    "success": true,
    "data": {
        "id": 8816,
        "code": "REC-000123",
        "amount": 40.00,
        "total_amount": 40.00,
        "date": "2026-09-23",
        "description": "دفعة نقدية",
        "account": {
            "id": 11015,
            "name": "حساب-عميل-تدقيق",
            "client": {
                "id": 11005,
                "trade_name": "..."
            }
        },
        "treasury": {
            "id": 2879,
            "name": "خزينة رائد"
        },
        "incomes_category": {
            "id": 1,
            "name": "إيراد عميل"
        },
        "branch": {}
    }
}
```

`403` if the voucher is outside the user's scope (`authorizeCreatorScope`) — even with a valid ID. `404` if it doesn't exist.

### 2.3 Create-screen data

`GET /receipts/create-data` — permission: `account.incomes.create`

| Parameter | Required | Notes |
|---|---|---|
| `client_id` | ✖ | Preselects that client's account, if it's within the user's scope |

```json
{
    "success": true,
    "data": {
        "accounts": [
            {
                "id": 11015,
                "name": "...",
                "client": {}
            }
        ],
        "treasuries": [
            {
                "id": 2879,
                "name": "خزينة رائد"
            }
        ],
        "incomes_categories": [
            {
                "id": 1,
                "name": "إيراد عميل"
            }
        ],
        "taxs": [],
        "otherAccounts": [],
        "branches": [],
        "MainTreasury": {
            "id": 2879
        },
        "treasury_id": 2879,
        "selectedAccountId": null,
        "nextCode": "REC-000124",
        "account_setting": {}
    }
}
```

`accounts` is **client accounts within the user's scope only** — the exact same list the web select shows: today's itinerary route if one exists, otherwise the employee's group regions; then unioned across everyone he manages if he's a team lead.

### 2.4 Create

`POST /receipts` — permission: `account.incomes.create`

| Field | Required | Notes |
|---|---|---|
| `account_id` | ✔ | Must be from the [§2.3](#23-create-screen-data) scope — an ID outside it is rejected with `403` |
| `treasury_id` | ✔ | The user must be allowed to **deposit** into it |
| `amount` | ✔ | `> 0` |
| `date` | ✔ | Within an open accounting period, otherwise `422` |
| `incomes_category_id`, `description`, `tax1`, `tax2`, `tax1_account_id`, `tax2_account_id`, `payment_method_id`, `cost_center_id`, `branch_id`, `attachments` (file) | ✖ | See [Taxes](#taxes) and [File attachments](#file-attachments) |

```json
{
    "success": true,
    "message": "تم إضافة سند القبض بنجاح!",
    "redirect": "http://.../Accounts/incomes/index"
}
```

The resulting entry is **always balanced** (verified directly): debits the treasury account for the full amount, credits the client account — plus a third line for the tax, if any. No `data` key in the response, and `redirect` is ignored.

### 2.5 Update

`PUT /receipts/{id}` — permission: `account.incomes.edit`

Same fields as [§2.4](#24-create), plus `code` (required). Same response shape.

### 2.6 Cancel

`POST /receipts/{id}/cancel` — permission: `account.incomes.cancel`

```json
{
    "success": true,
    "message": "تم إلغاء سند القبض بنجاح وإنشاء قيد عكسي"
}
```

Reverses the treasury and client balances, posts a reversing entry, and **deletes the voucher row itself** — the original web behavior, unchanged.

> ⚠️ A non-existent voucher does not return `404`; it returns `422` with the message `"حدث خطأ أثناء إلغاء سند القبض..."`. The original web function catches every exception, including "not found", in one generic `catch (\Exception)`. This is documented, not fixed — a literal delegation of its behavior.

---

## 3) Expense vouchers (سندات الصرف)

Same structure as [§2](#2-receipt-vouchers-سندات-القبض), with expense-voucher differences noted below.

### 3.1 List

`GET /expenses` — permission: `account.expenses.view`

Extra filters beyond [§2.1](#21-list): `cost_center_id`, `taxable` (`0`/`1`), `added_by`. Response shape as in §2.1 but keyed `expenses`.

### 3.2 Details

`GET /expenses/{id}` — permission: `account.expenses.view`

Loads `expenses_category`, `branch`, `account.client`, `supplier`.

### 3.3 Create-screen data

`GET /expenses/create-data` — permission: `account.expenses.create`

Built from `ExpensesController::create()`'s own view data (`$view->getData()`) — the same source the web screen renders from, not a hand-picked subset. This is deliberate: an earlier version of this endpoint only returned six of the fields below and silently dropped `taxs` entirely, so the app could not offer a tax on an expense voucher at all. Fixed 2026-09-23.

```json
{
    "success": true,
    "data": {
        "expenses_categories": [
            {
                "id": 1,
                "name": "..."
            }
        ],
        "suppliers": [
            {
                "id": 1,
                "trade_name": "..."
            }
        ],
        "treasuries": [
            {
                "id": 2879,
                "name": "خزينة رائد"
            }
        ],
        "accounts": [
            {
                "id": 11015,
                "name": "...",
                "client": {}
            }
        ],
        "expense_leaf_accounts": [
            {
                "id": 42,
                "name": "إيجار",
                "code": "5101"
            }
        ],
        "taxs": [
            {
                "id": 1,
                "name": "صفرية",
                "tax": 0,
                "type": "excluded"
            },
            {
                "id": 2,
                "name": "القيمة المضافة",
                "tax": 15,
                "type": "excluded"
            }
        ],
        "code": "EXP-000124",
        "MainTreasury": {
            "id": 2879
        },
        "treasury_id": 2879,
        "account_setting": {},
        "branches": [],
        "preselect_type": null,
        "preselect_account_id": null,
        "preselect_category_id": null,
        "client_default_category_id": null,
        "can_expense_to_supplier": true
    }
}
```

`treasuries` here are **withdrawal** treasuries (`withdrawableTreasuryAccounts`) — the opposite of [§2.3](#23-create-screen-data), which lists **deposit** treasuries. The two lists may differ for a user allowed one but not the other.

`taxs` is the tax settings catalog (`tax_sittings`) — use a row's `id` as the `tax1` / `tax2` value on [create](#34-create); see the [Taxes](#taxes) section for how the amount is computed.

### 3.4 Create

`POST /expenses` — permission: `account.expenses.create`

| Field | Required | Notes |
|---|---|---|
| `expense_type` | ✔ | `general` \| `client` \| `supplier` |
| `amount`, `date`, `treasury_id` | ✔ | `treasury_id` must allow **withdrawal** |
| `expenses_category_id` | Required unless `client` | |
| `account_id` | Required for `general` / `client` | From §3.3's `accounts` (for `client`) or `expense_leaf_accounts` (for `general`) |
| `supplier_id` | Required for `supplier` | See the note below if `can_expense_to_supplier` isn't `true` |
| `tax1`, `tax2`, `tax1_account_id`, `tax2_account_id` | ✖ | See [Taxes](#taxes) |
| `description`, `cost_center_id`, `branch_id`, `payment_method_id`, `attachments[]` | ✖ | `attachments[]` — see [File attachments](#file-attachments) |

```json
{
    "success": true,
    "message": "تم إضافة سند صرف بنجاح!",
    "redirect": "http://.../Accounts/expenses/index"
}
```

All three types verified end-to-end, including the accounting side: `general` posts to the `account_id` you send (a leaf expense account); `client` posts to that client's ledger account; `supplier` posts to the supplier's ledger account, **auto-creating one under the suppliers parent account the first time that supplier is used** — this auto-creation path had a real bug (see the developer note below) that made the very first expense for any new supplier fail with a raw SQL error; fixed 2026-09-23.

> ⚠️ Sending `expense_type=supplier` without `can_expense_to_supplier` being `true` for you does **not** come back as a clean `403`. The server silently downgrades the type to `general`, which then fails its own validation (`account_id` becomes required) — so you'll see `422` with an `account_id` error, not an error naming the real cause. No expense is created either way.

### 3.5 Update

`PUT /expenses/{id}` — permission: `account.expenses.edit`

Same fields as [§3.4](#34-create).

### 3.6 Cancel

`POST /expenses/{id}/cancel` — permission: `account.expenses.cancel`

```json
{
    "success": true,
    "message": "تم حذف سند الصرف بنجاح وإنشاء قيد عكسي!"
}
```

Unlike the receipt voucher, this web function is **already JSON-aware** — `403` / `422` come back in their real shape, with nothing swallowed into a generic message: a voucher linked to payroll is rejected with its own message, and so is one already reversed.

### 3.7 Delete

`DELETE /expenses/{id}` — permission: `account.expenses.delete`

**Literally identical** to [§3.6](#36-cancel) — `delete()` on the web controller *is* `cancel()`, a one-line `return $this->cancel($id);`.

---

## 4) Treasury and transfers

### 4.1 List treasuries

`GET /treasuries` — permission: `account.treasury.view`

| Parameter | Required | Notes |
|---|---|---|
| `direction` | ✖ | `deposit` (default, for a receipt voucher) or `withdraw` (for an expense voucher) |

```json
{
    "success": true,
    "data": [
        {
            "id": 2879,
            "name": "خزينة رائد",
            "code": "2879",
            "balance": 6.00
        }
    ]
}
```

### 4.2 Treasury movement (transfers)

`GET /treasuries/{treasuryId}/transfers` — permission: `account.treasury_transfers.view`

```json
{
    "transfers": [
        {
            "id": 991,
            "reference_number": "TRF-20260923-...",
            "date": "2026-09-23",
            "from_account": {},
            "to_account": {},
            "amount": 10.00
        }
    ]
}
```

> ⚠️ No `success` / `data` wrapper — a direct delegation to `TreasuryController::getTransfersData()`.

`403` if the treasury is outside the user's scope — a check **added here specifically**; the original web function doesn't check it at all.

### 4.3 Create a transfer

`POST /treasury-transfers` — permission: `account.treasury_transfers.create`

| Field | Required | Notes |
|---|---|---|
| `from_treasury_id`, `to_treasury_id` | ✔ | Must differ; require **withdrawal** from the first and **deposit** into the second |
| `amount` | ✔ | Rejected if it exceeds `from_treasury_id`'s balance |
| `transfer_date` | ✔ | Within an open accounting period |
| `from_currency`, `to_currency` | ✔ | Must match — a transfer between two different currencies isn't currently supported |
| `notes`, `attachments` (file) | ✖ | |

```json
{
    "success": true,
    "message": "تم التحويل وإنشاء القيد المحاسبي بنجاح!"
}
```

Always a balanced entry: credits `from_treasury_id` for the full amount, debits `to_treasury_id`.

### 4.4 Update a transfer

`PUT /treasury-transfers/{id}` — permission: `account.treasury_transfers.edit`

Same fields as [§4.3](#43-create-a-transfer).

---

## 5) Clients

### 5.1 Linked clients list

`GET /clients` — permission: `account.incomes.view`

| Parameter | Required | Notes |
|---|---|---|
| `per_page` | ✖ | Default `20` |

```json
{
    "success": true,
    "data": {
        "current_page": 1,
        "last_page": 1,
        "total": 1,
        "data": [
            {
                "id": 11005,
                "trade_name": "...",
                "first_name": "...",
                "phone": "...",
                "mobile": "...",
                "status_id": null
            }
        ]
    }
}
```

The exact same scope as the client accounts in [§2.3](#23-create-screen-data) — one source (`IncomesController::eligibleReceiptClientIds()`), no parallel algorithm.

### 5.2 Client account statement

`GET /clients/{clientId}/statement` — permission: `account.incomes.view`

```json
{
    "success": true,
    "data": {
        "client": {
            "id": 11005,
            "trade_name": "..."
        },
        "account_id": 11015,
        "opening_balance": 0.00,
        "closing_balance": 40.00,
        "entries": [
            {
                "date": "2026-09-23",
                "reference_number": "REC-000123",
                "description": "دفعة نقدية",
                "debit": 0.00,
                "credit": 40.00,
                "balance": -40.00
            }
        ]
    }
}
```

`403` if the client is outside the user's scope — even with a valid ID that genuinely exists. A client with no ledger account yet returns `entries: []` and `account_id: null`, not an error.

---

## Developer notes

**Module.** `Modules/Integration/Http/Controllers/Api/FinanceController.php` — no accounting logic lives in it; every write calls `Modules\Account\Http\Controllers\Finance\{Incomes,Expenses,Treasury}Controller` directly via `app(...)`.

**A second real bug found and fixed (2026-09-23) — a crash, not just a gap.** Both `ExpensesController::store()` and `::update()` had an identical broken block for `expense_type=supplier`: after auto-creating a ledger account for a supplier that didn't have one yet, they tried to write `$supplier->account_id = $newAccountId; $supplier->save();`. There is no `account_id` column on `suppliers` — the real, working link is the other direction (`accounts.supplier_id`, already set correctly on the new account a few lines earlier), and that stray write threw a raw `SQLSTATE[42S22]: Column not found` on every single first-time use of any supplier in an expense voucher, on the web screen as much as here. Fixed by deleting the two dead/broken lines in both methods — nothing needed saving on the supplier side, `$supplier->account` (a `hasOne` on `accounts.supplier_id`) already finds the newly-created account correctly on the very next use.

**All three expense types verified end-to-end after the fix**, mirroring exactly how the receipt voucher's single type works: `general` and `client` both post to an `account_id` you choose (from different scoped lists), `supplier` resolves a supplier's ledger account (creating it on first use, now without crashing) — every case checked directly against `journal_entry_details` for a balanced entry touching the right account.

**Why the response shape is inconsistent.** The delegated controllers were originally written for web screens, and they don't all behave the same way:

- Some check `wantsJson()` and return clean JSON — `store` / `update` on both `IncomesController` and `ExpensesController`, plus `cancel` / `delete` on `ExpensesController`.
- Others always return a redirect (`redirect()` / `back()`), regardless of request type — `IncomesController::cancel()`, `TreasuryController::transferTreasuryStore()` / `transferTreasuryUpdate()`.
- Some even swallow every exception in one generic `catch (\Exception)`, turning even "record not found" into a generic error message instead of an exception that Laravel would auto-translate to `404`.

The fix is a single adapter, `FinanceController::delegateWebAction()`, that inspects the call's result:

1. If it's already a `JsonResponse`, it's passed through as-is.
2. Otherwise, it reads whatever the function left in the request's session — `session('errors')` from `withErrors()`, or `session('error')` from `with('error', ...)` — and builds JSON from that.

This works even though the `api` middleware group never runs `StartSession`, because `redirect()` and `session()` both resolve to the same single session-store object in the container within one request.

**Permission lives on the route, not the controller.** Calling another controller's method directly via `app(...)->method()` **bypasses** that controller's own middleware — `$this->middleware(...)` in its constructor only fires through a real Router dispatch. So every permission here is declared explicitly on the mobile route in `routes/api.php`; it is never assumed to be enforced automatically by the delegated controller.

**Methods widened from `private` to `public`** (logic unchanged), to reuse without duplicating:

- `ExpensesController::getClientAccountsForUser()`
- `ExpensesController::getExpenseLeafAccounts()`
- `ExpensesController::canExpenseToSupplier()`
- `IncomesController::eligibleReceiptClientIds()`

**An extra check not present on the web.** `GET /treasuries/{id}/transfers` checks the treasury's scope (`authorizeTreasuryAccess`) before delegating — `TreasuryController::getTransfersData()` itself checks nothing, so any ID passed to it goes straight through.

**Attachment storage path.** `UploadedFile::move()` is called with a bare relative path (`assets/uploads/incomes`, `assets/uploads/expenses`) — no `public_path()`. It resolves against the project root, not `public/`, because this deployment serves the repo root directly as the docroot (see the root `CLAUDE.md`). Verified by uploading a real file through this API and confirming it lands at `{project root}/assets/uploads/{incomes,expenses}/...`. Building a download URL from a stored `attachments` value must account for this — a `public/`-prefixed URL will 404.

**A real bug found and fixed while auditing this doc (2026-09-23).** `GET expenses/create-data` originally hand-picked six fields and silently omitted `taxs` — the expense-voucher create screen on mobile had no way to offer a tax at all, and also lacked `code`, `MainTreasury`, `treasury_id`, `account_setting`, `branches`, and the `client`-type preselect fields that the receipt equivalent (`formData()`) already returns. Fixed by rebuilding the endpoint from `ExpensesController::create()`'s own `$view->getData()` instead of reassembling fields by hand — same principle as the rest of this API: read from the single source, don't re-derive it.

**Real end-to-end test (2026-09-23, extended).** Run through the full HTTP kernel (routing + Sanctum + middleware) inside rolled-back transactions — 77 assertions across three passes, all passed:

- Permission enforcement on all twenty endpoints.
- Dropdown lists returning real, correctly-scoped, non-empty data — including `taxs` on both create-data endpoints after the fix above.
- A full create → view → cancel cycle for the receipt voucher, the expense voucher, and a treasury transfer — each checked directly against the database to confirm the resulting journal entry is **balanced** (`SUM(debit) = SUM(credit)`) and that the correct accounts moved.
- A receipt voucher and an expense voucher each created **with a tax applied**, confirming `tax1_amount` / `total_amount` are computed server-side and the tax line posts to the right account.
- A single-file upload on a receipt and a two-file upload on an expense, confirming the files are genuinely written to disk and their path recorded on the voucher.
- All three expense types (`general`, `client`, `supplier`) created individually and checked against `journal_entry_details`, including a supplier used for the very first time (no ledger account yet) — the scenario that surfaced the crash fixed above.
- `GET treasuries` with `?direction=withdraw`, `?direction=deposit`, and no parameter (defaults to deposit), confirming the two lists are independently correct.

**Migrations.** None new for this feature — it reuses the existing `receipts` / `expenses` / `journal_entries` tables.
