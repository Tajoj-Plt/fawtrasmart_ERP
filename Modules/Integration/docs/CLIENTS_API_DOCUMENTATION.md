# Manual for fawtrasmart Api doc - Clients :

-Api_url_based : `{base_url}/api/integration/v1/`

-Headers_required : `Accept: application/json`

---

| process name | Branches |
|---|---|
| API_End point | `GET branches` |
| description | branches the user can filter by |
| Auth_method | Bearer token |
| permission | none |
| req_parameters | 1- search (optional) |
| Status_Code | 200 - success |
| res_body | `data : { current_branch_id, restricted, branches[ { id, name, code, parent_id, node_type, type_label, is_main, is_current } ] }` |

---

| process name | Client settings |
|---|---|
| API_End point | `GET clients/settings` |
| description | client module settings for the app |
| Auth_method | Bearer token |
| permission | none |
| req_parameters | none |
| Status_Code | 200 - success |
| res_body | `data : { sections{ visits, itinerary, notes, route_tracking }, required_fields{}, client_type_scope, visit{ proximity_meters, competitor_capture, signature_required, note_deposit_count, note_photo, note_min_photos, hide_finished_clients, allow_return_same_day }, status_change_requires_approval, itinerary_max_clients_per_day, max_upload_kb, image_quality, image_max_dimension, site_types[], shift{ full_time_value, full_time_label, hours[ { value, label } ], format } }` |

---

| process name | List clients |
|---|---|
| API_End point | `GET clients` |
| description | clients list |
| Auth_method | Bearer token |
| permission | clients_view_all_clients / clients_view_own_clients / clients_view_by_employee_groups / clients_view_by_daily_itinerary |
| req_parameters | 1- search (optional) 2- branch_id (optional) 3- include_descendants (1 / 0, optional) 4- status (id / no_status, optional) 5- registration_stage (optional) 6- category_id (optional) 7- type (1 / 2, optional) 8- region (optional) 9- neighborhood (optional) 10- date_from (optional) 11- date_to (optional) 12- employee (optional) 13- over_credit_limit (optional) 14- customer_target_id (id / no_target, optional) 15- last_invoice_period (optional) 16- last_payment_period (optional) 17- last_activity_period (optional) 18- sort (newest / name, optional) 19- latitude + longitude (optional) 20- per_page (1-100, optional) 21- page (optional) |
| Status_Code | 200 - success , 403 - no permission / branch outside scope , 422 - validation |
| res_body | `data : [ { id, code, trade_name, phone, mobile, status{ id, name, color }, branch{ id, name }, neighborhood, location{ latitude, longitude }, balance, balance_formatted, balance_direction, needs_visit, open_visit_id, distance_km } ] , meta{ current_page, last_page, per_page, total }` |

---

| process name | Clients map |
|---|---|
| API_End point | `GET clients/map` |
| description | clients with location |
| Auth_method | Bearer token |
| permission | clients_view_all_clients / clients_view_own_clients / clients_view_by_employee_groups / clients_view_by_daily_itinerary |
| req_parameters | same filters as list clients |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : { clients[ { id, code, trade_name, phone, mobile, status{}, branch{}, location{}, balance, balance_direction, branch_path, needs_visit, open_visit_id, itinerary_source{ tone, label } } ], total, itinerary_only }` |

---

| process name | Client form options |
|---|---|
| API_End point | `GET clients/options` |
| description | lists for add / edit client form |
| Auth_method | Bearer token |
| permission | clients_add_client / clients_edit_data |
| req_parameters | 1- client_id (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : { branch_tree[ { id, name, parent_id, selectable, node_type, type_label } ], categories[], statuses[], registration_stages[], can_assign_employees, employees[ { id, name, department } ], client_type_scope, next_code, required_fields{}, max_upload_kb }` |

---

