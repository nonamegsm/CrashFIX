# CrashFix: Yii1 → Yii2 Migration Workflow

**Last codebase audit:** 2026-04-23 (this document was refreshed against the `yii2-port` tree).

## Project Overview

**CrashFix** is a crash report collection and management server (Windows minidumps / CrashRpt-style). It handles crash report upload, processing, grouping, bug tracking, debug symbol management, project/user management, and a background daemon for report processing.

---

## Codebase Audit Summary

### Current State (approximate counts)

| Area | Yii2 (this repo) | Notes |
|------|------------------|--------|
| **Web controllers** | 12 | Bug, CrashGroup, CrashReport, DebugInfo, ExtraFiles, Install, Mail, Project, SerialsInfo, Site, User, UserGroup |
| **Console** | Poll, Mail (+ template `Hello`) | `php yii poll/run` drives daemon parity via `PollService` |
| **Models & forms** | 45 `.php` files under `models/` | Includes ActiveRecord, `*Search`, `*Form`, traits (`CrashreportPollTrait`, `DebuginfoPollTrait`) |
| **Views** | 77+ `.php` files under `views/` | Kebab-case paths (`crash-report`, `debug-info`, …) |
| **Migrations** | 15 | Under `migrations/`; installer also runs them from the web UI |
| **Components** | 10 | Daemon, Storage, LegacyStorage, BatchImporter, WebUser, MiscHelpers, Stats, PollService, UserParamsIni, (+ `config/storage.php` bridge) |

Legacy reference (`legacy/`): Yii1 app remains for diff and copy; not required at runtime for Yii2.

### What's DONE (high level)

- **Core CRUD:** Bug, CrashGroup, CrashReport, DebugInfo, Mail, User, UserGroup, Project.
- **Additional areas:** Extra Files (collections ZIP), Serials Info (admin grid + view), full **web installer** with **new DB** vs **existing Yii1 DB** path, **legacy file storage** via `user_params.ini` + `LegacyStorage`, **Poll / daemon tick** (`PollService` + traits for XML import).
- **Auth:** `IdentityInterface` on `User`, RBAC `DbManager` with legacy table names.
- **Password flows:** Login, reset password, **recover password** (`RecoverPasswordForm` + `views/site/recoverPassword.php`).
- **Site:** Failed items + bulk retry/delete, daemon admin + runtime stats, **static pages** `site/page` → `views/site/pages/*.php`.
- **Crash reports:** Tabbed view with `_viewSummary`, `_viewFiles`, `_viewCustomProps`, `_viewVideos`, `_viewScreenshots`, `_viewThreads`, `_viewModules`, `_upload`, `_reportList`, **`_search`** (index filters).
- **Bugs:** `view.php` + history via **`views/site/_bugChange.php`**; index uses `BugSearch` + inline filter form (no separate `_search` partial).
- **Debug info:** `uploadFile.php`, **`views/debug-info/_upload.php`**, format/DWARF columns where migrated.
- **Projects:** **`actionAdmin`** + `views/project/admin.php` + `ProjectSearch`.
- **Layouts:** AdminLTE3-based main layout; install layout.
- **Deployment docs:** `docs/deployment/*` including Option C updated for installer storage.

### What's PARTIAL

| Item | Notes |
|------|--------|
| **`SiteController::actionContact`** | `views/site/contact.php` and `ContactForm` exist; **no `actionContact`** wired in `SiteController` (and no access rule). Wire or remove the orphan view. |
| **`CrashReportForm` (dedicated model)** | Upload path uses **`Crashreport`** AR + `UploadedFile`; legacy had a separate form model — optional refactor for parity only. |
| **Extracted `_search` partials** | Crash **report** has `_search.php`. **Bug** / **crash-group** use **inline** GET forms on `index.php` instead of a shared `_search` partial — behaviour OK, structure differs from Yii1. |
| **Project `views/project/_view.php` / `_search.php`** | **Not present**; `view.php` / `index.php` are self-contained. Optional cleanup for consistency only. |
| **User group `views/user-group/_view.php` / `_search.php`** | **Not present**; `view.php` / `index.php` hold the UI. |
| **Automated tests / CI** | Codeception scaffolding exists; coverage and “every action” functional tests are **not** complete. |
| **Cross-cutting widget audit (Phase 3.18–3.21)** | Most views use Yii2 widgets; no guarantee **every** legacy corner is converted — spot-check when touching a module. |

### What's MISSING (still worth tracking)

1. **`actionContact` + access rules** (or delete unused `contact.php`).
2. **Optional:** dedicated **`CrashReportForm`** mirroring legacy naming.
3. **Test suite completion** (model rules, uploads, RBAC, installer paths).
4. **Production hardening** (Phase 7 items: strip debug tools, prod config checklist — see below).

