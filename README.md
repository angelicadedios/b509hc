# Barangay 509 Health Center – Appointment System (Vercel version)

Plain PHP + MySQL, no framework. Hosted for free on **Vercel** (website) + **TiDB Cloud** (MySQL-compatible database),
with the code kept on **GitHub**. Every time you upload changes to GitHub, Vercel updates the website by itself.

| Part | Service | Cost |
|---|---|---|
| Code | GitHub (private repository) | Free |
| Website (PHP 8.4) | Vercel Hobby + `vercel-php` runtime | Free |
| Database + uploaded files | TiDB Cloud Starter (MySQL-compatible) | Free |
| Patient login | Google Sign-In | Free |
| "Account approved" email | Your Gmail + App Password | Free (about 500 emails/day) |

None of these ask for a credit card.

---

## 1. What changed from the InfinityFree version

**For Vercel**
- `api/index.php`: Vercel sends every page request here, and it opens the right PHP page. Only real pages can be opened.
- `vercel.json`: tells Vercel to use PHP and to run in Singapore (`sin1`), close to the database.
- `config.php` has **no passwords** anymore. It reads them from Vercel's *Environment Variables*, so it's safe on GitHub.
- Logins (sessions) and uploaded files are saved **in the database**, because Vercel doesn't keep files.
- `api/slots.php` moved to `ajax/slots.php`.

**New features**
- **Registration with approval:** patients enter their PhilHealth / YAKAP number and upload their ID. They can't book until staff press **Verify**. Then they get an email with a link to the login page.
- **Dependent ID:** a dependent without PhilHealth gets the holder's number + `.D1`, `.D2`… (e.g. `12-345678901-2.D1`).
- **Medicine Given** (Appointments popup, after *Mark as arrived*): add rows (medicine with type-to-search, quantity, doctor dropdown); the logged-in staff is recorded as *Distributed by*; a warning popup appears before saving; stock goes down automatically.
- **Medicine stock:** click a medicine to see who received it (search + Today / 7 / 14 / 30 / 90 days / All). A **Given today** table with a **Print / other dates** page.
- **Patient profiles** (new staff page): search, filter by status, **View** (info, PhilHealth ID picture, dependents, appointment history, medicines given), **Verify**, **Ask to fix**, **Deactivate** (with reason) and **Reactivate**.
- **Doctors** page (administrators): the list used in the Doctor dropdown.
- **Privacy Policy** updated for PhilHealth data, medicine records and the new providers.

**About "Delete":** there is no delete button on purpose. Health records need to be kept, and deleting a patient would also erase their appointment and medicine history. **Deactivate** blocks the account, cancels upcoming appointments and keeps the records. You can **Reactivate** it later.

---

## 2. Put it online (about 30–45 minutes)

### Step 1: Create the database (TiDB Cloud)
1. Go to **https://tidbcloud.com** and sign up (you can use Google).
2. Create a cluster and choose **Starter** (free). For the region, pick **AWS Singapore (ap-southeast-1)**.
3. When it's ready, click **Connect**. Click **Generate Password** and **copy it now**, because it's only shown once. Note these values:
   - **Host** (looks like `gateway01.ap-southeast-1.prod.aws.tidbcloud.com`)
   - **Port:** `4000`
   - **User** (looks like `2AbCdEf1234.root`)
   - **Password**
4. Open the cluster's **SQL Editor**:
   - Run `CREATE DATABASE b509hc;`
   - Select the `b509hc` database (or run `USE b509hc;`).
   - Open `database/schema.sql` in Notepad++, copy **everything**, paste it into the editor and **run all**.
   - Check that 10 tables were created: users, patients, appointments, notifications, medicines, restock_logs, doctors, dispensations, files and sessions.

### Step 2: Make a Gmail App Password (for the approval email)
1. Use the Gmail account the health center will send from (e.g. `b509healthcenter@gmail.com`).
2. Go to **https://myaccount.google.com** > **Security** and turn on **2-Step Verification**.
3. Search for **App passwords** in the account settings. Create one named `B509 website`.
4. Copy the **16-letter password** (remove the spaces).

> You can skip this for now. Without it, verified patients just see an in-app notification instead of an email.

### Step 3: Upload the code to GitHub
1. Go to **https://github.com** and click **New repository**.
   - Name: `b509hc`
   - Choose **Private**.
   - Click **Create**.
2. On the empty repository page, click **uploading an existing file**.
3. Unzip `b509hc.zip` and drag **everything inside the `b509hc` folder** into the page.
   - Drag the contents, not the folder itself, so `vercel.json` sits at the top level.
   - Include `.gitignore` and `.vercelignore`. On Windows, turn on *View > Hidden items* to see them.
