# Findora AI: Smart College Lost and Found Management System

Findora AI is a responsive, modern web application designed for college campuses to report lost belongings, log found items, search records in real time, and claim recovered items. Built specifically to fulfill the requirements of the **Full-Stack Web Technology Laboratory Syllabus**, the system uses standard web technologies without external heavyweight frontend frameworks.

---

## 1. Project Objectives & Laboratory Concepts

The application serves as both a practical campus utility and a reference laboratory project demonstrating all 9 core web development concepts:

1. **Basic HTML Tags and Elements**: Headings (`<h1>`–`<h6>`), paragraphs, lists (`<ol>`, `<ul>`), hyperlinks, images, tables, semantic landmarks (`<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<aside>`, `<footer>`).
2. **CSS Styling and Keyframe Animations**: CSS3 variables, transitions, hover transforms, loading spinners, and multi-stage `@keyframes` animations.
3. **Advanced HTML5 Forms**: Specialized input types (`date`, `time`, `number`, `email`), `contenteditable="true"`, `spellcheck="true"`, placeholders, required constraints, pattern matching, and datalists.
4. **Drag-and-Drop & Browser Storage**: HTML5 Drag and Drop API (`dragstart`, `dragover`, `dragleave`, `drop`) coupled with `sessionStorage` (temporary session bookmarks) and `localStorage` (persistent user preferences).
5. **CSS Stylesheets Architecture**: Direct demonstration of Inline CSS, Internal CSS (`<style>` block), and External CSS (`style.css`), incorporating CSS Grid, Flexbox, media queries, pseudo-classes (`:hover`, `:focus-visible`), and pseudo-elements (`::before`, `::after`).
6. **JavaScript Event Handling**: Real-time handling of at least 5 primary event types: `click`, `input`, `change`, `submit`, and `mouseover`.
7. **PHP and MySQL Database Integration**: Forms submitting to PHP, robust server-side validation, and MySQL interaction using **PHP Data Objects (PDO)** with prepared statements for SQL injection prevention.
8. **Dynamic PHP Data Retrieval**: Dynamic record retrieval, relational SQL queries (`JOIN`), and output escaping (`htmlspecialchars()`) to defend against Cross-Site Scripting (XSS).
9. **jQuery Selectors & Asynchronous AJAX**: jQuery DOM selection, dynamic element injection, and asynchronous `$.ajax()` requests for real-time search, filtering, and report submissions without page reload.
10. **AI-Assisted Matching**: A transparent, weighted keyword and attribute matching algorithm (category, color, location, date proximity, and text similarity) that runs offline, with optional server-side external AI API integration (e.g. OpenAI/Gemini) through PHP.

---

## 2. Technology Stack

- **Frontend**: HTML5 Semantic Markup, CSS3 (Modern Flexbox & CSS Grid), Vanilla JavaScript (ES6+).
- **DOM & Asynchronous Interactivity**: jQuery 3.7.1, AJAX (`$.ajax()`).
- **Browser Storage**: `window.localStorage` and `window.sessionStorage`.
- **Backend**: PHP 8.x (Strict Types, PDO MySQL Driver, Session Security, CSRF Tokens).
- **Database**: MariaDB / MySQL (InnoDB Engine, Foreign Keys, UTF-8 Unicode Collation).
- **Development Environment**: XAMPP on Windows.

---

## 3. Complete Project Folder Structure

