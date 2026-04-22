# CrashFix: Yii1 → Yii2 Migration Workflow

## Project Overview

**CrashFix** is a crash report collection and management server (Windows minidumps / CrashRpt-style). It handles crash report upload, processing, grouping, bug tracking, debug symbol management, project/user management, and a background daemon for report processing.

---

## Codebase Audit Summary

### Current State

| Area | Yii2 (Modern) | Yii1 (Legacy) | Status |
|------|---------------|----------------|--------|
| **Controllers** | 10 | 9 (+1 Install new) | ~85% ported |
| **Models** | 31 | 28 | ~90% ported |
| **Views** | 61 | 78 | ~70% ported |
| **Components** | 4 | 7 | ~60% ported |
| **Widgets** | 3 | 2 (portlets) | Replaced with AdminLTE |

### What's DONE

- Core CRUD controllers: Bug, CrashGroup, CrashReport, DebugInfo, Mail, User, UserGroup
- All domain models ported (renamed to Yii2 conventions)
- Authentication via `IdentityInterface` on `User` model
- RBAC via `DbManager` with legacy table names
- Daemon component for background processing
- BatchImporter component
- AdminLTE3 + Bootstrap 5 layout system
- Web installer (Yii2-only, new feature)
- Upload endpoints (external + authenticated)

### What's PARTIAL

| Item | What's Missing |
|------|---------------|
| `SiteController` | `actionRecoverPassword` is a stub, no `RecoverPasswordForm` model, no view |
| `ProjectController` | `actionAdmin` not ported, no admin/search views |
| `BugController` | Missing `_view`, `_search`, `_bugChange` partials |
| `CrashReportController` | Missing many view partials (`_viewCustomProps`, `_viewFiles`, `_viewVideos`, `_viewModules`, `_viewScreenshots`, `_viewThreads`, `_search`) |
| `CrashGroupController` | Missing search/form partials |
| `DebugInfoController` | Missing `_upload` partial |
| `UserGroupController` | Missing `_view`, `_search` partials |

### What's MISSING

1. **`RecoverPasswordForm`** model + full password recovery flow
2. **`CrashReportForm`** model (legacy upload form model)
3. **`ProjectController::actionAdmin`** + admin view
4. **`site/page`** static page action (CViewAction equivalent)
5. **`views/site/recoverPassword.php`**
6. **`views/site/_bugChange.php`**
7. Several view partials across crash-report, bug, crash-group, debug-info, user-group

---

## Team Workflow

### Phase 0: Foundation & Setup (Week 1)

> **Goal:** Ensure the existing Yii2 app runs correctly, set up dev workflow

#### Tasks

- [ ] **0.1** Set up Git repository with proper `.gitignore` (exclude `vendor/`, `runtime/`, `web/assets/`, `config/user_params.ini`, `config/installed.txt`)
- [ ] **0.2** Verify `composer install` works cleanly
- [ ] **0.3** Run the installer flow end-to-end, confirm DB schema creates correctly
- [ ] **0.4** Test all existing Yii2 controllers — document which pages render vs error
- [ ] **0.5** Set up proper Yii2 migrations (move schema from `InstallController::actionRunMigrations` to `migrations/` directory)
- [ ] **0.6** Create a test database and seed data script
- [ ] **0.7** Configure Codeception test suites

**Deliverable:** Working dev environment, CI-ready test harness

---

### Phase 1: Model Layer Completion (Week 2)

> **Goal:** Ensure all ActiveRecord models are fully ported with proper Yii2 patterns

#### Tasks

- [ ] **1.1** Port `RecoverPasswordForm` from legacy `models/RecoverPasswordForm.php`
- [ ] **1.2** Port `CrashReportForm` from legacy `models/CrashReportForm.php` (or integrate into existing upload flow)
- [ ] **1.3** Audit all 31 Yii2 models against legacy counterparts:
  - Verify `rules()` validation matches legacy
  - Verify `relations()` → Yii2 `hasOne()`/`hasMany()` are complete
  - Verify `search()` → Yii2 `SearchModel` pattern is implemented
  - Verify `attributeLabels()` match
  - Verify `beforeSave()`/`afterSave()` logic is ported
- [ ] **1.4** Create Search models where missing (used by GridView/ListView in views):
  - `CrashReportSearch`
  - `CrashGroupSearch`
  - `BugSearch`
  - `DebugInfoSearch`
  - `ProjectSearch`
  - `UserGroupSearch`
- [ ] **1.5** Verify RBAC permission names match between legacy `WebUser` checks and Yii2 `authManager`

**Deliverable:** Complete model layer with all Search models

---

### Phase 2: Controller Actions (Week 3)