4. Click **Commit changes**.

### Step 4: Deploy on Vercel
1. Go to **https://vercel.com** and **Sign up with GitHub** (Hobby plan, free).
2. Click **Add New… > Project** and **Import** your `b509hc` repository. If you don't see it, click *Adjust GitHub App Permissions* and allow it.
3. **Framework Preset:** `Other`. Leave the Build and Output settings empty.
4. Open **Environment Variables** and add these:

| Name | Value |
|---|---|
| `APP_ENV` | `production` |
| `DB_HOST` | TiDB host |
| `DB_PORT` | `4000` |
| `DB_NAME` | `b509hc` |
| `DB_USER` | TiDB user (with the `.root` part) |
| `DB_PASS` | TiDB password |
| `DB_SSL` | `1` |
| `GOOGLE_CLIENT_ID` | your `....apps.googleusercontent.com` ID |
| `SMTP_USER` | the Gmail address from Step 2 |
| `SMTP_PASS` | the 16-letter App Password |
| `CLINIC_EMAIL` *(optional)* | shown on the Privacy Policy |
| `CLINIC_PHONE` *(optional)* | shown on the Privacy Policy |

5. Click **Deploy** and wait about a minute. You'll get an address like `https://b509hc.vercel.app`.
6. Go to **Settings > Environment Variables** and add `APP_URL` = `https://b509hc.vercel.app` (your real address, no slash at the end). This is used for the link in the approval email.
7. Go to **Deployments**, click the newest one, then **⋯ > Redeploy**. Environment variable changes only apply after a redeploy.

### Step 5: Allow your new address in Google Sign-In
1. Go to https://console.cloud.google.com > **APIs & Services > Credentials** and open your OAuth client.
2. Under **Authorized JavaScript origins**, add `https://b509hc.vercel.app` (your real address).
3. Save, then wait about 5 minutes.

### Step 6: Test
1. Open your site and log in as staff with `staff@b509hc.local` / `Staff@509`. **Change the password right away.**
2. On another browser or in incognito, sign in with Google as a patient. Fill in the registration and upload an ID.
3. As staff, go to **Patient profiles**, open the patient and click **Verify**. The patient should get the email.
4. As the patient, book an appointment.
5. As staff, open the appointment on the calendar. On the appointment day, click **Mark as arrived**, then add the **Medicine given**.

### Updating the site later
Edit your files, then on GitHub use **Add file > Upload files** to upload the changed files again (same folders). Vercel redeploys by itself in about a minute.

---

## 3. Run it on your computer (XAMPP), optional
1. Copy the folder to `C:\xampp\htdocs\b509hc`, then start **Apache** and **MySQL**.
2. In phpMyAdmin, create a database named `b509hc`, select it, and **Import** `database/schema.sql`.
3. Open http://localhost/b509hc. You don't need to edit `config.php`; the defaults are `root` with no password.
4. Use the **Dev Login** box to test patients. It only appears on localhost.

To test exactly like Vercel, run `php -S localhost:8000 api/index.php` inside the folder and open http://localhost:8000.

---

## 4. Folder structure
```
b509hc/
├── vercel.json             Vercel settings (PHP runtime, Singapore region, routes)
├── api/index.php           Vercel router (all pages go through here)
├── api/php.ini             upload size limits
├── config.php              settings, read from Environment Variables (no passwords inside)
├── index.php, login.php, auth_google.php, staff_login.php, logout.php, privacy.php
├── file.php                shows uploaded files (from the database) to the owner and staff
├── ajax/slots.php          free time slots (JSON)
├── patient/                book, bookings, reschedule, profile (registration), dependent, notifications
├── staff/
│   ├── index.php           Appointments calendar + Arrived / Cancel + Medicine given
│   ├── history.php         Appointment history
│   ├── inventory.php       Medicine stock + Given today
│   ├── medicine.php        History of one medicine (who received it)
│   ├── dispensed.php       Printable "medicine given" report per day
│   ├── patients.php        Patient profiles list
│   ├── patient.php         One patient: view, verify, ask to fix, deactivate
│   ├── doctors.php         Doctors list (admins)
│   ├── manage.php          Staff accounts (admins)
│   └── password.php
├── includes/               database, sessions, uploads, email, validation, header/footer
├── assets/                 css, js (app, calendar, address, book, inventory, dispense), images
└── database/schema.sql
```

---

