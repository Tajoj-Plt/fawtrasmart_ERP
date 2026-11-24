# Manual for fawtrasmart Api doc :

-Api_url_based : `{base_url}/api/integration/v1/`

-Headers_required : `Accept: application/json`

---

| process name | Auth process |
|---|---|
| API_End point | `POST auth/login` |
| description | validate user credential and authorised |
| Auth_method | none |
| req_parameters | 1- email 2- password 3- device_name (optional) |
| Status_Code | 200 - success , 401 - wrong credential , 403 - not employee |
| res_body | `data : { token, token_type, email_verified, profile { user{}, employee{} } }` |

---

| process name | Logout process |
|---|---|
| API_End point | `POST auth/logout` |
| description | revoke current device token |
| Auth_method | Bearer token |
| req_parameters | none |
| Status_Code | 200 - success , 401 - invalid token |
| res_body | `data : { }` |

---

| process name | Forgot password |
|---|---|
| API_End point | `POST auth/password/forgot` |
| description | send otp code to employee email |
| Auth_method | none |
| req_parameters | 1- email |
| Status_Code | 200 - success , 429 - too many requests |
| res_body | `data : { }` |

---

| process name | Verify reset otp |
|---|---|
| API_End point | `POST auth/password/verify-otp` |
| description | check otp code before reset |
| Auth_method | none |
| req_parameters | 1- email 2- otp (6 digits) |
| Status_Code | 200 - success , 422 - wrong or expired otp |
| res_body | `data : { }` |

---

| process name | Reset password |
|---|---|
| API_End point | `POST auth/password/reset` |
| description | set new password by otp |
| Auth_method | none |
| req_parameters | 1- email 2- otp 3- password 4- password_confirmation |
| Status_Code | 200 - success , 422 - wrong or expired otp |
| res_body | `data : { }` |

---

| process name | Send email verification otp |
|---|---|
| API_End point | `POST auth/email/send-otp` |
| description | send otp to verify employee email |
| Auth_method | Bearer token |
| req_parameters | none |
| Status_Code | 200 - success , 401 - invalid token |
| res_body | `data : { }` |

---

| process name | Verify email |
|---|---|
| API_End point | `POST auth/email/verify` |
| description | verify employee email by otp |
| Auth_method | Bearer token |
| req_parameters | 1- otp (6 digits) |
| Status_Code | 200 - success , 422 - wrong or expired otp |
| res_body | `data : { }` |

---

| process name | Get profile |
|---|---|
| API_End point | `GET profile` |
| description | get employee profile data |
| Auth_method | Bearer token |
| req_parameters | none |
| Status_Code | 200 - success , 401 - invalid token |
| res_body | `data : { user{ id, name, email, phone, avatar_url, email_verified, branch_id }, employee{ id, employee_code, first_name, full_name, photo_url, mobile_number, job_title, department, branch, emergency_contact{}, address{} } }` |

---

| process name | Update profile |
|---|---|
| API_End point | `PUT profile` |
| description | update employee profile data |
| Auth_method | Bearer token |
| req_parameters | 1- name 2- phone 3- mobile_number 4- phone_number 5- personal_email 6- language 7- nickname 8- emergency_contact_name 9- emergency_contact_phone 10- emergency_contact_relation 11- current_address_1 12- current_address_2 13- city 14- region 15- postal_code (all optional) |
| Status_Code | 200 - success , 422 - validation error |
| res_body | `data : { user{}, employee{} }` |

---

| process name | Update avatar |
|---|---|
| API_End point | `POST profile/avatar` |
| description | upload employee profile image |
| Auth_method | Bearer token |
| req_parameters | 1- avatar (file: jpg / jpeg / png / webp , max 2MB) — multipart/form-data |
| Status_Code | 200 - success , 422 - invalid file |
| res_body | `data : { avatar_url }` |

---

| process name | Change password |
|---|---|
| API_End point | `POST profile/change-password` |
| description | change password while logged in |
| Auth_method | Bearer token |
| req_parameters | 1- current_password 2- password 3- password_confirmation |
| Status_Code | 200 - success , 422 - wrong current password |
| res_body | `data : { }` |
