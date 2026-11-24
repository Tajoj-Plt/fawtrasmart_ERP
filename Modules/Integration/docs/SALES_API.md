# Manual for fawtrasmart Api doc — Sales (فواتير، مرتجعات، إشعارات دائنة، تبديل) :

-Api_url_based : `{base_url}/api/integration/v1/`

-Headers_required : `Accept: application/json`

-Auth_method (all endpoints below) : `Bearer token` (see auth/login in API_DOCUMENTATION.md)

-Notes : discounts post two fields (amount|percentage) same as the web screens. The general document discount reduces the tax base (applied before VAT, allocated across lines). Stock and accounting journals post exactly like the web — invoices deduct stock and post a sales + COGS entry; returns/credit notes restock and reverse both; exchanges move stock between two different warehouses with two entries. No edit/delete on invoices, returns or credit notes (ZATCA e-invoicing rule) — only create/show/print. Product exchange has no linked invoice and no client balance effect. Every route is permission-gated at the route level (`permission.role:sales.*` in `routes/api.php`) — same catalog the web screen's constructor enforces — not re-checked inside the controller.

---

| process name | List invoices |
|---|---|
| API_End point | `GET invoices` |
| description | paginated, filtered list — same query and scope as the web index screen (visibility ladder + active branch) |
| Auth_method | Bearer token |
| req_parameters | client_id?, invoice_number? (code or id), branch?, payment_status? (1\|2\|3), item? (product name in the lines), total_from?/total_to?, from_date?/to_date? (default: current accounting period; closed periods always excluded), added_by_employee?, sales_person_user?, per_page? (10\|25\|50\|100), page? |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : invoice[]`, `meta : { current_page, last_page, per_page, total }` |

---

| process name | Create invoice |
|---|---|
| API_End point | `POST invoices` |
| description | create a sales invoice — deducts stock, posts sales + COGS journal entries, optional cash receipt if paid |
| Auth_method | Bearer token |
| req_parameters | 1- client_id 2- invoice_date? 3- store_house_id? 4- treasury_id? 5- terms? (payment days) 6- notes? 7- advance_payment? 8- shipping_cost? 9- shipping_tax_id? 10- discount_amount? 11- discount_type? (amount\|percentage) 12- current_latitude/current_longitude (GPS check unless bypass permission) 13- items[] { product_id, product_unit_id?, store_house_id?, quantity, unit_price, discount?, discount_type?, tax_1_id?, tax_2_id? } |
| Status_Code | 201 - success , 402 - plan limit , 403 - no permission/scope/debt-without-permission , 422 - validation / below cost / over max discount / over credit limit |
| res_body | `data : invoice{ id, code, client, totals, items[], can_return, can_credit_note, print_url }` |

---

| process name | Get invoice |
|---|---|
| API_End point | `GET invoices/{id}` |
| description | full invoice detail, including payments |
| Auth_method | Bearer token |
| req_parameters | none |
| Status_Code | 200 - success , 403 - out of scope , 404 - not found |
| res_body | `data : invoice{}` |

---

| process name | Print invoice |
|---|---|
| API_End point | `GET invoices/{id}/print` |
| description | full HTML print page (designed template if configured, else the built-in one) — not a PDF file, open in a WebView |
| Auth_method | Bearer token |
| req_parameters | template? (id, or `builtin` to force the system template) |
| Status_Code | 200 - success (text/html) , 403 - out of scope |
| res_body | raw HTML |

---

| process name | List returns |
|---|---|
| API_End point | `GET returns` |
| description | paginated, filtered list — same query and scope as the web index screen |
| Auth_method | Bearer token |
| req_parameters | client_id?, invoice_number? (return's own code or id), reference_number? (original invoice's code or id), branch?, total_from?/total_to?, from_date?/to_date? (default: current accounting period), added_by_employee?, sales_person_user? (matches the return's own employee, or its original invoice's), per_page?, page? |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : return[]`, `meta : { current_page, last_page, per_page, total }` |

---

| process name | Create sales return |
|---|---|
| API_End point | `POST returns` |
| description | return goods against an original invoice — one return per invoice, quantity/price capped to what that invoice actually sold, restocks to the original line's warehouse, optional cash refund |
| Auth_method | Bearer token |
| req_parameters | 1- invoice_id (original invoice) 2- items[] { product_id, product_unit_id?, quantity, unit_price } 3- treasury_id? 4- return_amount_option? (`return` to refund cash, default) 5- discount_amount? 6- discount_type? 7- shipping_cost? 8- shipping_tax_id? |
| Status_Code | 201 - success , 402 - plan limit , 403 - no permission/scope , 422 - invoice already returned/credited / quantity or price exceeds what was sold |
| res_body | `data : return{ id, code, original_invoice_id, totals, items[], print_url, refunded_cash }` |

---

| process name | Get return |
|---|---|
| API_End point | `GET returns/{id}` |
| description | return detail |
| Auth_method | Bearer token |
| req_parameters | none |
| Status_Code | 200 - success , 403 - out of scope , 404 - not found |
| res_body | `data : return{}` |