| process name | Add client |
|---|---|
| API_End point | `POST clients` |
| description | add client (multipart/form-data) |
| Auth_method | Bearer token |
| permission | clients_add_client |
| req_parameters | 1- client_tree_branch_id 2- trade_name 3- phone 4- mobile 5- email 6- first_name 7- last_name 8- category_id 9- registration_stage_id 10- client_type 11- tax_number 12- commercial_registration 13- currency 14- printing_method 15- street1 16- street2 17- city 18- region 19- postal_code 20- country 21- notes 22- latitude 23- longitude 24- neighborhood_name_manual 25- attachments (image) 26- employee_client_id[] 27- contacts[] — required fields from settings |
| Status_Code | 201 - created , 403 - no permission , 422 - validation , 500 - not saved |
| res_body | `{ success, message, data : { id, code, trade_name, ..., visit_id } }` |

---

| process name | Client details |
|---|---|
| API_End point | `GET clients/{id}` |
| description | client details |
| Auth_method | Bearer token |
| permission | clients_view_all_clients / clients_view_own_clients / clients_view_by_employee_groups / clients_view_by_daily_itinerary |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope / MUST_VISIT_FIRST / VISIT_FINISHED_TODAY , 404 - not found |
| res_body | `data : { id, code, trade_name, phone, mobile, email, status{}, branch{}, branch_path, category{}, registration_stage{}, location{}, balance, credit_limit, item_quantity_limit, attachment_url, assigned_employees[], contacts[], can{ edit, change_status, add_note, view_notes, open_visit }, ... }` |

---

| process name | Update client |
|---|---|
| API_End point | `POST clients/{id}` |
| description | update client (multipart/form-data) |
| Auth_method | Bearer token |
| permission | clients_edit_data |
| req_parameters | same as add client + item_quantity_limit (attachments optional) |
| Status_Code | 200 - success , 403 - no permission / outside scope , 404 - not found , 422 - validation |
| res_body | `{ success, message, data : { client } }` |

---

| process name | Status options |
|---|---|
| API_End point | `GET clients/{id}/status-options` |
| description | status and stage lists with approvers |
| Auth_method | Bearer token |
| permission | clients_change_status |
| req_parameters | none |
| Status_Code | 200 - success , 403 - no permission / outside scope |
| res_body | `data : { current_status_id, current_stage_id, statuses[ { id, name, color } ], stages[], status_needs_approval, stage_needs_approval, status_approvers[ { id, name } ], stage_approvers[] }` |

---

| process name | Change status |
|---|---|
| API_End point | `POST clients/{id}/status` |
| description | change client status or send approval request |
| Auth_method | Bearer token |
| permission | clients_change_status |
| req_parameters | 1- status_id 2- manager_id (required when approval needed) 3- notes (optional) 4- visit_id (optional) |
| Status_Code | 200 - success , 403 - outside scope , 422 - validation / same status |
| res_body | `{ success, message, data : { client_id, status_id, pending_approval } }` |

---

| process name | Change stage |
|---|---|
| API_End point | `POST clients/{id}/stage` |
| description | change client stage or send approval request |
| Auth_method | Bearer token |
| permission | clients_change_status |
| req_parameters | 1- stage_id (null = no stage) 2- manager_id (required when approval needed) 3- notes (optional) 4- visit_id (optional) |
| Status_Code | 200 - success , 403 - outside scope , 422 - validation / same stage |
| res_body | `{ success, message, data : { client_id, stage_id, pending_approval } }` |

---

| process name | Client contacts |
|---|---|
| API_End point | `GET clients/{id}/contacts` |
| description | client staff contacts with shift |
| Auth_method | Bearer token |
| permission | client_follow_up_view_notes_attachments_appointments_all / _assigned / _own |
| req_parameters | none |
| Status_Code | 200 - success , 403 - outside scope |
| res_body | `data : [ { id, employee_name, employee_position, employee_phone, is_primary, shift, shift_start, shift_end, shift_label, image_url, invoice_id, invoice_code, created_by, created_at } ]` |

---

| process name | Add contact |
|---|---|
| API_End point | `POST clients/{id}/contacts` |
| description | add client staff contact |
| Auth_method | Bearer token |
| permission | client_follow_up_add_notes_attachments_appointments_all / _assigned / _own / clients_add_employee_note |
| req_parameters | 1- employee_name 2- employee_phone 3- shift (full / HH:00\|HH:00) 4- employee_position (optional) 5- is_primary (optional) 6- invoice_id (optional) 7- image (optional) |
| Status_Code | 201 - created , 403 - outside scope , 422 - validation |
| res_body | `{ success, message, data : { contact } }` |