```
c:\xampp\htdocs\college-lost-found/
├── .env                         # Server environment configuration (DB credentials & optional AI key)
├── .env.example                 # Template for environment configuration
├── .htaccess                    # Web server protection rules
├── connection-test.php          # Quick database sanity test script
├── index.php                    # Public homepage with live stats, hero search & top match
├── login.php                    # Student and staff login portal
├── register.php                 # Student account registration with password hashing
├── logout.php                   # Secure session termination handler
├── lost-item.php                # Report a lost item (Advanced HTML5 Form & AI trigger)
├── found-item.php               # Report a found item (Private verification traits & AI trigger)
├── browse-items.php             # Browse board with AJAX search, filters & Drag-Drop workbench
├── item-details.php             # Comprehensive item view, secret details shield & claim submission
├── my-claims.php                # Student dashboard tracking submitted claims & office release status
├── admin/
│   ├── login.php                # Dedicated administrator authentication gateway
│   ├── dashboard.php            # Admin KPI dashboard, pending claim alerts & AI sweep utility
│   ├── manage-items.php         # Full items table, status modifier & record deletion
│   └── manage-claims.php        # Side-by-side claim verification & approval/rejection console
├── api/
│   ├── create-item.php          # Asynchronous endpoint to report items via AJAX
│   ├── list-items.php           # Asynchronous search and filtering endpoint returning JSON & cards
│   ├── create-claim.php         # Asynchronous endpoint to submit claims
│   ├── update-claim.php         # Admin endpoint to approve/reject claims
│   └── ai-match.php             # Endpoint for on-demand AI item comparison & suggestions
├── config/
│   ├── config.php               # Application constants and .env loader
│   └── database.php             # Shared singleton PDO MySQL connection
├── includes/
│   ├── auth.php                 # Session management, CSRF tokens, auth guards & flash messages
│   ├── helpers.php              # Formatting, sanitization, image uploader & card renderer
│   ├── ai.php                   # AI weighted-similarity algorithm & external AI API client
│   ├── header.php               # Common responsive college navigation bar
│   └── footer.php               # Common footer with links & script inclusions
├── assets/
│   ├── css/
│   │   └── style.css            # External CSS design system (tokens, layout, cards, animations)
│   ├── js/
│   │   ├── script.js            # Global UI scripts (mobile menu, number count-up, flash alerts)
│   │   ├── drag-drop.js         # HTML5 Drag and Drop API & localStorage/sessionStorage engine
│   │   └── ajax.js              # jQuery AJAX search, dynamic DOM highlighter & claim handlers
│   └── images/                  # Static design assets and icons
├── uploads/                     # Uploaded item photographs (PHP script execution disabled by .htaccess)
├── sql/
│   └── database.sql             # MySQL database schema and sample campus test data
└── README.md                    # Project documentation, lab mapping and test checklist
```

---

## 4. Database Architecture

The system utilizes a relational MySQL database named `college_lost_found`. All passwords are saved exclusively as bcrypt hashes (`PASSWORD_DEFAULT`), and foreign keys enforce referential integrity across users, items, and claims.

### Table Schema Summary

| Table | Primary Key | Foreign Keys | Key Attributes & Description |
|---|---|---|---|
| `users` | `id` | - | `name`, `email` (unique), `password_hash`, `role` (`student`, `staff`, `admin`), `created_at` |
| `items` | `id` | `reporter_id` &rarr; `users(id)` | `item_type` (`lost`, `found`), `name`, `category`, `description`, `color`, `location`, `item_date`, `item_time`, `estimated_value`, `image_path`, `additional_notes`, `private_details`, `contact_info`, `status` (`lost`, `found`, `claim_pending`, `returned`) |
| `claims` | `id` | `item_id` &rarr; `items(id)`, `claimant_id` &rarr; `users(id)` | `claim_description` (secret identifying traits), `status` (`pending`, `approved`, `rejected`), `admin_note`, `created_at`, `reviewed_at` |
| `match_suggestions` | `id` | `lost_item_id` &rarr; `items(id)`, `found_item_id` &rarr; `items(id)` | `score` (0–100), `explanation`, `created_at`, Unique key on `(lost_item_id, found_item_id)` |

---

## 5. XAMPP Installation and Setup Instructions

### Step 1: Start XAMPP Services
1. Launch the **XAMPP Control Panel** on Windows.
2. Click **Start** for both **Apache** and **MySQL**. Verify that both indicators turn green.

### Step 2: Place the Codebase
Ensure the project is located inside your XAMPP web root:
```
C:\xampp\htdocs\college-lost-found
```

