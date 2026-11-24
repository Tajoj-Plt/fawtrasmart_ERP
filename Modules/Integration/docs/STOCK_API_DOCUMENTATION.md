# Manual for fawtrasmart Api doc - Stock :

-Api_url_based : `{base_url}/api/integration/v1/stock/`

-Headers_required : `Accept: application/json`

---

| process name | List warehouses |
|---|---|
| API_End point | `GET warehouses` |
| description | list warehouses |
| Auth_method | Bearer token |
| permission | stock.warehouses.view |
| req_parameters | 1- keywords (optional) 2- status (optional) 3- per_page (optional) 4- page (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : [ { id, name, status, shipping_address, branch, is_default, products_count, total_quantity, stock_value, responsible_employees[], can_view, can_create_invoices, can_edit_stock, can_view_details, can_view_movements, can_view_statement } ] , pagination{}` |

---

| process name | Warehouse details |
|---|---|
| API_End point | `GET warehouses/{id}` |
| description | warehouse details with quantities |
| Auth_method | Bearer token |
| permission | stock.warehouses.view + stock.warehouses.show |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 404 - not found |
| res_body | `data : { warehouse{}, items[ { id, name, barcode, current_quantity, incoming_manual, incoming_opening, incoming_transfer, incoming_total, outgoing_manual, outgoing_transfer, sold, sales_returns, withdrawn_total, unit_cost, stock_value } ], totals{}, total_stock_value, account_balance, value_difference, logs[ { date, description, user } ] }` |

---

| process name | Warehouse movements |
|---|---|
| API_End point | `GET warehouses/{id}/movements` |
| description | warehouse movements |
| Auth_method | Bearer token |
| permission | stock.reports.summary_movement |
| req_parameters | 1- keywords (optional) 2- category (optional) 3- operation_type (incoming / outgoing / transfer, optional) |
| Status_Code | 200 - success , 403 - outside scope |
| res_body | `{ success, data[ { id, name, incoming_manual, incoming_opening, incoming_transfer, outgoing_manual, outgoing_transfer, incoming_total, outgoing_total, movement_total, sold_quantity, sales_return_quantity } ], storehouse{}, categories[] }` |

---

| process name | List permits |
|---|---|
| API_End point | `GET permits` |
| description | list warehouse permits |
| Auth_method | Bearer token |
| permission | stock.permits.view |
| req_parameters | 1- status (pending / approved / rejected, optional) 2- permission_type (1-4, optional) 3- keywords (optional) 4- store_house (optional) 5- product (optional) 6- created_by (optional) 7- from_date (optional) 8- to_date (optional) 9- period_id (optional) 10- currency (optional) 11- per_page (optional) 12- page (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : [ { id, number, type, type_label, status, date, warehouse, from_warehouse, to_warehouse, grand_total, currency, details, created_by, can_edit, can_delete, can_approve, can_reject, can_cancel_approval } ] , pagination{}` |

---

| process name | Permit create-data |
|---|---|
| API_End point | `GET permits/create-data` |
| description | data for create permit screen |
| Auth_method | Bearer token |
| permission | stock.permits.create_addition / create_disbursement / create_transfer / create_opening (by type) |
| req_parameters | 1- type (1 addition / 2 disbursement / 3 transfer / 4 opening) |
| Status_Code | 200 - success , 403 - no permission , 422 - unknown type |
| res_body | `data : { type, serial_number, warehouses[ { id, name } ], products[ { id, name, barcode, purchase_price, sale_price } ] }` |

---

| process name | Product stock |
|---|---|
| API_End point | `GET permits/product-stock/{warehouseId}/{productId}` |
| description | product quantity in warehouse |
| Auth_method | Bearer token |
| permission | stock.permits.view |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 404 - product not found |
| res_body | `{ stock, formatted }` |

---

| process name | Permit details |
|---|---|
| API_End point | `GET permits/{id}` |
| description | permit details |
| Auth_method | Bearer token |
| permission | stock.permits.show |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 404 - not found |
| res_body | `data : { id, number, type, type_label, status, date, warehouse, from_warehouse, to_warehouse, grand_total, currency, details, created_by, can_edit, can_delete, can_approve, can_reject, can_cancel_approval, items[ { id, product_id, product_name, quantity, unit_price, total } ], attachments, rejection_reason, logs[] }` |

---

| process name | Create permit - addition |
|---|---|
| API_End point | `POST permits` |
| description | create addition permit |
| Auth_method | Bearer token |
| permission | stock.permits.create_addition |
| req_parameters | 1- permission_type = 1 2- permission_date 3- store_houses_id 4- grand_total 5- product_id[] 6- quantity[] 7- unit_price[] 8- total[] 9- details (optional) 10- attachments (file, optional) |
| Status_Code | 200 - success , 403 - no permission / outside scope , 422 - validation error , 402 - plan limit |
| res_body | `{ success, message, data{ id, number, status } }` |

---

| process name | Create permit - disbursement |
|---|---|
| API_End point | `POST permits` |
| description | create disbursement permit |
| Auth_method | Bearer token |
| permission | stock.permits.create_disbursement |
| req_parameters | 1- permission_type = 2 2- permission_date 3- store_houses_id 4- grand_total 5- product_id[] 6- quantity[] 7- unit_price[] 8- total[] 9- details (optional) 10- attachments (file, optional) |
| Status_Code | 200 - success , 403 - no permission / outside scope , 422 - validation error , 402 - plan limit |
| res_body | `{ success, message, data{ id, number, status } }` |

---

| process name | Create permit - transfer |
|---|---|
| API_End point | `POST permits` |
| description | create transfer permit |
| Auth_method | Bearer token |
| permission | stock.permits.create_transfer |
| req_parameters | 1- permission_type = 3 2- permission_date 3- from_store_houses_id 4- to_store_houses_id 5- grand_total 6- product_id[] 7- quantity[] 8- unit_price[] 9- total[] 10- details (optional) 11- attachments (file, optional) |
| Status_Code | 200 - success , 403 - no permission / outside scope , 422 - validation error , 402 - plan limit |
| res_body | `{ success, message, data{ id, number, status } }` |

---

| process name | Create permit - opening balance |
|---|---|
| API_End point | `POST permits` |
| description | create opening balance (approved on save) |
| Auth_method | Bearer token |
| permission | stock.permits.create_opening |
| req_parameters | 1- permission_type = 4 2- permission_date 3- store_houses_id 4- grand_total 5- product_id[] 6- quantity[] 7- unit_price[] 8- total[] 9- details (optional) 10- attachments (file, optional) |
| Status_Code | 200 - success , 403 - no permission / outside scope , 422 - validation error , 402 - plan limit |
| res_body | `{ success, message, data{ id, number, status } }` |

---

| process name | Update permit |
|---|---|
| API_End point | `PUT permits/{id}` |
| description | update pending permit |
| Auth_method | Bearer token |
| permission | stock.permits.edit + create permission of its type |
| req_parameters | same as create |
| Status_Code | 200 - success , 403 - outside scope , 422 - validation error / not pending |
| res_body | `{ success, message }` |

---

| process name | Delete permit |
|---|---|
| API_End point | `DELETE permits/{id}` |
| description | delete not approved permit |
| Auth_method | Bearer token |
| permission | stock.permits.delete |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 422 - approved permit |
| res_body | `{ success, message }` |

---

| process name | Approve permit |
|---|---|
| API_End point | `POST permits/{id}/approve` |
| description | approve permit |
| Auth_method | Bearer token |
| permission | stock.permits.approve |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope , 422 - not pending / insufficient stock / closed period |
| res_body | `{ success, message }` |

---

| process name | Reject permit |
|---|---|
| API_End point | `POST permits/{id}/reject` |
| description | reject permit |
| Auth_method | Bearer token |
| permission | stock.permits.reject |
| req_parameters | 1- rejection_reason (optional) |
| Status_Code | 200 - success , 403 - outside scope , 422 - not pending |
| res_body | `{ success, message }` |

---

| process name | Cancel permit approval |
|---|---|
| API_End point | `POST permits/{id}/cancel-approval` |
| description | cancel permit approval |
| Auth_method | Bearer token |
| permission | stock.permits.cancel_approval |
| req_parameters | none |
| Status_Code | 200 - success , 400 - not approved , 403 - outside scope , 422 - closed period |
| res_body | `{ success, message }` |

---

| process name | List price lists |
|---|---|
| API_End point | `GET price-lists` |
| description | list price lists |
| Auth_method | Bearer token |
| permission | stock.price_lists.view |
| req_parameters | 1- keywords (optional) 2- product (optional) 3- category (optional) 4- brand (optional) 5- status (optional) 6- page (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : [ { id, name, status, created_at, can_edit, can_delete, can_manage_products } ] , pagination{}` |

---

| process name | Price list details |
|---|---|
| API_End point | `GET price-lists/{id}` |
| description | price list details |
| Auth_method | Bearer token |
| permission | stock.price_lists.show |
| req_parameters | none |
| Status_Code | 200 - success , 404 - not found |
| res_body | `data : { id, name, status, created_at, can_edit, can_delete, can_manage_products, items[ { item_id, product_id, name, sale_price } ], available_products[ { id, name, sale_price } ] }` |

---

| process name | Create price list |
|---|---|
| API_End point | `POST price-lists` |
| description | create price list |
| Auth_method | Bearer token |
| permission | stock.price_lists.create |
| req_parameters | 1- name 2- status (optional) |
| Status_Code | 200 - success , 403 - no permission , 422 - validation error , 402 - plan limit |
| res_body | `{ success, message, data{} }` |

---

| process name | Update price list |
|---|---|
| API_End point | `PUT price-lists/{id}` |
| description | update price list |
| Auth_method | Bearer token |
| permission | stock.price_lists.edit |
| req_parameters | 1- name 2- status (optional) |
| Status_Code | 200 - success , 403 - no permission , 422 - validation error |
| res_body | `{ success, message }` |

---

| process name | Delete price list |
|---|---|
| API_End point | `DELETE price-lists/{id}` |
| description | delete price list |
| Auth_method | Bearer token |
| permission | stock.price_lists.delete |
| req_parameters | none |
| Status_Code | 200 - success , 403 - no permission , 404 - not found |
| res_body | `{ success, message }` |

---

| process name | Add product to price list |
|---|---|
| API_End point | `POST price-lists/{id}/products` |
| description | add product with price |
| Auth_method | Bearer token |
| permission | stock.price_lists.manage_products |
| req_parameters | 1- product_id 2- sale_price |
| Status_Code | 200 - success , 403 - product outside scope , 422 - validation error |
| res_body | `{ success, message }` |

---

| process name | Remove product from price list |
|---|---|
| API_End point | `DELETE price-lists/products/{itemId}` |
| description | remove product from price list |
| Auth_method | Bearer token |
| permission | stock.price_lists.manage_products |
| req_parameters | none |
| Status_Code | 200 - success , 404 - not found |
| res_body | `{ success, message }` |

---

| process name | Statement warehouses |
|---|---|
| API_End point | `GET statement/warehouses` |
| description | warehouses available for statement |
| Auth_method | Bearer token |
| permission | stock.reports.employee_statement |
| req_parameters | none |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : [ { id, name } ]` |

---

| process name | Warehouse statement |
|---|---|
| API_End point | `GET statement` |
| description | warehouse statement |
| Auth_method | Bearer token |
| permission | stock.reports.employee_statement |
| req_parameters | 1- warehouse (optional) 2- start_date (optional) 3- end_date (optional) 4- product (optional) 5- category (optional) 6- brand (optional) 7- permission_source (optional) |
| Status_Code | 200 - success , 403 - no permission , 404 - no warehouses |
| res_body | `data : { warehouse{ id, name }, start_date, end_date, statement{}, products[ { id, name, serial_number } ] }` |