---

| process name | Client notes |
|---|---|
| API_End point | `GET clients/{id}/notes` |
| description | follow-up notes |
| Auth_method | Bearer token |
| permission | client_follow_up_view_notes_attachments_appointments_all / _assigned / _own |
| req_parameters | 1- per_page (optional) 2- page (optional) |
| Status_Code | 200 - success , 403 - outside scope |
| res_body | `data : [ { id, client_id, description, process, status, deposit_count, competitor_documents, site_type, shift_start, shift_end, contact{ id, name }, employee{ id, name }, attachments[ { index, url } ], competitors[], can_edit, created_at } ] , meta{}` |

---

| process name | Add note |
|---|---|
| API_End point | `POST clients/{id}/notes` |
| description | add follow-up note (multipart/form-data) |
| Auth_method | Bearer token |
| permission | client_follow_up_add_notes_attachments_appointments_all / _assigned / _own / clients_add_employee_note |
| req_parameters | 1- client_employee_id 2- description 3- deposit_count 4- attachments[] 5- current_latitude 6- current_longitude 7- shift (optional) 8- process (optional) 9- site_type (optional) |
| Status_Code | 201 - created , 403 - outside scope , 422 - validation / out of range |
| res_body | `{ success, message, data : { note } }` |

---

| process name | Update note |
|---|---|
| API_End point | `POST notes/{id}` |
| description | update note (multipart/form-data) |
| Auth_method | Bearer token |
| permission | client_follow_up_edit_delete_notes_attachments_appointments_all / _assigned / _own |
| req_parameters | 1- client_employee_id 2- description 3- deposit_count 4- site_type 5- competitor_documents 6- shift 7- attachments[] (optional) |
| Status_Code | 200 - success , 403 - not allowed , 404 - not found , 422 - validation |
| res_body | `{ success, message, data : { note } }` |

---

| process name | Delete note attachment |
|---|---|
| API_End point | `DELETE notes/{id}/attachments/{index}` |
| description | delete one attachment |
| Auth_method | Bearer token |
| permission | client_follow_up_edit_delete_notes_attachments_appointments_all / _assigned / _own |
| req_parameters | none |
| Status_Code | 200 - success , 403 - not allowed , 404 - not found |
| res_body | `{ success, message, data : { remaining } }` |

---

| process name | Delete note |
|---|---|
| API_End point | `DELETE notes/{id}` |
| description | delete note |
| Auth_method | Bearer token |
| permission | client_follow_up_edit_delete_notes_attachments_appointments_all / _assigned / _own |
| req_parameters | none |
| Status_Code | 200 - success , 403 - not allowed , 404 - not found |
| res_body | `{ success, message }` |

---

| process name | Itinerary day |
|---|---|
| API_End point | `GET itinerary/day` |
| description | daily itinerary |
| Auth_method | Bearer token |
| permission | clients_daily_itinerary_view / clients_daily_itinerary_add_for_others |
| req_parameters | 1- date (optional) 2- employee_id (optional) |
| Status_Code | 200 - success , 403 - no permission / section disabled , 422 - validation |
| res_body | `data : { date, date_label, is_today, is_past, stops[ { id, client_id, name, code, client_status, place, address, phone, lat, lng, state, start, end, leg_km, leg_minutes, added_by, note, photos[] } ], stats{ done, total, percent, km, minutes }, employee{ id, name }, is_self, can{ add, postpone, start } }` |

---

| process name | Itinerary employees |
|---|---|
| API_End point | `GET itinerary/employees` |
| description | employees whose itinerary user can open |
| Auth_method | Bearer token |
| permission | clients_daily_itinerary_view / clients_daily_itinerary_add_for_others |
| req_parameters | none |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : [ { id, name } ]` |

---

| process name | Itinerary picker clients |
|---|---|
| API_End point | `GET itinerary/clients` |
| description | clients available to add |
| Auth_method | Bearer token |
| permission | clients_daily_itinerary_add_client / clients_daily_itinerary_add_for_others |
| req_parameters | 1- q (optional) 2- node_id (optional) 3- date (optional) 4- employee_id (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : { clients[], total, limit, nodes[] }` |

