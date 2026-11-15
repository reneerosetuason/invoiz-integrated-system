# LAB ACT 3 — CI/CD for INVOIZ Integrated E-Commerce System

**DRAFT — local image build and SQLite smoke test verified; remote evidence pending.**
Do not describe pending activities as successful results.

## Title page

Subject: [complete]

Laboratory Module: **14**, as specified in the uploaded report format.
Activity title: **LAB ACT 3 — CI/CD: Continuous Integration and Continuous Delivery/Deployment**.
The source document uses both labels; confirm the instructor's preferred label.

Group members: [complete]

Instructor: [complete]

Submission date: [complete]

## Introduction

Continuous Integration (CI) automates checking, testing and building changes.
Continuous Delivery prepares a verified artifact for release. Continuous
Deployment automatically releases verified changes to a running environment.
Automation makes checks repeatable, provides earlier feedback, and reduces
manual release mistakes. This activity applies the supplied SIS example to
INVOIZ's existing Laravel backend and Blade frontend. Flutter clients remain
connected to that backend and are outside this image's build scope.

## Objectives

- Configure GitHub Actions triggers, lint, isolated tests and asset builds.
- Package Laravel using Docker without secrets or development records.
- Prepare GHCR delivery gated on successful CI and trusted references.
- Demonstrate a repository secret without exposing its value.
- Demonstrate failure/recovery safely and prepare PR, tag and image challenges.
- Protect all existing functionality, databases, hosting and tunnel settings.

## Tools and technologies

| Tool | Use |
| --- | --- |
| Git / GitHub | Existing source repository and feature branch |
| GitHub Actions | Automated CI and gated registry delivery |
| PHP / Composer | Laravel runtime, locked dependencies and PHPUnit |
| Laravel 11 / Blade | Existing INVOIZ application |
| SQLite memory | Disposable, test-owned fixtures; no MySQL access |
| MySQL | Existing application technology; untouched by tests |
| Node.js / npm / Vite / Tailwind | Existing frontend asset build |
| Docker / Apache | Proposed isolated Linux application image |
| GHCR | Proposed image registry, built-in GITHUB_TOKEN authentication |
| Flutter / Dart | Existing buyer/rider clients, unchanged |

Local checks ran on Windows with PHP 8.5.9 and Node 24.15.0. CI/container
configuration targets PHP 8.4 and Node 22. The actual Linux build and runtime
verification passed (PHP 8.4.27). GitHub-hosted CI remains unexecuted.

## Procedures and implementation

1. Read the complete lab document and inspected composer.json, composer.lock,
   package.json, package-lock.json, test configurations, fixture helpers,
   application directories and existing workflow.
2. Selected the single root Laravel application. Obtained approval and created
   local branch `feature/ci-cd-implementation`; did not merge or push.
3. Added `.github/workflows/ci-cd.yml`, preserving `playwright.yml` unchanged.
   Triggers cover main pushes, PRs to main, v* tags and manual runs.
4. Added PHP syntax checking and selected four existing fixture-based test
   classes. Forced testing/SQLite memory settings and separate runtime/cache
   directories in a lab-owned bootstrap. Did not run migrations.
5. Used the existing locked npm build; generated local assets into ignored lab
   output so the running website's compiled assets were not replaced.
6. Added a multi-stage Dockerfile: Node web build, PHP extensions, Composer
   production dependencies and Apache runtime. The allowlist `.dockerignore`
   excludes host env files, uploads, SQL backups, caches and mobile artifacts.
   A standalone Compose lab environment has its own volumes/network and no
   host-published database port. No host Laravel bind mount is used.
7. Prepared GHCR login, image metadata, main/version/commit tags, publish and
   digest inspection. `needs: ci` and reference/event checks block publication
   after failed CI and from PRs. Image publication does not deploy the website.
8. Added DEMO_API_KEY nonempty verification with no value output; documented
   dummy-secret configuration and fork-PR handling.
9. Added a lab-only image-version artifact and test to demonstrate controlled
   failure and recovery locally. Prepared remote challenge procedures without
   executing pushes, tags or publication.
10. Updated README and created organized lab documentation/evidence checklist.

Source excerpts for the submission (copy actual final files):

```yaml
needs: ci
permissions:
  contents: read
  packages: write
```

```powershell
php vendor/phpunit/phpunit/phpunit -c devops/lab-act-3/phpunit.ci.xml
npm.cmd run build -- --outDir devops/lab-act-3/.runtime/build --emptyOutDir
```

Insert screenshots from [the checklist](../evidence/README.md), referencing the
actual commit, run URL and digest when available.

## Results and discussion

See [verification-results.md](verification-results.md) for the complete record.
The original local suite ran 43 tests / 215 assertions: 38 passed, 4 failed and
1 errored. After read-only investigation and explicit approval, only three test
files were corrected: valid designated-admin identity, cart scenario/automatic
variant coverage, current Add to Cart markup, and a missing packaging_cost
fixture column. Existing application behavior and dependencies were unchanged.
The latest complete suite PASSED under both PHP 8.4.27 and PHP 8.5.9:
**44 tests, 277 assertions, 0 failures, 0 errors**. The five original problem
cases also passed separately under each version: 5 tests / 59 assertions.
No tests were skipped or disabled; response and button checks were strengthened.

The controlled lab-only test failed with exit 1 under a temporary incorrect
expectation and recovered with exit 0 after restoring the process environment.
PHP syntax, Vite assets, YAML parsing/policy checks and Composer validation ran
locally. Composer reported an existing unbounded Sanctum constraint warning.