## 5. Why some limits exist
- **2 MB per file:** Vercel accepts about 4.5 MB per request. Phone photos are shrunk automatically before upload, so this is rarely a problem. Large PDFs over 2 MB need to be compressed first.
- **First page load after a quiet period takes 1–3 seconds:** Vercel "wakes up" PHP. After that, pages are fast.
- **Missed appointments:** there are no cron jobs, so upcoming appointments from past dates are marked **Missed** whenever any page loads.

---

## 6. Common tasks
- **Add staff:** **Staff accounts** page (administrators).
- **Add or hide doctors:** **Doctors** page (administrators).
- **Clinic hours or slot length:** `CLINIC_OPEN`, `CLINIC_CLOSE`, `SLOT_MINUTES`, `BOOKING_DAYS_AHEAD` in `config.php`.
- **Reasons, services, priorities:** the lists at the top of `includes/validation.php`. If you change services, visit types or priorities, update the matching `ENUM` in the database too.
- **Deactivation reasons:** the list at the top of `staff/patient.php`.
- **SMS when staff cancels:** optional and paid (Semaphore). Add `SMS_API_KEY` as an Environment Variable.

---

## 7. How the validations work (useful for your defense)

Every rule is checked **twice**:

1. **Frontend (JavaScript, `assets/js/app.js`)**: fields have `data-validate="required|name|max:50"` attributes. Errors show under each field right away, and the booking wizard won't go to the next step until the current step is valid.
2. **Backend (PHP, `includes/validation.php`)**: the same rules run again on the server. Someone can turn off JavaScript or send a fake request, so the server never trusts the browser.

| Field | Rule |
|---|---|
| Names | letters (with Ñ and accents), spaces, `. ' -`, 2–50 characters; middle name and suffix optional |
| Suffix, Sex, Visit type, Service, Priority, Relationship, Cancel reason | must be one of the fixed choices |
| Birth date | real date, not in the future, at most 120 years ago |
| Mobile | `09` + 9 digits (11 digits) |
| Address | region/province/city/barangay required; zone letters/numbers only; street 3–150 characters |
| Booking date | not in the past, not a **Sunday**, within 60 days |
| Time slot | must be a **booking** slot (walk-in slots are rejected), free, and not already passed |
| Priority | *Senior Citizen* requires age 60+; *Pregnant* requires a female patient |
| PhilHealth / YAKAP | 12 digits, saved as `00-000000000-0`; can't be used by two patients; required for the account holder, optional for dependents |
| Uploads | real file type checked from the file contents (JPG/PNG/PDF), max 2 MB, photos shrunk in the browser first, saved in the database with random names, viewable only by the owner and staff |
| Medicine given | medicine must be from the list, quantity 1–100,000, doctor required; stock is only reduced if there is enough (all rows saved together or none) |
| Double booking | one patient can't have two upcoming bookings on the same day; a database lock plus a unique index stop two people from grabbing the same slot at the same moment |
| Restock | whole positive number only (1–100,000), then a confirmation modal before saving |

**Security measures:** PDO prepared statements (SQL injection), `htmlspecialchars` on every output (XSS), CSRF token on every form, `password_hash` / `password_verify` for staff, a lockout after 5 failed staff logins, session ID regenerated at login, httponly/samesite cookies, only real pages can be opened through the Vercel router (`config.php`, `includes/` and `database/` are never served), and logins are stored in the database.

---

## 8. Troubleshooting

| Problem | Fix |
|---|---|
| "The system is temporarily unavailable" | Check the `DB_` variables in Vercel, then **Redeploy**. `DB_SSL` must be `1` and `DB_PORT` must be `4000`. To see the exact error, open **Vercel > project > Logs**. |
| 404 on every page | Make sure `vercel.json` and the `api` folder are at the top level of the repository, not inside another folder. |
| Google button: "origin not allowed" | Add your exact `https://….vercel.app` address to **Authorized JavaScript origins** and wait about 5 minutes. |
| Approval email not received | Check `SMTP_USER` / `SMTP_PASS` (the App Password, not your Gmail password) and look in the Spam folder. Then **Redeploy**. |
| "Your session expired" right after login | Make sure the `sessions` table exists (re-run `schema.sql` in a fresh database). |
| Upload says "too large" | The limit is 2 MB per file. Compress the PDF, or upload a photo instead. |

> **Data privacy:** this system stores patient health information covered by the Data Privacy Act of 2012 (RA 10173). Free hosting is fine for a capstone demo. For real use by the health center, get the barangay's approval for where patient data is stored.