---

| process name | Add clients to itinerary |
|---|---|
| API_End point | `POST itinerary/clients` |
| description | add clients to a day |
| Auth_method | Bearer token |
| permission | clients_daily_itinerary_add_client / clients_daily_itinerary_add_for_others |
| req_parameters | 1- client_ids[] 2- date (optional) 3- employee_id (optional) |
| Status_Code | 200 - success , 403 - no permission , 422 - validation / nothing added / locked |
| res_body | `{ success, message, data : { added, already, out_of_scope, unassigned, full, locked } }` |

---

| process name | Postpone visit |
|---|---|
| API_End point | `POST itinerary/{rowId}/postpone` |
| description | move planned visit to tomorrow |
| Auth_method | Bearer token |
| permission | clients_itinerary_edit |
| req_parameters | none |
| Status_Code | 200 - success , 403 - no permission , 404 - not found , 422 - cannot postpone |
| res_body | `{ success, message, data : { id, visit_date } }` |

---

| process name | Return client to itinerary |
|---|---|
| API_End point | `POST clients/{id}/return-to-itinerary` |
| description | return finished client to today |
| Auth_method | Bearer token |
| permission | clients_visits_track / clients_itinerary_add / clients_visit_workspace_access / clients_must_visit_before_view |
| req_parameters | none |
| Status_Code | 200 - success , 403 - disabled in settings , 422 - not finished today |
| res_body | `{ success, message, data : { client_id } }` |

---

| process name | Open visit |
|---|---|
| API_End point | `POST clients/{id}/visits/open` |
| description | open today visit or return the open one |
| Auth_method | Bearer token |
| permission | clients_visits_track / clients_itinerary_add / clients_visit_workspace_access / clients_must_visit_before_view |
| req_parameters | none |
| Status_Code | 201 - created , 200 - existing , 403 - outside scope |
| res_body | `data : { visit }` — same as visit details |

---

| process name | Visit details |
|---|---|
| API_End point | `GET visits/{id}` |
| description | visit workspace |
| Auth_method | Bearer token |
| permission | clients_visits_track / clients_itinerary_add / clients_visit_workspace_access / clients_must_visit_before_view |
| req_parameters | none |
| Status_Code | 200 - success , 403 - not owner , 404 - not found |
| res_body | `data : { visit_id, visit_number, visit_date, stage, started_at, paused, elapsed_seconds, departure_time, outcome, cancel_reason, employee{}, client{}, client_location{}, steps{ has_contact, has_shift, has_note, competitor_step, has_competitors, docs_unlocked, has_sale, can_complete, can_return, outcome }, rules{ proximity_meters, any_location, competitor_capture, signature_required, note_deposit_count, note_photo, note_min_photos, max_upload_kb }, can{ complete, cancel, change_shift, reply_notifications, change_status, status_needs_approval, stage_needs_approval }, status_approvers[], stage_approvers[], contacts[], notes[], competitors_observed[], competitor_options[], client_notifications[], visit_documents[ { group, type, id, number, amount, at } ], client_documents{ invoices[], returns[], receipts[], expenses[], credit_notes[], exchanges[] } }` |

---

| process name | Start visit |
|---|---|
| API_End point | `POST visits/{id}/start` |
| description | start visit from client location |
| Auth_method | Bearer token |
| permission | clients_visits_track / clients_itinerary_add / clients_visit_workspace_access / clients_must_visit_before_view |
| req_parameters | 1- current_latitude 2- current_longitude |
| Status_Code | 200 - success , 403 - not owner , 422 - validation / OUT_OF_RANGE / closed |
| res_body | `data : { visit_id, stage, started_at, paused, elapsed_seconds }` |

---

| process name | Toggle pause |
|---|---|
| API_End point | `POST visits/{id}/toggle-pause` |
| description | pause / resume visit timer |
| Auth_method | Bearer token |
| permission | clients_visits_track / clients_itinerary_add / clients_visit_workspace_access / clients_must_visit_before_view |
| req_parameters | none |
| Status_Code | 200 - success , 422 - VISIT_NOT_STARTED / VISIT_CLOSED |
| res_body | `data : { visit_id, stage, started_at, paused, elapsed_seconds }` |

