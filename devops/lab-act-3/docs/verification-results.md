# Local verification record

Date: 15 November 2026 (Asia/Shanghai).
Branch: `feature/ci-cd-implementation`.
Base commit: `b0e380817c5f2acab226d3b3f86f062a9d289aca`.
Current local result: **44 tests passed, 0 failures, 0 errors, 277 assertions**
under PHP **8.4.27** and **8.5.9**. Feature-branch commit/push and an unmerged PR
are authorized for GitHub Actions verification. Remote results will be reported
after execution; no merge, version tag, image publication or deployment is authorized.

## Files created

- `.github/workflows/ci-cd.yml`
- `Dockerfile`
- `.dockerignore`
- `devops/lab-act-3/.gitignore`
- `devops/lab-act-3/README.md`
- `devops/lab-act-3/phpunit.ci.xml`
- `devops/lab-act-3/scripts/lint-php.php`
- `devops/lab-act-3/scripts/test-bootstrap.php`
- `devops/lab-act-3/scripts/validate-workflow.mjs`
- `devops/lab-act-3/scripts/Test-Docker.ps1`
- `devops/lab-act-3/challenges/LabImageVersionTest.php`
- `devops/lab-act-3/docker/apache.conf`
- `devops/lab-act-3/docker/compose.yaml`
- `devops/lab-act-3/docker/runtime.env.example`
- `devops/lab-act-3/docker/lab-version.txt`
- `devops/lab-act-3/docs/github-setup.md`
- `devops/lab-act-3/docs/challenges.md`
- `devops/lab-act-3/docs/lab-report-draft.md`
- `devops/lab-act-3/docs/verification-results.md`
- `devops/lab-act-3/evidence/README.md`

Ignored generated files: `.runtime/build/`, `.runtime/cache-*/`, PHPUnit JUnit
XML/output, and local challenge output. Test storage/views are in OS temporary
`invoiz-lab-ci-*` directories. No generated assets replace `public/build`.

## Files modified

Existing tracked files changed: root `README.md` (added lab section) and the
three approved fixture/test files: `SharedBackendTest.php`,
`BuyerWebShoppingTest.php`, and `SellerProductShippingDimensionsTest.php`.
Existing controllers/models/routes/migrations/Blade/Flutter/configuration,
dependency manifests/lockfiles, `run.txt` and `playwright.yml` are unchanged.

Docker follow-up changes within the new lab files: default PHP extension
compilation to one job; set Docker and CI PHP to 8.4 to satisfy the existing
locked packages; add a resource-limited isolated smoke-test script; update
README/lab guide/report with actual local Docker results. Twenty files have
been created in total for the lab. Only README.md was a pre-existing tracked
file modified before the separately approved three-file test corrections.

## Approved test corrections and latest verification

Only the three approved test files were changed during the fixture-fix phase:

- SharedBackendTest uses the designated global Logistics Admin identity and
  valid account flags; model authorization and all seven role checks remain active.
- BuyerWebShoppingTest creates the variant after the original base-cart additions,
  retaining base/variant separation assertions. A new test verifies automatic
  selection of an available active variant, repeated additions, exclusion of a
  base line, and separation of an explicitly selected second variant. The button
  check verifies an enabled Add to Cart control wired to the current handler,
  together with View Cart after an item is added.
- SellerProductShippingDimensionsTest adds nullable decimal(12,2) packaging_cost
  to its isolated SQLite fixture, matching the existing migration without running
  it. Creation/update checks require HTTP 302, the expected redirect and success
  message; all weight, dimension and persisted-data checks remain.

| Runtime | Selection | Passed | Failures | Errors | Assertions |
| --- | --- | ---: | ---: | ---: | ---: |
| Host PHP 8.5.9 | Five previously problematic cases | 5 | 0 | 0 | 59 |
| Host PHP 8.5.9 | Complete selected CI suite | 44 | 0 | 0 | 277 |
| Docker PHP 8.4.27 | Five previously problematic cases | 5 | 0 | 0 | 59 |
| Docker PHP 8.4.27 | Complete selected CI suite | 44 | 0 | 0 | 277 |

All three files passed syntax checks under both versions. The suite increased
from 43 to 44 tests because of the new automatic-variant test; no test was skipped,
deleted or disabled. PHP 8.4 verification used one 384 MiB / one-CPU container
at a time, networking disabled, source mounted read-only and caches in tmpfs.
The containers were automatically removed. No MySQL connection was used.

Diff review found 47 insertions and 7 deletions across the three test files.
Before/after checksums matched for 1,649 other tracked/nonignored files during
that phase. No application functionality, dependency, database, tunnel or
hosting change was made. Nonfatal low-stock fixture warnings about
seller_notifications.title and a checkout rejection log remain visible;
PHPUnit still exits successfully. These unrelated messages were not hidden.

GitHub preflight: repository push access verified; DEMO_API_KEY existence
verified through the API without retrieving or logging its value. Main still
matched the base commit when checked. Actual PR workflow outcomes are pending.