---

## Team Workflow

### Phase 0: Foundation & Setup (Week 1)

> **Goal:** Ensure the existing Yii2 app runs correctly, set up dev workflow

#### Tasks

- [x] **0.1** Git `.gitignore` excludes `vendor/`, `runtime/`, `web/assets/`, `config/user_params.ini`, `config/installed.txt` (verify locally if you add paths).
- [ ] **0.2** Verify `composer install` works cleanly on a fresh clone.
- [ ] **0.3** Run installer **fresh** and **existing Yii1** paths on a throwaway DB; confirm migrations + `LegacyStorage` when applicable.
- [ ] **0.4** Smoke-test main controllers (logged-in and guest where relevant); log gaps in issues, not only here.
- [x] **0.5** Yii2 migrations live under `migrations/`; installer runs the same set via `InstallController::runPendingMigrations`.
- [ ] **0.6** Optional seed / fixture DB for demos.
- [ ] **0.7** Codeception: expand beyond minimal coverage.

**Deliverable:** Working dev environment; repeatable install.

---

### Phase 1: Model Layer Completion (Week 2)

> **Goal:** ActiveRecord + forms + search aligned with legacy behaviour

#### Tasks

- [x] **1.1** `RecoverPasswordForm` present and used from `SiteController`.
- [ ] **1.2** Optional: port **`CrashReportForm`** or document that **`Crashreport`** upload is the supported path.
- [ ] **1.3** Ongoing: diff each model vs `legacy/protected/models/` when fixing bugs (rules, relations, labels, save hooks).
- [x] **1.4** Search models: `CrashreportSearch`, `CrashgroupSearch`, `BugSearch`, `DebuginfoSearch`, `ProjectSearch`, `UsergroupSearch`, `UserSearch`, `ExtrafilesSearch`, `SerialsinfoSearch`, …
- [ ] **1.5** RBAC name parity: re-verify `gperm_*` / `pperm_*` when adding actions.

**Deliverable:** Models trustworthy for production data.

---

### Phase 2: Controller Actions (Week 3)

> **Goal:** Behaviour parity with legacy where it matters

#### Tasks

- [x] **2.1** `SiteController::actionRecoverPassword` — implemented (email via mailer).
- [x] **2.2** `ProjectController::actionAdmin` — grid + `ProjectSearch`.
- [ ] **2.3** Access rules: audit when adding endpoints (Extra Files, Serials Info, Failed, etc.).
- [ ] **2.4** Request handling audit (GET/POST) — ongoing.
- [ ] **2.5** Legacy base `Controller` filters — port any missing cross-cutting behaviour if found.
- [x] **2.6** Crash report + debug info uploads present; storage abstraction used.
- [x] **2.7** Daemon used from Site admin + Poll tick + controllers that dispatch work.

**Deliverable:** No known stub for critical user journeys except contact (see PARTIAL).

---

### Phase 3: View Layer (Weeks 4–5)

> **Goal:** UI complete and AdminLTE-consistent

#### Tasks — Crash report (HIGH)

- [x] **3.1** `_viewCustomProps.php`
- [x] **3.2** `_viewFiles.php`
- [x] **3.3** `_viewVideos.php`
- [x] **3.4** `_viewModules.php`
- [x] **3.5** `_viewScreenshots.php`
- [x] **3.6** `_viewThreads.php`
- [x] **3.7** `_search.php` (index filter card)

#### Bug (HIGH)

- [x] **3.8** Bug detail in `bug/view.php` (no separate `_view` partial — acceptable).
- [~] **3.9** Search/filter **inline** on `bug/index.php` (not a `_search` partial).
- [x] **3.10** `views/site/_bugChange.php`

#### Project (MEDIUM)

- [x] **3.11** `project/admin.php`
- [~] **3.12** `_view` / `_search` partials — **not used**; main views suffice.

#### Crash group (MEDIUM)

- [~] **3.13** Filters **inline** on `crash-group/index.php` / `view.php`.

#### Debug info (MEDIUM)

- [x] **3.14** `debug-info/_upload.php` (+ `uploadFile.php` flow)

#### User group (LOW)

- [~] **3.15** No `_view` / `_search` partials; `view.php` / `index.php` complete.

#### Site (HIGH)

- [x] **3.16** `recoverPassword.php`
- [x] **3.17** `_bugChange.php`
- [x] **3.18a** Static pages: `site/page`, `views/site/pages/*`

#### Cross-cutting

- [~] **3.18–3.21** Ongoing when editing views; no open “all files must be rewritten” task.

**Deliverable:** Primary user flows render; see PARTIAL for contact page wiring.

---

### Phase 4: Components & Infrastructure (Week 5)

#### Tasks