---

| process name | Visit contact |
|---|---|
| API_End point | `POST visits/{id}/contact` |
| description | step 1 — add client contact |
| Auth_method | Bearer token |
| permission | workspace + client_follow_up_add_notes_attachments_appointments_all / _assigned / _own / clients_add_employee_note |
| req_parameters | same as add contact |
| Status_Code | 201 - created , 422 - validation / VISIT_NOT_STARTED / VISIT_CLOSED |
| res_body | `data : { contact, steps{} }` |

---

| process name | Contact shift |
|---|---|
| API_End point | `POST visits/{id}/contact-shift` |
| description | step 2 — set contact shift |
| Auth_method | Bearer token |
| permission | clients_visits_track / clients_itinerary_add / clients_visit_workspace_access / clients_must_visit_before_view |
| req_parameters | 1- client_employee_id 2- shift (full / HH:00\|HH:00) |
| Status_Code | 200 - success , 403 - change needs clients_change_contact_shift , 422 - validation |
| res_body | `data : { contact, steps{} }` |

---

| process name | Visit note |
|---|---|
| API_End point | `POST visits/{id}/notes` |
| description | step 3 — visit note (multipart/form-data) |
| Auth_method | Bearer token |
| permission | workspace + client_follow_up_add_notes_attachments_appointments_all / _assigned / _own / clients_add_employee_note |
| req_parameters | 1- client_employee_id 2- description 3- deposit_count 4- attachments[] 5- current_latitude 6- current_longitude 7- shift (optional) 8- process (optional) 9- site_type (optional) |
| Status_Code | 201 - created , 403 - shift change not allowed , 422 - validation / out of range / VISIT_NOT_STARTED / VISIT_CLOSED |
| res_body | `data : { note_id, steps{} }` |

---

| process name | Quick competitor |
|---|---|
| API_End point | `POST visits/{id}/competitors/quick` |
| description | add new competitor to list |
| Auth_method | Bearer token |
| permission | workspace + client_follow_up_add_notes_attachments_appointments_all / _assigned / _own / clients_add_employee_note |
| req_parameters | 1- name |
| Status_Code | 201 - created , 200 - existed , 422 - validation / disabled competitor |
| res_body | `data : { id, name, existed }` |

---

| process name | Competitors observation |
|---|---|
| API_End point | `POST visits/{id}/competitors` |
| description | step 4 — competitors (multipart/form-data) |
| Auth_method | Bearer token |
| permission | workspace + client_follow_up_add_notes_attachments_appointments_all / _assigned / _own / clients_add_employee_note |
| req_parameters | 1- competitors[i][competitor_id] 2- competitors[i][asset_count] 3- competitors[i][photo] — or 1- no_competitors=1 2- no_competitors_photo |
| Status_Code | 200 - success , 422 - validation / NOTE_REQUIRED / STEP_DISABLED |
| res_body | `data : { saved, steps{} }` |

---

| process name | Read client notification |
|---|---|
| API_End point | `POST visits/{id}/notifications/{notificationId}/read` |
| description | mark client notification as read |
| Auth_method | Bearer token |
| permission | clients_visits_track / clients_itinerary_add / clients_visit_workspace_access / clients_must_visit_before_view |
| req_parameters | none |
| Status_Code | 200 - success , 404 - not found |
| res_body | `data : { id, read }` |

---

| process name | Reply client notification |
|---|---|
| API_End point | `POST visits/{id}/notifications/{notificationId}/reply` |
| description | reply to client notification |
| Auth_method | Bearer token |
| permission | workspace + notifications_reply_to_notifications |
| req_parameters | 1- reply_message |
| Status_Code | 200 - success , 404 - not found , 422 - validation |
| res_body | `data : { id }` |

---