> **Goal:** Port all missing controller actions

#### Tasks

- [ ] **2.1** `SiteController::actionRecoverPassword` — full implementation with email sending
- [ ] **2.2** `ProjectController::actionAdmin` — admin grid with search/filter
- [ ] **2.3** Verify all controller `access` rules match legacy permission checks
- [ ] **2.4** Audit each controller action parameter handling (Yii1 `$_GET`/`$_POST` → Yii2 `Yii::$app->request`)
- [ ] **2.5** Port any missing filter/behavior logic from legacy `Controller.php` base class
- [ ] **2.6** Verify file upload handling in `CrashReportController` and `DebugInfoController`
- [ ] **2.7** Verify daemon communication in all actions that call the Daemon component

**Deliverable:** All controller actions functional

---

### Phase 3: View Layer (Weeks 4-5)

> **Goal:** Port all missing views and partials, modernize with AdminLTE3

#### Tasks

##### Crash Report Views (Priority: HIGH)
- [ ] **3.1** Port `_viewCustomProps.php` — custom properties tab/section
- [ ] **3.2** Port `_viewFiles.php` — attached files list
- [ ] **3.3** Port `_viewVideos.php` — video attachments
- [ ] **3.4** Port `_viewModules.php` — loaded modules list
- [ ] **3.5** Port `_viewScreenshots.php` — screenshot gallery
- [ ] **3.6** Port `_viewThreads.php` — thread/stack trace display
- [ ] **3.7** Port `_search.php` — crash report search form

##### Bug Views (Priority: HIGH)
- [ ] **3.8** Port `_view.php` — bug detail partial
- [ ] **3.9** Port `_search.php` — bug search form
- [ ] **3.10** Port `_bugChange.php` — bug change history display

##### Project Views (Priority: MEDIUM)
- [ ] **3.11** Create `admin.php` — project admin grid
- [ ] **3.12** Port `_view.php` and `_search.php` partials

##### Crash Group Views (Priority: MEDIUM)
- [ ] **3.13** Port search/filter partials

##### Debug Info Views (Priority: MEDIUM)
- [ ] **3.14** Port `_upload.php` partial

##### User Group Views (Priority: LOW)
- [ ] **3.15** Port `_view.php` and `_search.php` partials

##### Site Views (Priority: HIGH)
- [ ] **3.16** Create `recoverPassword.php` view
- [ ] **3.17** Port `_bugChange.php` partial

##### Cross-cutting
- [ ] **3.18** Convert all Yii1 widget calls (`CGridView`, `CListView`, `CDetailView`, `CActiveForm`) to Yii2 equivalents (`GridView`, `ListView`, `DetailView`, `ActiveForm`)
- [ ] **3.19** Replace `CHtml::` helpers with `Html::` equivalents
- [ ] **3.20** Update all `Yii::app()` references to `Yii::$app`
- [ ] **3.21** Verify AdminLTE3 card/box layout consistency across all views

**Deliverable:** All views render correctly with AdminLTE3 styling

---

### Phase 4: Components & Infrastructure (Week 5)

> **Goal:** Ensure all supporting components work correctly

#### Tasks

- [ ] **4.1** Audit `Daemon.php` — verify socket communication, error handling, timeouts
- [ ] **4.2** Audit `BatchImporter.php` — verify import logic works with Yii2 models
- [ ] **4.3** Audit `MiscHelpers.php` — verify all helper methods are ported and used
- [ ] **4.4** Audit `WebUser.php` — verify `gperm_*`/`pperm_*` permission mapping completeness
- [ ] **4.5** Port mail functionality — verify Symfony Mailer config replaces legacy mailer
- [ ] **4.6** Verify file upload/download paths and storage logic
- [ ] **4.7** Verify session handling (Yii2 `DbSession` if legacy used `YiiSession` table)
- [ ] **4.8** Verify caching setup (legacy `cache` table → Yii2 `DbCache` or alternative)

**Deliverable:** All infrastructure components verified and working

---

### Phase 5: Database & Migrations (Week 6)

> **Goal:** Proper Yii2 migration system, data migration from legacy

#### Tasks

- [ ] **5.1** Create Yii2 migrations for all tables (extract from `InstallController`)
- [ ] **5.2** Create RBAC migration (`rbac/init` or manual migration)
- [ ] **5.3** Create seed data migration for `lookup` table
- [ ] **5.4** Write data migration scripts for any existing production data
- [ ] **5.5** Verify table prefix handling (`tbl_`) works consistently
- [ ] **5.6** Add proper foreign key constraints where legacy had none
- [ ] **5.7** Add database indexes for common query patterns

**Deliverable:** Complete migration system, data migration tested

---