## Docker follow-up — actually executed

Docker CLI was found at
`%LOCALAPPDATA%/Programs/DockerDesktop/resources/bin/docker.exe`; the existing
terminal had an older PATH. Only the command process's PATH was extended.
`docker --version` and `docker info` succeeded: Docker client/server 29.9.3,
Linux engine, 8 available CPUs, 2,977,603,584 bytes of engine memory. Docker's
named-pipe access needed tool sandbox elevation; access then succeeded.

- `hello-world` ran successfully with 64 MiB, 0.25 CPU and network disabled.
- Web stage built with Node 22, then PHP/vendor stage, then the final image:
  sequential builds avoided overlapping Node builds and PHP compilation.
- The initial PHP 8.3 build FAILED at Composer's platform verification:
  locked Symfony 8.1 packages require PHP >=8.4.1. No dependency was upgraded or
  downgraded. Changing only the new image and CI runtime to PHP 8.4 resolved it.
- Final `docker build -t invoiz-lab:local .` SUCCEEDED, with locked production
  dependencies and compiled Vite assets. Runtime PHP is **8.4.27**.
- Local image ID:
  `sha256:fe1a262e34ee414280a078019f3c78d988e4a66535ae300981da0d9d8f842402`.
  Reported image size: **817,245,335 bytes**. This is local image evidence,
  not a GHCR publication or registry digest verification.
- `Test-Docker.ps1` ran one container on **127.0.0.1:8085**, capped at **256 MiB,
  one CPU, 128 PIDs**, using an ephemeral key, no host mounts and no volumes.
- Host HTTP `/up`: **200**. Docker HEALTHCHECK: **healthy**.
- Actual PDO connection confirmed **SQLite :memory:**. Required extensions,
  Vite manifest and the lab version file passed verification.
- Host `.env`, auth.json, .git, downloads, Flutter web builds, lab runtime
  directory and database dumps were absent from the checked image paths.
- The script stopped and removed its own lab container in `finally`. The image
  and build cache remain locally for reuse. No pruning or engine reset occurred.
- No existing MySQL connection, migration, database reset or MySQL container
  was used. Existing host port 8000 and tunnel configuration were untouched.

Raw execution logs are ignored under `.runtime/`: `docker-web-build.txt`,
`docker-php-build.txt` (failed PHP 8.3), `docker-php84-build.txt`,
`docker-final-build.txt`, `docker-smoke-test.txt`, and
`phpunit-docker-followup.xml` / `.txt`. Do not commit generated runtime output
without reviewing it. Capture genuine screenshots from these results if needed.

## Executed checks

| Check | Actual result |
| --- | --- |
| PHP syntax checker | Follow-up full scan PASSED: 706 files, zero failures (initial scan: 705) |
| PHPUnit isolated final suite | PASSED: 44 tests / 277 assertions, zero failures/errors under PHP 8.4.27 and 8.5.9 |
| Lab artifact deliberate failure | Expected failure: 1 test / 1 assertion, exit 1 |
| Lab artifact recovery | Passed: 1 test / 1 assertion, exit 0 |
| Vite production assets | Passed using existing dependencies; lab-only output directory; Vite 5.4.21 |
| Docker Vite build | Passed inside the Node 22 build stage |
| Local final Docker image | Passed with PHP 8.4.27 after correcting the PHP 8.3 platform mismatch |
| Isolated container smoke test | Passed: host HTTP 200, healthy status, SQLite memory and runtime/content checks; container removed |
| Workflow YAML / policy validator | Passed parsing, unique-key checks and trigger/permission/dependency/PR/secret assertions |
| Compose configuration | Passed YAML parsing AND Docker Compose v5.7.0 config --no-env-resolution --quiet, exit 0; no services started |
| Composer manifest validation | Passed with existing warning: Sanctum uses unbounded `*` constraint |
| Git whitespace/scope review | `git diff --check` passed; existing source/operational paths unchanged |

Latest suite detail:

| Class | Passed | Failed | Errors |
| --- | ---: | ---: | ---: |
| LabImageVersionTest | 1 | 0 | 0 |
| PickupHubRecommendationsTest | 9 | 0 | 0 |
| SharedBackendTest | 8 | 0 | 0 |
| BuyerWebShoppingTest | 23 | 0 | 0 |
| SellerProductShippingDimensionsTest | 3 | 0 | 0 |

Historical failures (resolved by the approved test-only corrections above):

1. `SharedBackendTest::test_every_role_authenticates_against_the_same_users_table`:
   AuthorizationException — only the designated account may receive the global
   Logistics Admin role (`app/Models/User.php:131`).
2. `BuyerWebShoppingTest::test_add_to_cart_repeats_and_keeps_variations_separate`:
   expected two cart_items entries; found one.
3. `BuyerWebShoppingTest::test_product_keeps_add_button_after_first_cart_addition`:
   rendered HTML does not contain the old `onclick="openOpt()"` expectation.