---

| process name | Print return |
|---|---|
| API_End point | `GET returns/{id}/print` |
| description | same as invoice print — full HTML page |
| Auth_method | Bearer token |
| req_parameters | template? |
| Status_Code | 200 - success , 403 - out of scope |
| res_body | raw HTML |

---

| process name | List credit notes |
|---|---|
| API_End point | `GET credit-notes` |
| description | paginated, filtered list — same query, scope and employee-group axis as the web index screen |
| Auth_method | Bearer token |
| req_parameters | client_id?, credit_number?, invoice_id?, status? (5 matches both 0 and 5 — «cancelled», same quirk as the web screen), branch?, total_from?/total_to?, from_date?/to_date? (default: current accounting period, matched against `credit_date` falling back to `created_at` for legacy rows), created_by?, sales_person_user?, item_search?, per_page?, page? |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : note[]`, `meta : { current_page, last_page, per_page, total }` |

---

| process name | Credit note — settlement preview |
|---|---|
| API_End point | `GET credit-notes/settlement-preview` |
| description | shows how an amount would be allocated (referenced invoice → previous balance → oldest open invoices) without writing anything — call before store to show the app the breakdown |
| Auth_method | Bearer token |
| req_parameters | 1- client_id 2- amount 3- invoice_id? |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : { previous_balance, previous_pay, invoices[], unallocated }` |

---

| process name | Create credit note |
|---|---|
| API_End point | `POST credit-notes` |
| description | issue a credit note — restocks goods, posts the reversing journal, then auto-settles the client's debt (referenced invoice first, then previous balance, then oldest open invoices) |
| Auth_method | Bearer token |
| req_parameters | 1- client_id 2- invoice_id? (optional — a credit note can stand alone) 3- credit_date? 4- store_house_id? (required if no invoice_id) 5- treasury_id? 6- notes? 7- discount_amount? 8- discount_type? 9- shipping_cost? 10- shipping_tax_id? 11- items[] { product_id, product_unit_id?, quantity, unit_price, tax_1_id?, tax_2_id? } |
| Status_Code | 201 - success , 402 - plan limit , 403 - no permission/scope , 422 - invoice already returned/credited / limits exceeded |
| res_body | `data : note{ id, credit_number, invoice_id, totals, items[], print_url, settlement }` |

---

| process name | Get credit note |
|---|---|
| API_End point | `GET credit-notes/{id}` |
| description | credit note detail, including what it settled (`settlements`) |
| Auth_method | Bearer token |
| req_parameters | none |
| Status_Code | 200 - success , 403 - out of scope , 404 - not found |
| res_body | `data : note{ ..., settlements[] }` |

---

| process name | Print credit note |
|---|---|
| API_End point | `GET credit-notes/{id}/print` |
| description | designed template (HTML) if configured, else a real PDF file (unlike invoice/return's HTML-only fallback) |
| Auth_method | Bearer token |
| req_parameters | template? |
| Status_Code | 200 - success , 403 - out of scope |
| res_body | HTML or `application/pdf` |

---

| process name | List product exchanges |
|---|---|
| API_End point | `GET exchanges` |
| description | paginated, filtered list — same query and scope as the web index screen |
| Auth_method | Bearer token |
| req_parameters | client_id?, code?, from_date?, to_date?, branch? (the exchange's own frozen branch), per_page? (10\|25\|50\|100), page? |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : exchange[]`, `meta : { current_page, last_page, per_page, total }` |

---

| process name | Create product exchange |
|---|---|
| API_End point | `POST exchanges` |
| description | swap damaged goods for good ones — moves stock between two different warehouses (out + in), two journal entries, no client balance effect, no invoice link |
| Auth_method | Bearer token |
| req_parameters | 1- client_id 2- storehouse_id (out) 3- return_storehouse_id (in — must differ from storehouse_id) 4- exchange_date 5- image (file, required, max 5MB) 6- notes? 7- items[] { product_id, quantity, reason (id from the exchange-reasons list), reason_notes? } |
| Status_Code | 201 - success , 402 - plan limit , 403 - no permission , 422 - daily limit exceeded / insufficient stock |
| res_body | `data : exchange{ id, code, client, total_value, image_url, items[], print_url }` |

---

| process name | Get product exchange |
|---|---|
| API_End point | `GET exchanges/{id}` |
| description | exchange detail |
| Auth_method | Bearer token |
| req_parameters | none |
| Status_Code | 200 - success , 403 - out of scope , 404 - not found |
| res_body | `data : exchange{}` |

---

| process name | Print product exchange |
|---|---|
| API_End point | `GET exchanges/{id}/print` |
| description | the built-in HTML view — no designed-template system for this document type on the web either |
| Auth_method | Bearer token |
| req_parameters | none |
| Status_Code | 200 - success , 403 - out of scope |
| res_body | raw HTML |