Docker build/run passed locally on the Docker Desktop Linux engine. The first
PHP 8.3 attempt failed because locked Symfony packages require PHP >=8.4.1;
updating only the new image/CI runtime to PHP 8.4 resolved that platform failure.
Application dependencies and lockfiles were unchanged. On port 8085, `/up`
returned HTTP 200; Docker reported healthy; SQLite :memory: PDO, required PHP
extensions, Vite manifest and host-data exclusions were verified. The 256 MiB /
one-CPU lab container was stopped and removed after verification. No MySQL ran.

DEMO_API_KEY existence has been verified without retrieving its value. Feature-
branch push and PR verification are authorized; actual Actions execution,
release tagging, GHCR push/pull and image republication remain pending. No successful remote run
or published image is claimed. Full marketplace behavior inside a container
requires separately reviewed synthetic schema/data; the container health route
alone cannot establish correctness of payments, escrow or order fulfillment.

## Individual contributions

Complete with actual work performed; combine roles for smaller groups.

| Member | Role | Actual contribution / evidence |
| --- | --- | --- |
| [name] | Developer / reviewer | [complete] |
| [name] | CI/CD engineer | [complete] |
| [name] | Docker engineer | [complete] |
| [name] | Tester / QA | [complete] |
| [name] | Documentation lead | [complete] |

All members should review, test and understand the full pipeline. Record
assistance/tools used according to your instructor's academic disclosure policy.

## Opening discussion questions — reference notes

1. **Push to main:** GitHub matches the trigger and starts CI. Publishing follows
   only if CI succeeds and the event/reference is allowed.
2. **actions/checkout:** places the triggering repository revision on the runner.
3. **Job versus step:** a job runs on a runner; its steps run sequentially in that
   job. Separate jobs may run concurrently unless dependencies order them.
4. **Step failure:** normally stops later success-only steps and fails its job;
   dependent jobs are skipped. `always()` evidence steps can still execute.
5. **Why automate:** consistent checks, faster feedback and reproducible builds.

## Secret discussion questions — reference notes

1. API keys should not be committed because repository access and Git history
   can expose them beyond intended users.
2. An exposed password can enable unauthorized access; revoke/rotate it and
   investigate use. Removing the visible line alone is insufficient.
3. GitHub Secrets supplies protected values to authorized workflows without
   storing them in source. Workflow authors must still avoid logging them.
4. A regular environment variable is a process configuration mechanism. A GitHub
   Secret is protected storage that can populate an environment variable; an
   environment variable is not automatically secret or masked.

## Individual guide questions — reference answers to personalize

Each student must write their own responses and connect them to actual work.
These notes are a draft aid, not a completed individual reflection.

1. **What is CI/CD and why is it important?** Automated integration checks and
   release preparation/deployment catch problems earlier and make releases repeatable.
2. **CI, delivery, deployment:** CI checks integrated changes; delivery produces
   releasable artifacts; deployment automatically updates a running environment.
   This lab prepares registry delivery and performs no website deployment.
3. **Linting:** detects syntax errors before the slower tests/build. PHP `-l`
   checks syntax, not every style or semantic issue.
4. **Tests before deployment:** failed behavior should block artifact publication
   and release before users are exposed to it.
5. **Workflow/job/step:** a workflow is the event-triggered automation definition;
   jobs group work on runners; steps perform commands/actions within jobs.
6. **on:** declares events and branch/tag filters that start the workflow.
7. **needs: ci:** establishes a successful-CI prerequisite for Docker publication.
8. **Dockerfile:** describes the runtime image's layers, dependencies, files and
   default process; the image is reproducibly built from those instructions.
9. **Image/container:** an image is the packaged filesystem/configuration;
   a container is a running instance with its own runtime state.
10. **GHCR:** stores versioned container images and digests for distribution.
11. **Secrets:** protect credentials outside tracked source and scope access to
    authorized workflows. Values must still never be echoed.
12. **Failed test:** returns a nonzero exit status, fails CI and skips dependent
   publication. A controlled lab-only failure/recovery demonstrated this gate locally.
13. **Collaboration:** PR checks give reviewers shared, repeatable feedback on
    the proposed revision before it is accepted.
14. **Deployment without human approval risks:** incomplete tests, harmful schema
    changes, wrong environment selection, leaked credentials and runtime outages.
15. **Improvements:** separately repair outdated fixtures, expand isolated role
    coverage, pin Actions/images to reviewed immutable digests, add dependency
    auditing and container vulnerability scanning, and protect release tags.

## Individual reflection — complete manually per member

Name: [complete]. Actual contribution: [complete]. Most useful learning:
[complete]. Actual failure investigated: [complete]. How data was protected:
[complete]. Improvement proposed: [complete].

## Conclusion draft

The activity added CI/CD infrastructure around INVOIZ while preserving the
existing application source and operational configuration. Local checks exposed
existing test incompatibilities; approved test-only corrections produced a
passing 44-test suite on PHP 8.4 and PHP 8.5. Controlled local failure/recovery
was demonstrated. Complete this conclusion
with genuine Actions and registry observations only after those activities
have been authorized and executed. Local Docker evidence is now recorded in
the verification report and ignored runtime logs.

## References

- Uploaded `LAB_ACT_CICD.docx`, LAB ACT 3 laboratory instructions.
- [GitHub Actions workflow syntax](https://docs.github.com/en/actions/reference/workflow-syntax-for-github-actions)
- [Publishing Docker images](https://docs.github.com/en/actions/tutorials/publish-packages/publish-docker-images)
- [GitHub Secrets](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets)
- [GHCR](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry)
- [Docker multi-stage builds](https://docs.docker.com/build/building/multi-stage/)
- [Laravel 11 testing](https://laravel.com/docs/11.x/testing)
- [PHPUnit 10 configuration](https://docs.phpunit.de/en/10.5/configuration.html)