### Step 3: Import the Database
1. Open your web browser and navigate to **`http://localhost/phpmyadmin`**.
2. Click the **Import** tab at the top.
3. Click **Browse / Choose File** and choose:
   ```
   C:\xampp\htdocs\college-lost-found\sql\database.sql
   ```
4. Click **Import** (or **Go**). The script creates the `college_lost_found` database, constructs the tables, and populates sample student accounts, items, claims, and matches.

### Step 4: Environment Configuration
Check that `.env` exists in the project root:
```ini
DB_HOST=localhost
DB_NAME=college_lost_found
DB_USER=root
DB_PASS=

# Optional external AI service (leave blank to run the built-in offline algorithm)
AI_API_KEY=
AI_API_URL=
AI_MODEL=
```

### Step 5: Test Connection
Open **`http://localhost/college-lost-found/connection-test.php`** in your browser. You should see a green success message confirming active connection with row counts.

---

## 6. Pre-Configured Test Accounts

| Account Role | Email Address | Password | Permissions & Use Case |
|---|---|---|---|
| **Campus Admin** | `admin@cit.edu.in` | `Admin@12345` | Full administrative console, verify secret details, approve/reject claims, delete items, run AI sweeps. |
| **Student 1** | `student1@cit.edu.in` | `Student@12345` | Report lost/found belongings, browse items, track submitted claims in "My Claims". |
| **Student 2** | `student2@cit.edu.in` | `Student@12345` | Finder of items, reporter of missing items, claim submissions. |

*Note: New student accounts can also be created at any time through the **Create Account** (`register.php`) page.*

---

## 7. AI Matching Engine Architecture

The AI matching engine is located in `includes/ai.php` and computes a similarity score from **0 to 100** between any lost report and found listing based on 5 weighted criteria:

1. **Category Concordance (Weight: 30)**:
   Items in different categories (e.g. Wallet vs. Electronics) receive 0 match score immediately, avoiding false positives. Same category earns 30 points.
2. **Keyword & Text Overlap (Weight: 35)**:
   Tokenizes item names and descriptions, eliminates English stop words, and computes lexical overlap using Jaccard and substring similarity.
3. **Color Compatibility (Weight: 15)**:
   Compares primary colors and searches for color keywords inside descriptions.
4. **Campus Location Proximity (Weight: 10)**:
   Rewards exact location matches and nearby campus facilities (e.g., "Library" and "IT Block Library").
5. **Date Proximity (Weight: 10)**:
   Rewards found dates that fall on the same day or within 1 to 7 days after the lost date.
6. **Transparent Explanation Generation**:
   The engine automatically writes an explanation justifying the score (e.g., *"Same category (Wallet); Matching keywords: black, wallet, leather; Matching colour (Black); Same campus location (Main Canteen); Lost and found on the exact same date."*).

### Optional External AI Integration
If an `AI_API_KEY` is provided in `.env`, the function `call_external_ai_api()` in `includes/ai.php` will dispatch an outbound cURL request to your chosen AI endpoint. The API key is strictly maintained server-side and is never sent to the browser. If the API key is empty or the external service is unreachable, the system falls back to the built-in offline algorithm.

---

## 8. Syllabus Technology Mapping

Every core web technology requirement is directly integrated into the production application:

