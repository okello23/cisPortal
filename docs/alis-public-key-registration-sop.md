# Standard Operating Procedure

## Title
A-LIS Remote Backup Keys: Register the Public Key in CIS Portal

## Purpose
This procedure guides ICT support and authorized CIS Portal users on how to register an A-LIS facility SSH public key in the CIS Portal so it can later be deployed to the central backup server without manually editing `authorized_keys` on the server.

## Scope
Use this SOP when:

- a facility is registering its first A-LIS backup public key
- a facility server has been rebuilt and the SSH key changed
- an existing public key must be updated in the CIS Portal

## Roles Responsible

- ICT Support Staff
- ICT Supervisor
- ICT Manager
- ICT Administrator

## Prerequisites

1. The facility server has already generated a public key.
2. The public key has been copied from the facility server.
3. The user has valid CIS Portal login credentials.
4. The relevant facility record already exists in the CIS Portal facility list.

## Public Key Format Expected
The CIS Portal expects a single-line SSH public key in the following format:

```text
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA... alis-offsite-backup
```

The portal will reject:

- empty values
- multi-line keys
- private keys
- duplicate keys already assigned to another facility
- unsupported key types unless explicitly enabled

## Procedure

### Step 1: Log into the CIS Portal

1. Open the CIS Portal in your browser.
2. Enter your username/email and password.
3. Click `Staff Login`.

**Screenshot to insert:** Login screen  
**Suggested caption:** `Figure 1. CIS Portal login page`

---

### Step 2: Open the A-LIS Remote Backup Keys Module

1. After login, go to the main staff dashboard.
2. From the top navigation bar, click `Infrastructure`.
3. Select `A-LIS Remote Backup Keys`.

You should land on the A-LIS Remote Backup Keys registry page, which displays:

- the registered keys table
- the `Add Key` button
- the `Deploy Keys` button
- the `Deployment Status` card on the right
- the `Audit History` section

**Screenshot to insert:** A-LIS Remote Backup Keys registry page  
**Suggested caption:** `Figure 2. A-LIS Remote Backup Keys main page`

---

### Step 3: Start a New Key Registration

1. On the A-LIS Remote Backup Keys page, click `Add Key`.
2. The key registration form opens in a modal window.

The modal contains:

- `Facility`
- `SSH Public Key`
- and, when updating an existing key, `Update Reason` and `Comments`

**Screenshot to insert:** Add Key modal  
**Suggested caption:** `Figure 3. Add Key modal for registering a facility public key`

---

### Step 4: Select the Facility

1. In the `Facility` field, search for the facility using the searchable dropdown.
2. You may search by:
   - facility name
   - district
   - region
   - facility code
3. Click the correct facility from the results list.

Important:

- the facility search is not restricted to facilities assigned to the logged-in user
- always confirm you selected the correct facility before saving

---

### Step 5: Paste the SSH Public Key

1. Copy the facility public key from the A-LIS server.
2. Paste it into the `SSH Public Key` text area.
3. Confirm that:
   - it starts with `ssh-ed25519`
   - it is on one line only
   - it does not contain any private key content

Example:

```text
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA... alis-offsite-backup
```

---

### Step 6: Save the Key

1. Click `Save Key`.
2. The system validates the key.
3. If validation succeeds, the portal:
   - stores the public key
   - generates the fingerprint
   - marks the deployment status as `pending`
   - records the action in audit history

Expected success message:

```text
Key saved successfully. Pending deployment.
```

**Screenshot to insert:** Success message after key registration  
**Suggested caption:** `Figure 4. Public key saved successfully and marked pending deployment`

---

### Step 7: Confirm the Registration

After saving, review the right-hand `Current Key Status` and `Deployment Status` panels.

Confirm that:

- the selected facility is correct
- the key status is shown
- the fingerprint is present
- deployment status shows `pending`

Also review the registry table and audit section to confirm the change has been captured.

---

## Updating an Existing Key

If the facility already has a registered key:

1. Open the same `Add Key` or `Edit` workflow.
2. Select the facility.
3. Paste the new public key.
4. Select an `Update Reason`.
5. If the reason is `Other`, enter a comment explaining the change.
6. Click `Save Key`.

The system will preserve audit history and mark the updated key for deployment.

## Expected Result
At the end of this procedure:

- the facility public key is stored in CIS Portal
- the key fingerprint is generated and saved
- the record is listed in the A-LIS key registry
- the deployment status is `pending`
- the action is captured in audit history

## Troubleshooting

### Issue: Key is rejected
Check whether:

- the key starts with `ssh-ed25519`
- the key is only one line
- the key is a public key, not a private key
- the same key is not already assigned to another facility

### Issue: Facility cannot be found

- verify the facility exists in the facility list
- search using a different field such as district, region, or facility code

### Issue: Update reason is required
This is expected when changing a facility that already has an active key.

### Issue: Deployment status remains pending
Registration is successful, but the key still needs to be deployed through the `Deploy Keys` action.

## Related Records

- A-LIS Remote Backup Keys registry
- Deployment Status panel
- Audit History section
- A-LIS Key Update Reasons list on the staff dashboard

## Recommended Screenshot Pack
For the final published SOP, capture and insert these four screenshots from the live portal:

1. Login screen
2. A-LIS Remote Backup Keys main page
3. Add Key modal
4. Success message and pending deployment state