| process name | Complete visit |
|---|---|
| API_End point | `POST visits/{id}/complete` |
| description | finish visit |
| Auth_method | Bearer token |
| permission | workspace + clients_visit_complete |
| req_parameters | 1- current_latitude 2- current_longitude 3- client_signature (data:image, by settings) 4- employee_signature (data:image, by settings) 5- outcome_notes (optional) 6- return_later (optional) 7- return_time (HH:MM, with return_later) |
| Status_Code | 200 - success , 422 - STEPS_INCOMPLETE / OUT_OF_RANGE / VISIT_CLOSED / validation |
| res_body | `data : { visit_id, outcome, duration_minutes, return_later }` |

---

| process name | Cancel visit |
|---|---|
| API_End point | `POST visits/{id}/cancel` |
| description | cancel visit |
| Auth_method | Bearer token |
| permission | workspace + clients_visit_cancel |
| req_parameters | 1- cancel_reason 2- current_latitude 3- current_longitude |
| Status_Code | 200 - success , 422 - validation / OUT_OF_RANGE / closed |
| res_body | `data : { visit_id }` |

---

| process name | Forgotten visits |
|---|---|
| API_End point | `GET visits/forgotten` |
| description | started visits not finished |
| Auth_method | Bearer token |
| permission | clients_view_forgotten_visits_own / clients_view_forgotten_visits_all |
| req_parameters | 1- scope (own / all, optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : { scope, count, visits[ { id, visit_number, visit_date, started_at, stage, employee{}, client{}, is_own } ] }` |

---

| process name | Close forgotten visit |
|---|---|
| API_End point | `POST visits/forgotten/{id}/close` |
| description | finish forgotten visit remotely |
| Auth_method | Bearer token |
| permission | clients_close_forgotten_visits_own / clients_force_close_forgotten_visits |
| req_parameters | 1- notes (optional) |
| Status_Code | 200 - success , 403 - no permission , 404 - not forgotten |
| res_body | `data : { visit_id }` |

---

| process name | Cancel forgotten visit |
|---|---|
| API_End point | `POST visits/forgotten/{id}/cancel` |
| description | cancel forgotten visit |
| Auth_method | Bearer token |
| permission | clients_cancel_forgotten_visits |
| req_parameters | 1- cancel_reason |
| Status_Code | 200 - success , 403 - not allowed , 404 - not forgotten , 422 - validation |
| res_body | `data : { visit_id }` |

---

| process name | Pending justifications |
|---|---|
| API_End point | `GET visit-justifications/pending` |
| description | incomplete visits needing justification |
| Auth_method | Bearer token |
| permission | none |
| req_parameters | none |
| Status_Code | 200 - success |
| res_body | `data : { exempt, pending_count, awaiting_approval, visits[ { id, visit_date, day_of_week, client{} } ] }` |

---

| process name | Submit justifications |
|---|---|
| API_End point | `POST visit-justifications` |
| description | justify incomplete visits |
| Auth_method | Bearer token |
| permission | none |
| req_parameters | 1- justifications{ visit_id : text } |
| Status_Code | 200 - success , 422 - validation / none accepted / exempt |
| res_body | `data : { submitted[], rejected[] }` |

---

| process name | My justifications |
|---|---|
| API_End point | `GET visit-justifications` |
| description | submitted justifications |
| Auth_method | Bearer token |
| permission | clients_visit_justifications_view_own / clients_visit_justifications_view_all |
| req_parameters | 1- page (optional) |
| Status_Code | 200 - success , 403 - no permission |
| res_body | `data : [ { id, visit_date, client{}, justification, justification_date, status, approved_by } ] , meta{} , periods[]` |

---

| process name | Justification details |
|---|---|
| API_End point | `GET visit-justifications/{id}` |
| description | justification details |
| Auth_method | Bearer token |
| permission | clients_visit_justifications_view_own / clients_visit_justifications_view_all |
| req_parameters | none |
| Status_Code | 200 - success , 404 - not found |
| res_body | `data : { justification }` |

---

| process name | Update justification |
|---|---|
| API_End point | `PUT visit-justifications/{id}` |
| description | edit justification before approval |
| Auth_method | Bearer token |
| permission | clients_visit_justifications_view_own / clients_visit_justifications_view_all |
| req_parameters | 1- justification |
| Status_Code | 200 - success , 404 - not found , 422 - validation / approved |
| res_body | `data : { justification }` |

---

| process name | Notifications |
|---|---|
| API_End point | `GET notifications` |
| description | my notifications |
| Auth_method | Bearer token |
| permission | sales.notifications.view + notifications_view_all_notifications / data_scope_all |
| req_parameters | 1- unread (optional) 2- client_id (optional) 3- type (optional) 4- target (clients / employees, optional) 5- reply_status (unanswered / answered, optional) 6- date_from (optional) 7- date_to (optional) 8- per_page (optional) 9- page (optional) |
| Status_Code | 200 - success , 402 - plan , 403 - no permission |
| res_body | `data : [ { id, title, description, type, read, is_sender, is_receiver, from{}, to{}, client{}, scheduled_at, created_at } ] , meta{} , unread_count` |

---

| process name | Unread count |
|---|---|
| API_End point | `GET notifications/unread-count` |
| description | unread notifications in last 24 hours |
| Auth_method | Bearer token |
| permission | sales.notifications.view |
| req_parameters | none |
| Status_Code | 200 - success , 402 - plan , 403 - no permission |
| res_body | `data : { count }` |

---

| process name | Notification details |
|---|---|
| API_End point | `GET notifications/{id}` |
| description | notification with replies |
| Auth_method | Bearer token |
| permission | sales.notifications.view |
| req_parameters | none |
| Status_Code | 200 - success , 404 - not found |
| res_body | `data : { notification, replies[ { id, description, from, created_at } ], decision }` |

---

| process name | Mark read |
|---|---|
| API_End point | `POST notifications/{id}/read` |
| description | mark notification as read |
| Auth_method | Bearer token |
| permission | sales.notifications.view |
| req_parameters | none |
| Status_Code | 200 - success , 404 - not found |
| res_body | `data : { id, read }` |

---

| process name | Mark all read |
|---|---|
| API_End point | `POST notifications/read-all` |
| description | mark all my notifications as read |
| Auth_method | Bearer token |
| permission | sales.notifications.view |
| req_parameters | none |
| Status_Code | 200 - success |
| res_body | `data : { updated }` |

---

| process name | Reply notification |
|---|---|
| API_End point | `POST notifications/{id}/reply` |
| description | reply to notification |
| Auth_method | Bearer token |
| permission | sales.notifications.reply + notifications_reply_to_notifications / data_scope_all |
| req_parameters | 1- reply_message |
| Status_Code | 201 - created , 403 - not a party , 422 - validation / no other party |
| res_body | `data : { id, reply_to }` |

---

| process name | Send location |
|---|---|
| API_End point | `POST tracking/location` |
| description | send current location or batch |
| Auth_method | Bearer token |
| permission | clients_itinerary_edit |
| req_parameters | 1- latitude 2- longitude — or 1- points[ { latitude, longitude, recorded_at } ] (max 100) |
| Status_Code | 200 - success , 403 - no permission / TRACKING_DISABLED , 422 - validation , 429 - too many |
| res_body | `data : { received, saved, skipped, rejected }` |

---

| process name | Path |
|---|---|
| API_End point | `GET tracking/path` |
| description | day path and current location |
| Auth_method | Bearer token |
| permission | clients_itinerary_view / clients_view_by_daily_itinerary |
| req_parameters | 1- date (optional) 2- employee_id (optional) |
| Status_Code | 200 - success , 403 - outside team / TRACKING_DISABLED , 422 - validation |
| res_body | `data : { employee{ id, name }, date, current{ latitude, longitude, is_live, updated_at }, distance_km, points_count, points[ { latitude, longitude, at } ] }` |

---

| process name | Team locations |
|---|---|
| API_End point | `GET tracking/team` |
| description | latest location of team |
| Auth_method | Bearer token |
| permission | clients_itinerary_view / clients_view_by_daily_itinerary |
| req_parameters | none |
| Status_Code | 200 - success , 403 - no permission / TRACKING_DISABLED |
| res_body | `data : { count, employees[ { employee{ id, name }, latitude, longitude, is_live, updated_at, is_me } ] }` |