| Technology Requirement | Implementation in Project | Primary Files |
|---|---|---|
| **Basic HTML Tags** | Semantic landmarks (`header`, `main`, `section`, `article`, `footer`), headings, lists, tables, links. | `includes/header.php`, `index.php`, `item-details.php` |
| **CSS, JS & Animation** | Keyframe animations (`@keyframes spin`, `pulse`), button hover transitions, loading spinners, and JS count-up. | `assets/css/style.css`, `assets/js/script.js`, `index.php` |
| **Advanced HTML5 Forms** | Inputs: `date`, `time`, `number`, `email`, `contenteditable="true"`, `spellcheck="true"`, `required`, `datalist`. | `lost-item.php`, `found-item.php` |
| **Drag & Drop and Storage** | HTML5 Drag & Drop (`dragstart`, `dragover`, `dragleave`, `drop`), `sessionStorage` (saved items), and `localStorage` (preferences). | `assets/js/drag-drop.js`, `browse-items.php` |
| **CSS Stylesheets** | External modern design system (`style.css`), CSS Grid, Flexbox, media queries, and pseudo-classes. | `assets/css/style.css` |
| **JS Event Handling** | Core events handled: `click`, `input`, `change`, `submit`, `mouseover`, `dragstart`, `drop`. | `assets/js/script.js`, `assets/js/ajax.js`, `assets/js/drag-drop.js` |
| **PHP & MySQL Integration** | Form handling, server-side data validation, PDO connection, prepared statements, and SQL injection defense. | `lost-item.php`, `found-item.php`, `config/database.php` |
| **PHP Data Retrieval** | Dynamic querying from MySQL, relational joins (`items` + `users`), HTML escaping (`e()`), filtering, and record detail rendering. | `browse-items.php`, `item-details.php`, `my-claims.php` |
| **jQuery and AJAX** | jQuery selectors, DOM manipulation (`.html()`, `.text()`, `.addClass()`), live debounced search, and asynchronous requests. | `assets/js/ajax.js`, `api/list-items.php`, `api/create-claim.php` |

---

## 9. Comprehensive Testing Checklist

Use this checklist during evaluation or code demonstration:

- [x] **Database Connectivity**: Verify `connection-test.php` returns status `OK` with table row counts.
- [x] **Student Authentication**:
  - Register a new student account via `register.php`.
  - Log in with `student1@cit.edu.in` / `Student@12345` via `login.php`.
  - Confirm user badge displays *"Hi, Anu"* in navbar.
- [x] **Reporting a Lost Item (`lost-item.php`)**:
  - Submit an item (e.g., *"Blue Water Bottle"*, Category: *Accessories*, Location: *Sports Complex*).
  - Verify validation prevents empty titles and past dates.
  - Confirm report appears instantly on `browse-items.php`.
- [x] **Reporting a Found Item (`found-item.php`)**:
  - Fill in public details and add a secret note in the *🔒 Secret Identifying Details* box.
  - Verify secret note is NOT visible to visitors browsing `browse-items.php` or `item-details.php`.
- [x] **Real-Time AJAX Search & Filter (`browse-items.php`)**:
  - Type *"wallet"* in the search box; watch results update live without page reload.
  - Click a quick category pill (*"Electronics"*); verify item cards filter instantly.
  - Inspect highlighted query keywords wrapped in yellow `<mark>` tags.
- [x] **Drag-and-Drop & Browser Storage (`browse-items.php`)**:
  - Drag an item card into the **★ Save for Later** drop zone.
  - Check that the saved count updates and the item appears in the saved list.
  - Refresh the browser; verify the item remains present (persisted in `sessionStorage`).
  - Drag an item into the **⚡ Instant AI Match Inspector** drop zone; observe instant match computation.
- [x] **Submitting a Claim (`item-details.php`)**:
  - Open a found item (e.g., Item #2 *Black Leather Wallet*).
  - Fill out the claim form with proof details and submit via AJAX.
  - Confirm success message appears and navigate to **My Claims** (`my-claims.php`) to see the pending status.
- [x] **Administrator Verification Portal (`admin/manage-claims.php`)**:
  - Log in as administrator at `admin/login.php` (`admin@cit.edu.in` / `Admin@12345`).
  - Open **Manage Claims**.
  - Compare the claimant's proof side-by-side with the finder's secret notes.
  - Click **Approve Claim & Mark Returned**.
  - Verify item status transitions to `Returned` in both `browse-items.php` and the claimant's `my-claims.php`.
- [x] **AI Matching Engine Sweep (`admin/dashboard.php`)**:
  - Click **⚡ Run Full AI Matching Sweep**.
  - Observe re-calculation of all open reports and update of suggestions.
