# Manual for fawtrasmart Api doc - Permissions :

-Api_url_based : `{base_url}/api/integration/v1/`

-Headers_required : `Accept: application/json`

---

| process name | User permissions |
|---|---|
| API_End point | `GET permissions` |
| description | user permissions and data scope |
| Auth_method | Bearer token |
| req_parameters | none |
| Status_Code | 200 - success , 401 - invalid token |
| res_body | `data : { permissions{ "permission.name": true/false }, data_scope (all / region / branch / department / team / own), manages_employees }` |

---

| process name | Permissions in login / profile |
|---|---|
| API_End point | `POST auth/login` , `GET profile` |
| description | same permissions inside profile |
| Auth_method | none (login) , Bearer token (profile) |
| req_parameters | as login / profile |
| Status_Code | as login / profile |
| res_body | `profile : { user{}, employee{}, access{ permissions{}, data_scope, manages_employees } }` |
