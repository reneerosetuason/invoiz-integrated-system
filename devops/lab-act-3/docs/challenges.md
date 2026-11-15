# Additional laboratory challenges

Local challenge tests operate only on the lab image version file and do not boot
Laravel. Remote steps require explicit approval and remain unexecuted. Never
change existing business code or force-push main to demonstrate a failure.

## 1. Test failure detection and recovery

The lab-only `challenges/LabImageVersionTest.php` is included in CI. Locally, a
temporary process environment override demonstrates failure without editing a
tracked file or connecting to a database:

```powershell
# Preserve an existing override, then restore it in finally:
$previousLabVersion = $env:INVOIZ_LAB_EXPECTED_VERSION
try {
    $env:INVOIZ_LAB_EXPECTED_VERSION = 'Deliberately incorrect laboratory expectation'
    php vendor/phpunit/phpunit/phpunit --no-configuration --do-not-cache-result --bootstrap vendor/autoload.php devops/lab-act-3/challenges/LabImageVersionTest.php
    # Expected exit 1: assertion failure.
} finally {
    $env:INVOIZ_LAB_EXPECTED_VERSION = $previousLabVersion
}
php vendor/phpunit/phpunit/phpunit --no-configuration --do-not-cache-result --bootstrap vendor/autoload.php devops/lab-act-3/challenges/LabImageVersionTest.php
# Expected exit 0 if no pre-existing override: recovery.
```

For GitHub evidence, after push approval, use a temporary branch based on the
implementation branch. Modify ONLY the expected version string in this lab test,
commit those exact files, and open a PR targeting main. Capture the failed test
and skipped publish job. Restore the expected string in another commit and
capture the lab test recovery. Close the PR without merging. Existing fixture
failures remain separate blockers: recovery of this lab test alone cannot make
the complete CI green while those failures remain. No such fixes are authorized.

## 2. Pull request automation

After approval, create `lab/ci-pr-demo` from the implementation branch. Change a
sentence in this laboratory documentation, push only that branch, and open a PR
to main. Verify that the new CI and existing Playwright workflows trigger and
the Docker job is skipped. Review checks before closing the unmerged PR. Do not
disable Playwright if its existing environment assumptions cause failures.

## 3. Version tagging

After all CI failures are independently resolved and release publication is
explicitly authorized, verify whether `v1.0.0` already exists locally/remotely.
Use a reviewed commit containing the workflow. Do not overwrite an existing tag.

```powershell
git tag --list v1.0.0
git ls-remote --tags origin refs/tags/v1.0.0
# Only after explicit approval, if absent:
git tag -a v1.0.0 <reviewed-commit-sha> -m 'INVOIZ CI/CD laboratory v1.0.0'
git push origin refs/tags/v1.0.0
```

This is a release operation even if the commit is on a feature branch. It starts
CI and may publish an image. It must not be used to bypass approval. Capture tag
event, CI, publish, `v1.0.0`/`1.0.0` image tags, and immutable digest evidence.
No main merge or main checkout is required for preparation.

## 4. Image modification and republication

On another temporary branch, edit ONLY `docker/lab-version.txt` to
`INVOIZ CI/CD laboratory image - Version 2.0` and update the lab-only test's
expected text to match. This adapts the SIS message-change exercise to a
container artifact without modifying INVOIZ pages or functionality.

Build a separate local tag `invoiz-lab:v2-demo`, run the health check, and inspect
the file using `docker exec <lab-container> cat /usr/local/share/invoiz-lab-version.txt`.
Record both image IDs/digests and the changed file output. For remote evidence,
obtain approval for a reviewed version tag such as `v1.0.1`; then verify updated
GHCR digest and pulled contents. Do not repoint `v1.0.0` or merge into main.

All remote challenge observations must remain labelled pending until executed.