4. `SellerProductShippingDimensionsTest::test_seller_can_create_product_with_weight_in_kg_and_dimensions_in_cm`:
   HTTP 500; SQLite fixture products table lacks `packaging_cost`.
5. `SellerProductShippingDimensionsTest::test_seller_can_update_weight_and_dimensions`:
   expected product name `Kitchen Blender Pro`, actual `Kitchen Blender`.

The original failed run was 43 tests / 215 assertions: 38 passed, 4 failures,
1 error. Read-only source/history investigation identified invalid identity
fixtures, outdated behavior/markup expectations and a missing fixture column.
The approved fixes correct these fixtures and strengthen assertions; application
behavior is unchanged. The original failures did not prove production failures.

During initial setup the new bootstrap produced 42 pre-assertion errors due to
Laravel treating Windows drive-qualified cache paths as relative. Corrected only
the new bootstrap to use repository-relative isolated cache paths, then reran.
The intermediate fixture run was 42 tests / 214 assertions, with the same four
failures and one error. Subsequent runs included the lab artifact test and then
the new automatic-variant test, producing the current 44-test suite.

## Unexecuted or skipped checks

- Docker build/run, image health, Apache/PHP container compatibility, extension
  install and Composer production install inside Docker were initially pending;
  these checks have now PASSED in the follow-up above.
- Optional Compose two-container runtime, persistent volumes and MySQL-backed
  business flows: deliberately not started to minimize RAM and database scope.
  Compose configuration validation passed without resolving a runtime env file
  or starting services, using only the placeholder example for interpolation.
- actionlint: executable absent. YAML/policy validation is narrower than GitHub's
  complete schema/expression validation.
- Actual PR Actions execution: authorized, pending execution. DEMO_API_KEY
  existence is now verified; its value was not retrieved or displayed.
- GHCR publication, manifest/pull and updated image: not executed; no publication authorized.
- PR automation, version-tag remote challenge and remote recovery: procedures only.
- MySQL/full PHPUnit suite: deliberately not run; forbidden existing database
  access and migration/seed prerequisites do not fit the isolated fixture scope.
- Playwright and Flutter suites: unchanged/outside selected Laravel lab target;
  no claim of browser/mobile regression coverage.
- Live website behavior, tunnel/domain/hosting verification: not exercised or changed.
- GitHub-hosted CI execution remains pending. Local Linux Docker build/runtime
  now passed using PHP 8.4.27 and Node 22; host PHPUnit uses PHP 8.5.9.

## Docker configuration review and risks

Static review plus actual SQLite smoke verification: staged web/Composer builds, PHP 8.4 Apache, required common PHP
extensions, public-only document root, writable runtime storage, production
dependencies, no automatic migrations, externally supplied APP_KEY/database
settings, no local env or storage copy, source allowlist, /up health check,
optional private Compose DB with independent volumes. Geographic lookup JSON is
included because existing pickup recommendations use it. Local Docker success
is verified; no Actions or GHCR success is claimed.

- Selected tests now pass locally; live GitHub Actions results are still pending.
- GitHub Actions and optional Compose/MySQL runtime may expose additional issues.
- An empty container database supports boot/health checks, not a complete
  marketplace demo. Synthetic schema/data preparation is separate reviewed work.
- Health checks do not establish payment, escrow or database correctness.
- Mobile web builds/APKs and host uploads are excluded from image packaging.
- Application dependencies were not upgraded/audited; existing package risks
  and the unbounded Sanctum constraint remain unchanged.
- Docker's Composer reported that composer.lock is not up to date with
  composer.json; installation still succeeded on PHP 8.4. This existing warning
  remains unresolved because dependency/lockfile changes are not authorized.
- Actions and base image tags are version-pinned but not immutable SHA/digests;
  further hardening can pin reviewed digests without application changes.
- Version-tag ancestry is not enforced by the workflow; protect release tags.
- Same-repository PRs require the demo secret; fork PRs skip only that check.
- Package access/visibility and repository policies can prevent publication.

Final Docker process check: `docker ps` listed no running containers after
verification. Workflow YAML/policy validation, Composer validation (existing
Sanctum warning only), documentation link checks and Git scope checks passed.

## Remaining manual requirements

1. Approved fixture/test corrections and local PHP 8.4/8.5 verification are
   COMPLETE. Stop and report any unexpected remote workflow failure before fixes.
2. Local Docker installation, build and isolated smoke checks are COMPLETE;
   capture the corresponding genuine evidence screenshots for submission.
3. DEMO_API_KEY existence is verified. Capture its name/existence only for evidence.
4. Feature-branch commit/push and an unmerged PR are now authorized. Observe both
   workflows and confirm Docker publication is skipped. No main merge is authorized.
5. After successful CI and separate approval, execute the reviewed version-tag
   and GHCR publication procedures, pull by digest, and demonstrate republication.
6. Complete screenshots, title-page details, contribution table, individual
   guide-question answers and personal reflections. Confirm Module 14/Act 3 label.