### Phase 6: Testing & QA (Weeks 7-8)

> **Goal:** Full test coverage and regression testing

#### Tasks

- [ ] **6.1** Write unit tests for all model validation rules
- [ ] **6.2** Write unit tests for Search models
- [ ] **6.3** Write functional tests for each controller action
- [ ] **6.4** Write functional tests for file upload flows
- [ ] **6.5** Write functional tests for authentication/authorization
- [ ] **6.6** Write functional tests for daemon communication
- [ ] **6.7** Cross-browser testing of all views
- [ ] **6.8** Performance testing — compare with legacy app response times
- [ ] **6.9** Security audit — CSRF, XSS, SQL injection, file upload validation

**Deliverable:** Test suite with >80% coverage, all tests passing

---

### Phase 7: Cleanup & Launch (Week 8)

> **Goal:** Production-ready application

#### Tasks

- [ ] **7.1** Remove all debug/test scripts from root (`debug_save.php`, `get_project.php`, `test_upload.php`, `test_db.php`)
- [ ] **7.2** Update `README.md` with CrashFix-specific documentation
- [ ] **7.3** Remove or archive `legacy/` directory
- [ ] **7.4** Configure production `web.php` (disable debug, gii; set proper cookie key)
- [ ] **7.5** Set up error logging and monitoring
- [ ] **7.6** Create deployment documentation
- [ ] **7.7** Final side-by-side comparison with legacy app

**Deliverable:** Production deployment

---

## Team Role Assignments

### Role 1: Backend Lead (Models & Controllers)
**Owns:** Phases 1, 2, 5  
**Focus areas:**
- ActiveRecord model completion and validation
- Search model creation
- Controller action porting
- Database migration system
- RBAC and permission system

### Role 2: Frontend Lead (Views & UI)
**Owns:** Phase 3  
**Focus areas:**
- View template porting (Yii1 widgets → Yii2 widgets)
- AdminLTE3 integration and styling
- GridView/ListView/DetailView configuration
- JavaScript/jQuery interactions
- Responsive design

### Role 3: Infrastructure & DevOps
**Owns:** Phases 0, 4, 7  
**Focus areas:**
- Dev environment setup
- Daemon component
- File storage and upload handling
- Mail system
- Deployment pipeline
- Security hardening

### Role 4: QA & Testing
**Owns:** Phase 6  
**Focus areas:**
- Test suite development
- Regression testing against legacy
- Performance benchmarks
- Security audit

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
| `$this->renderPartial()` | `$this->renderPartial()` (same) |
| `CUploadedFile::getInstance()` | `UploadedFile::getInstance()` |
| `CHttpException` | `yii\web\HttpException` (or `NotFoundHttpException`, etc.) |
| `accessRules()` | `behaviors()` with `AccessControl` filter |
| `CUrlManager` rules | `urlManager` rules in config (similar syntax) |

---

## File Naming Convention Changes

| Yii1 (legacy) | Yii2 (modern) |
|---------------|---------------|
| `controllers/CrashReportController.php` | `controllers/CrashReportController.php` (same) |
| `views/crashReport/` | `views/crash-report/` (kebab-case) |
| `views/debugInfo/` | `views/debug-info/` (kebab-case) |
| `views/userGroup/` | `views/user-group/` (kebab-case) |
| `views/crashGroup/` | `views/crash-group/` (kebab-case) |

---

## Risk Register

| Risk | Impact | Mitigation |
|------|--------|------------|
| Daemon protocol incompatibility | HIGH | Test daemon communication early (Phase 0) |
| Legacy password hash incompatibility | HIGH | Already handled — Yii2 `User` model uses legacy salt+hash |
| Missing view partials cause 500 errors | MEDIUM | Audit all `render()`/`renderPartial()` calls vs existing view files |
| RBAC permission name mismatches | MEDIUM | Map all `gperm_*`/`pperm_*` checks systematically |
| File upload path differences | MEDIUM | Verify storage paths match between legacy and modern |
| jQuery/JS dependency conflicts | LOW | AdminLTE3 bundles its own jQuery; verify no conflicts |
| Data migration for production DB | HIGH | Write and test migration scripts against a copy of prod data |

---

## Priority Order (if resources are limited)

1. **Phase 1** — Model completion (blocks everything else)
2. **Phase 3 (HIGH items)** — Crash report and bug views (core functionality)
3. **Phase 2** — Controller actions (recover password, project admin)
4. **Phase 5** — Database migrations (needed for deployment)
5. **Phase 4** — Component audit (daemon, mail)
6. **Phase 3 (MEDIUM/LOW)** — Remaining view partials
7. **Phase 6** — Testing
8. **Phase 7** — Cleanup and launch
