# Manual for fawtrasmart Api doc - Finance :

-Api_url_based : `{base_url}/api/integration/v1/finance/`

-Headers_required : `Accept: application/json`

---

| process name | Finance summary |
|---|---|
| API_End point | `GET summary` |
| description | receipts/expenses totals, treasuries balances, last receipts/expenses |
| Auth_method | Bearer token |
| permission | account.incomes.view OR account.expenses.view |
| req_parameters | 1- period_id (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : { period, receipts_total, expenses_total, treasury_flow{in,out,net}, treasuries[{id,name,balance}], recent_receipts[{id,code,date,amount}], recent_expenses[{id,code,date,amount}] }` |

---

| process name | List receipt vouchers |
|---|---|
| API_End point | `GET receipts` |
| description | list receipt vouchers |
| Auth_method | Bearer token |
| permission | account.incomes.view |
| req_parameters | 1- keywords 2- sub_account 3- branch 4- cost_center_id 5- taxable 6- added_by 7- period_id 8- per_page 9- page (all optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `{ incomes: {data[], current_page, last_page, total}, totals{totalPeriod, totalLast30Days, totalLast365Days}, account_setting, pagination{} }` |

---

| process name | Receipt voucher create-data |
|---|---|
| API_End point | `GET receipts/create-data` |
| description | data for create receipt screen |
| Auth_method | Bearer token |
| permission | account.incomes.create |
| req_parameters | 1- client_id (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : { accounts[], treasuries[], incomes_categories[], taxs[], MainTreasury, treasury_id, selectedAccountId, nextCode, otherAccounts[], account_setting, branches[] }` |

---

| process name | Receipt voucher details |
|---|---|
| API_End point | `GET receipts/{id}` |
| description | receipt voucher details |
| Auth_method | Bearer token |
| permission | account.incomes.view |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 404 - not found |
| res_body | `data : { id, code, amount, total_amount, date, account{client{}}, treasury{}, incomes_category{}, branch{} }` |

---

| process name | Create receipt voucher |
|---|---|
| API_End point | `POST receipts` |
| description | create receipt voucher |
| Auth_method | Bearer token |
| permission | account.incomes.create |
| req_parameters | 1- account_id 2- treasury_id 3- amount 4- date 5- incomes_category_id (optional) 6- description (optional) 7- seller (optional) 8- tax1 (optional) 9- tax2 (optional) 10- tax1_account_id (optional) 11- tax2_account_id (optional) 12- payment_method_id (optional) 13- cost_center_id (optional) 14- branch_id (optional) 15- attachments (file, optional) |
| Status_Code | 200 - success , 403 - outside scope , 422 - validation error , 402 - plan limit |
| res_body | `{ success, message, redirect }` |

---

| process name | Update receipt voucher |
|---|---|
| API_End point | `PUT receipts/{id}` |
| description | update receipt voucher |
| Auth_method | Bearer token |
| permission | account.incomes.edit |
| req_parameters | 1- code 2- account_id 3- treasury_id 4- amount 5- date 6- incomes_category_id 7- description (optional) 8- tax1 (optional) 9- tax2 (optional) 10- tax1_account_id (optional) 11- tax2_account_id (optional) 12- payment_method_id (optional) 13- cost_center_id (optional) 14- branch_id (optional) |
| Status_Code | 200 - success , 403 - outside scope , 422 - validation error |
| res_body | `{ success, message, redirect }` |

---

| process name | Cancel receipt voucher |
|---|---|
| API_End point | `POST receipts/{id}/cancel` |
| description | cancel receipt voucher |
| Auth_method | Bearer token |
| permission | account.incomes.cancel |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 422 - error |
| res_body | `{ success, message }` |

---

| process name | List expense vouchers |
|---|---|
| API_End point | `GET expenses` |
| description | list expense vouchers |
| Auth_method | Bearer token |
| permission | account.expenses.view |
| req_parameters | 1- keywords 2- description 3- category 4- sub_account 5- branch 6- cost_center_id 7- taxable 8- amount_from 9- amount_to 10- created_at_from 11- created_at_to 12- added_by 13- period_id 14- per_page 15- page (all optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `{ expenses: {data[], current_page, last_page, total}, totals{}, account_setting, pagination{} }` |

---

| process name | Expense voucher create-data |
|---|---|
| API_End point | `GET expenses/create-data` |
| description | data for create expense screen |
| Auth_method | Bearer token |
| permission | account.expenses.create |
| req_parameters | 1- client_id (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : { expenses_categories[], suppliers[], treasuries[], accounts[], expense_leaf_accounts[], taxs[], code, MainTreasury, treasury_id, account_setting, branches[], preselect_type, preselect_account_id, preselect_category_id, client_default_category_id, can_expense_to_supplier }` |

---

| process name | Expense voucher details |
|---|---|
| API_End point | `GET expenses/{id}` |
| description | expense voucher details |
| Auth_method | Bearer token |
| permission | account.expenses.view |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 404 - not found |
| res_body | `data : { id, code, amount, total_amount, date, expense_type, expenses_category{}, account{client{}}, supplier{}, branch{} }` |

---

| process name | Create expense voucher - general |
|---|---|
| API_End point | `POST expenses` |
| description | create general expense voucher |
| Auth_method | Bearer token |
| permission | account.expenses.create |
| req_parameters | 1- expense_type = general 2- amount 3- date 4- treasury_id 5- expenses_category_id 6- account_id 7- description (optional) 8- seller (optional) 9- tax1 (optional) 10- tax2 (optional) 11- tax1_account_id (optional) 12- tax2_account_id (optional) 13- cost_center_id (optional) 14- branch_id (optional) 15- payment_method_id (optional) 16- attachments[] (files, optional) |
| Status_Code | 200 - success , 403 - outside scope , 422 - validation error , 402 - plan limit |
| res_body | `{ success, message, redirect }` |

---

| process name | Create expense voucher - client |
|---|---|
| API_End point | `POST expenses` |
| description | create client expense voucher |
| Auth_method | Bearer token |
| permission | account.expenses.create |
| req_parameters | 1- expense_type = client 2- amount 3- date 4- treasury_id 5- account_id 6- expenses_category_id (optional) 7- description (optional) 8- tax1 (optional) 9- tax2 (optional) 10- tax1_account_id (optional) 11- tax2_account_id (optional) 12- cost_center_id (optional) 13- branch_id (optional) 14- payment_method_id (optional) 15- attachments[] (files, optional) |
| Status_Code | 200 - success , 403 - outside scope , 422 - validation error , 402 - plan limit |
| res_body | `{ success, message, redirect }` |

---

| process name | Create expense voucher - supplier |
|---|---|
| API_End point | `POST expenses` |
| description | create supplier expense voucher |
| Auth_method | Bearer token |
| permission | account.expenses.create |
| req_parameters | 1- expense_type = supplier 2- amount 3- date 4- treasury_id 5- expenses_category_id 6- supplier_id 7- description (optional) 8- tax1 (optional) 9- tax2 (optional) 10- tax1_account_id (optional) 11- tax2_account_id (optional) 12- cost_center_id (optional) 13- branch_id (optional) 14- payment_method_id (optional) 15- attachments[] (files, optional) |
| Status_Code | 200 - success , 403 - outside scope , 422 - validation error , 402 - plan limit |
| res_body | `{ success, message, redirect }` |

---

| process name | Update expense voucher |
|---|---|
| API_End point | `PUT expenses/{id}` |
| description | update expense voucher |
| Auth_method | Bearer token |
| permission | account.expenses.edit |
| req_parameters | same as create (general / client / supplier) |
| Status_Code | 200 - success , 403 - outside scope , 422 - validation error |
| res_body | `{ success, message, redirect }` |

---

| process name | Cancel expense voucher |
|---|---|
| API_End point | `POST expenses/{id}/cancel` |
| description | cancel expense voucher |
| Auth_method | Bearer token |
| permission | account.expenses.cancel |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 422 - error |
| res_body | `{ success, message }` |

---

| process name | Delete expense voucher |
|---|---|
| API_End point | `DELETE expenses/{id}` |
| description | delete expense voucher |
| Auth_method | Bearer token |
| permission | account.expenses.delete |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 422 - error |
| res_body | `{ success, message }` |

---

| process name | List treasuries |
|---|---|
| API_End point | `GET treasuries` |
| description | list treasuries |
| Auth_method | Bearer token |
| permission | account.treasury.view |
| req_parameters | 1- direction (deposit / withdraw, optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : [ { id, name, code, balance } ]` |

---

| process name | Treasury transfers |
|---|---|
| API_End point | `GET treasuries/{treasuryId}/transfers` |
| description | list treasury transfers |
| Auth_method | Bearer token |
| permission | account.treasury_transfers.view |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope |
| res_body | `{ transfers: [ { id, reference_number, date, from_account{}, to_account{}, amount } ] }` |

---

| process name | Create treasury transfer |
|---|---|
| API_End point | `POST treasury-transfers` |
| description | transfer between treasuries |
| Auth_method | Bearer token |
| permission | account.treasury_transfers.create |
| req_parameters | 1- from_treasury_id 2- to_treasury_id 3- amount 4- transfer_date 5- from_currency 6- to_currency 7- notes (optional) 8- attachments (file, optional) |
| Status_Code | 200 - success , 403 - not allowed , 422 - validation error |
| res_body | `{ success, message }` |

---

| process name | Update treasury transfer |
|---|---|
| API_End point | `PUT treasury-transfers/{id}` |
| description | update treasury transfer |
| Auth_method | Bearer token |
| permission | account.treasury_transfers.edit |
| req_parameters | 1- from_treasury_id 2- to_treasury_id 3- amount |
| Status_Code | 200 - success , 403 - not allowed , 422 - validation error |
| res_body | `{ success, message }` |

---

| process name | Linked clients |
|---|---|
| API_End point | `GET clients` |
| description | list linked clients |
| Auth_method | Bearer token |
| permission | account.incomes.view |
| req_parameters | 1- per_page (optional) 2- page (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : { data: [ { id, trade_name, first_name, last_name, phone, mobile, status_id } ], current_page, last_page, total }` |

---

| process name | Client account statement |
|---|---|
| API_End point | `GET clients/{clientId}/statement` |
| description | client account statement |
| Auth_method | Bearer token |
| permission | account.incomes.view |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 404 - not found |
| res_body | `data : { client{}, account_id, opening_balance, closing_balance, entries[ { date, reference_number, description, debit, credit, balance } ] }` |