- [ ] **4.1–4.8** Periodic audit: Daemon, BatchImporter, Mailer, Storage/LegacyStorage, DbSession/DbCache, WebUser.

**Deliverable:** Ops confidence on each integration.

---

### Phase 5: Database & Migrations (Week 6)

#### Tasks

- [x] **5.1** Table creation / evolution in `migrations/`.
- [x] **5.2–5.3** RBAC + lookup seeds in migration set (as per repo).
- [ ] **5.4** Optional dedicated **data** migration scripts for unusual prod dumps.
- [x] **5.5** `tbl_` prefix via `user_params.ini` / `db.php`.
- [ ] **5.6–5.7** Extra FKs/indexes only if profiling shows need.

**Deliverable:** `php yii migrate` + installer adopt-mode for Yii1 DBs.

---

### Phase 6: Testing & QA (Weeks 7–8)

#### Tasks

- [ ] **6.1–6.9** As needed before production cutover.

---

### Phase 7: Cleanup & Launch (Week 8)

#### Tasks

- [ ] **7.1** Remove stray debug scripts from repo root if any appear.
- [ ] **7.2** README / deployment kept in sync with installer + storage options.
- [ ] **7.3** Keep or archive `legacy/` per team policy (reference is still useful).
- [ ] **7.4** Production `web.php`: `YII_DEBUG` off, secure cookie key, no Gii.
- [ ] **7.5–7.7** Logging, monitoring, final side-by-side checklist.

---

## Team Role Assignments

(Unchanged — still a reasonable split.)

### Role 1: Backend Lead (Models & Controllers)
**Owns:** Phases 1, 2, 5  
**Focus:** AR, Search models, permissions, migrations, Poll/daemon behaviour.

### Role 2: Frontend Lead (Views & UI)
**Owns:** Phase 3  
**Focus:** AdminLTE consistency, accessibility, remaining contact page wiring.

### Role 3: Infrastructure & DevOps
**Owns:** Phases 0, 4, 7  
**Focus:** Installer, `LegacyStorage`, deployment docs, production config.

### Role 4: QA & Testing
**Owns:** Phase 6  

---

## Key Migration Patterns (Yii1 → Yii2 Reference)

| Yii1 Pattern | Yii2 Equivalent |
|--------------|-----------------|
| `Yii::app()` | `Yii::$app` |
| `CActiveRecord` | `yii\db\ActiveRecord` |
| `CActiveForm` | `yii\widgets\ActiveForm` |
| `CHtml::link()` | `Html::a()` |
| `CHtml::encode()` | `Html::encode()` |
| `CGridView` | `yii\grid\GridView` |
| `CListView` | `yii\widgets\ListView` |
| `CDetailView` | `yii\widgets\DetailView` |
| `$this->redirect()` | `return $this->redirect()` |
| `$model->attributes = $_POST['Model']` | `$model->load(Yii::$app->request->post())` |
| `CDbCriteria` | `yii\db\Query` / `ActiveQuery` |
| `CDbAuthManager` | `yii\rbac\DbManager` |
| `Yii::app()->user->setFlash()` | `Yii::$app->session->setFlash()` |
| `CUploadedFile::getInstance()` | `UploadedFile::getInstance()` |
| `accessRules()` | `behaviors()` + `AccessControl` |
| `CViewAction` static pages | `SiteController::actionPage` + `views/site/pages/` |

---

## File Naming Convention Changes

| Yii1 (legacy) | Yii2 (modern) |
|---------------|---------------|
| `views/crashReport/` | `views/crash-report/` |
| `views/debugInfo/` | `views/debug-info/` |
| `views/userGroup/` | `views/user-group/` |
| `views/crashGroup/` | `views/crash-group/` |

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| Daemon protocol / poll tick | HIGH | Exercise `php yii poll/run` with live `crashfixd`; watch `tbl_operation`. |
| Legacy password hashes | HIGH | `User` model keeps legacy salt + hash behaviour. |
| Installer adopt-mode marks migration applied | MEDIUM | Backup DB first; review logs for “adopted” steps; fix drift manually if needed. |
| `LegacyStorage` path wrong | MEDIUM | Installer validates `crashReports` / `debugInfo`; double-check `storage_base_path`. |
| RBAC mismatch on new action | MEDIUM | Add explicit `AccessControl` rules per action. |

---

## Priority Order (updated)

1. **Close real gaps:** wire **`actionContact`** or remove orphan view; any security/access review for new modules.
2. **Phase 6** — tests for installer, upload, Poll, RBAC before prod.
3. **Phase 4 / 7** — production config, logging, daemon connectivity monitoring.
4. **Cosmetic** — extract `_search` partials for bug/crash-group if the team wants file symmetry with Yii1.
