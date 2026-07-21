# Resource Multi-Select Fields Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Update resource create/update/read APIs so `sub_industry` and `sub_service` support multiple selections as arrays.

**Architecture:** Persist both fields as JSON arrays in the `resources` table, normalize request payloads into arrays before validation, and expose both raw arrays and label arrays in resource responses. Keep metadata options unchanged, and migrate existing single-string data into one-item arrays to preserve backward compatibility.

**Tech Stack:** Laravel, MySQL JSON columns, FormRequest validation, PHPUnit feature tests, Postman collection generator

## Global Constraints

- `sub_industry` and `sub_service` must accept multiple selected values in create and update APIs.
- Existing resource records must remain readable after migration.
- Response payloads must stay frontend-friendly and include label arrays for selected values.
- Postman examples must reflect the modified request shape.

---

### Task 1: Lock failing API expectations

**Files:**
- Modify: `tests/Feature/Api/ResourceControllerTest.php`

**Interfaces:**
- Consumes: `POST /api/resources`, `PUT /api/resources/{id}`, `GET /api/resources/{id}`
- Produces: Failing tests that define array-based request/response behavior for `sub_industry` and `sub_service`

- [ ] Add create/update/show assertions that use array input and expect array output
- [ ] Run targeted resource tests and confirm they fail for the missing multi-select behavior

### Task 2: Migrate persistence to JSON arrays

**Files:**
- Create: `database/migrations/2026_07_21_000000_convert_resource_sub_fields_to_json_arrays.php`
- Modify: `app/Models/Resource.php`

**Interfaces:**
- Consumes: Existing `resources.sub_industry` and `resources.sub_service` string values
- Produces: JSON-backed `sub_industry` and `sub_service` arrays with Eloquent casts

- [ ] Convert existing non-null string values into one-item JSON arrays
- [ ] Alter both columns to JSON nullable fields
- [ ] Add Eloquent casts so resources hydrate those fields as arrays

### Task 3: Normalize and validate array input

**Files:**
- Modify: `app/Http/Requests/Api/StoreResourceRequest.php`

**Interfaces:**
- Consumes: JSON requests, multipart form-data, repeated `sub_industry[]` / `sub_service[]` values
- Produces: Validated normalized arrays for controller usage

- [ ] Normalize string, JSON-string, and repeated field inputs into arrays in `prepareForValidation()`
- [ ] Validate the top-level fields as arrays and each member against config values

### Task 4: Return updated API contract

**Files:**
- Modify: `app/Http/Controllers/Api/ResourceController.php`

**Interfaces:**
- Consumes: Validated resource arrays from `StoreResourceRequest`
- Produces: Resource responses containing `sub_industry`, `sub_industry_labels`, `sub_service`, and `sub_service_labels`

- [ ] Store normalized arrays during create/update
- [ ] Return label arrays for both fields in full resource responses
- [ ] Keep list/show behavior otherwise unchanged

### Task 5: Refresh Postman examples and verify

**Files:**
- Modify: `postman/generate_collection.php`
- Modify: `postman/THCWebsite3.0_AdminPanel_API.postman_collection.json`

**Interfaces:**
- Consumes: Updated array-based resource API contract
- Produces: Import-ready Postman requests using repeated `sub_industry[]` and `sub_service[]` form-data fields

- [ ] Update resource create/update examples in the generator
- [ ] Regenerate the collection JSON
- [ ] Run verification commands and capture actual outcomes
