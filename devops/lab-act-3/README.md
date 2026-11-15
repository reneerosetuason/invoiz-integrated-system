# LAB ACT 3 — INVOIZ CI/CD

This laboratory applies the supplied SIS example to the existing INVOIZ Laravel
11 / PHP / Blade / MySQL backend. It creates no second backend. Flutter buyer and
rider clients remain unchanged. Scope: CI, container packaging, registry delivery,
laboratory verification procedures, and documentation. No website deployment.

## Organization and requirement mapping

| Activity | Implementation / evidence |
| --- | --- |
| Repository and application | Existing root INVOIZ backend; branch `feature/ci-cd-implementation` |
| Lint / test / build | `scripts/lint-php.php`, `phpunit.ci.xml`, existing Vite build |
| GitHub Actions | `../../.github/workflows/ci-cd.yml` |
| Container | Root Dockerfile and `.dockerignore`; `docker/` supporting configuration |
| GitHub secret and GHCR | [GitHub setup](docs/github-setup.md) |
| Four challenges | [Challenge procedures](docs/challenges.md) |
| Report and questions | [Laboratory report draft](docs/lab-report-draft.md) |
| Actual local outcomes | [Verification results](docs/verification-results.md) |
| Screenshots and reflections | [Evidence checklist](evidence/README.md) |

Root Dockerfile, `.dockerignore`, and `.github/workflows/ci-cd.yml` use their normal
tool discovery paths. Supporting assets are collected here. `.runtime/` is ignored.

## Tools and safe tests

Use Git, Composer 2, PHP 8.4.1+ for the current lockfile, with mbstring, DOM/XML, PDO SQLite and usual Laravel
extensions, and Node/npm. CI and the image use PHP 8.4 and Node 22. Docker Desktop
Linux containers is optional locally. MySQL is not needed for CI fixtures.

From the repository root, after dependencies are installed:

```powershell
php devops/lab-act-3/scripts/lint-php.php
php vendor/phpunit/phpunit/phpunit -c devops/lab-act-3/phpunit.ci.xml
node devops/lab-act-3/scripts/validate-workflow.mjs
npm.cmd run build -- --outDir devops/lab-act-3/.runtime/build --emptyOutDir
```

The YAML check uses the already locked npm `yaml` package; no dependency change.
The test selection explicitly includes PickupHubRecommendationsTest,
SharedBackendTest, BuyerWebShoppingTest, and SellerProductShippingDimensionsTest.
Their fixtures create only in-memory tables; no migration suite is invoked.
The bootstrap forces connection settings before Laravel boots, overrides the
cached configuration path, isolates package/route/config caches in ignored
`.runtime/cache-*`, and redirects views/storage into OS temporary directories.
Laravel may read the local dotenv file, but its immutable loader cannot replace
the forced test values. The file is neither modified nor copied into the image.
Temporary test directories can be removed manually after the processes finish;
no automatic recursive cleanup touches application directories.

The full default or MySQL suite is deliberately not invoked: it includes tests
with different fixture requirements and migration restrictions. Passing these
selected tests is not verification of every business flow.

## Independent Docker setup — execute only when Docker is installed

### Low-RAM verification (recommended first)

Build the stages sequentially so the Node build does not overlap PHP compilation.
PHP extension compilation defaults to one job (`PHP_BUILD_JOBS=1`). Run from the
repository root with Docker Desktop's Linux engine running:

```powershell
docker --version
docker info
docker run --rm --memory=64m --cpus=0.25 --network=none hello-world
docker build --target web .
docker build --target vendor .
docker build -t invoiz-lab:local .
powershell -NoProfile -File devops/lab-act-3/scripts/Test-Docker.ps1
```

If this terminal has an older PATH, the verification script can locate the
per-user Docker Desktop installation itself. For the build commands, open a new
terminal or add Docker's `resources/bin` directory to that terminal's PATH only.
No system PATH, domain, tunnel or hosting setting needs to change.

The smoke test runs ONE 256 MiB / one-CPU container on `127.0.0.1:8085`, with a
random temporary application key, SQLite `:memory:`, no credentials from the
host, no host bind mounts, and no database volume. It checks host HTTP `/up`,
Docker health, PHP extensions, the actual SQLite PDO driver, Vite manifest, lab
version and selected host-data exclusions. It stops/removes its own container
in `finally`, retaining the image and build cache. No MySQL server is started or
contacted. It does not create marketplace schema or prove all business flows.
Use `-Port 8086` if 8085 is already occupied; the script never stops a service to
free its port. A pre-existing container with its name causes creation to fail,
and is not removed.

### Optional two-container synthetic-database setup

These commands use a dedicated project, port 8085, internal MySQL network, and
named volumes. They do not bind-mount local Laravel files or expose MySQL on a
host port. Do not point this container at an existing database.

```powershell
New-Item -ItemType Directory -Force devops/lab-act-3/.runtime
Copy-Item devops/lab-act-3/docker/runtime.env.example devops/lab-act-3/.runtime/container.env
docker build -t invoiz-lab:local .
# Generate a NEW container-only key; paste the output into container.env APP_KEY:
docker run --rm --entrypoint php invoiz-lab:local -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Edit only the ignored `container.env`: set APP_KEY and a disposable DB_PASSWORD.
Keep DB_HOST=`db` (Compose service DNS). Keep the supplied disposable
database/user names. Never substitute development credentials.

```powershell
docker compose --env-file devops/lab-act-3/.runtime/container.env -f devops/lab-act-3/docker/compose.yaml config --quiet
docker compose --env-file devops/lab-act-3/.runtime/container.env -f devops/lab-act-3/docker/compose.yaml up -d
Invoke-WebRequest http://127.0.0.1:8085/up
docker compose --env-file devops/lab-act-3/.runtime/container.env -f devops/lab-act-3/docker/compose.yaml ps
docker compose --env-file devops/lab-act-3/.runtime/container.env -f devops/lab-act-3/docker/compose.yaml exec app cat /usr/local/share/invoiz-lab-version.txt
```

Expected verification is HTTP 200 at Laravel's existing `/up` health route plus
the lab version file. This proves container boot, not shopping/payment behavior.
The independent MySQL starts EMPTY. The application image contains no real
database records or uploads. A full marketplace demo requires a separately
reviewed synthetic schema/data fixture; existing migration history is not a safe
fresh-install bootstrap and is not automatically executed. Do not copy existing
database dumps. No runtime entrypoint runs migrations or changes keys.

The image was built locally and the single-container SQLite smoke test passed;
see the verification report for the exact image and checks. The optional
two-container MySQL runtime has not been exercised. The
`/up` health check does not validate database availability or external services.
Queue sync, file sessions/cache, and log mail are lab defaults; production worker,
scheduler, mail, payment, persistent-upload and TLS deployment configuration is
outside this activity. Flutter web/APK artifacts are excluded from this image.

Stop only this lab project (volumes are retained):

```powershell
docker compose --env-file devops/lab-act-3/.runtime/container.env -f devops/lab-act-3/docker/compose.yaml down
```

Do not add `-v` unless you explicitly intend to delete disposable lab volumes.
No tunnel, domain, current hosting configuration, or running website is changed.
