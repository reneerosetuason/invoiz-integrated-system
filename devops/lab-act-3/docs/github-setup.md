# GitHub setup and registry verification

Status: local verification is complete; feature-branch commit/push and unmerged
PR verification are authorized. Live results will be reported after execution.
No merge, version tag, package publication or deployment is authorized.

## Repository and approval boundaries

The configured Git remote is
`https://github.com/reneerosetuason/invoiz-integrated-system.git`.
Keep the existing repository and visibility; do not recreate it as SIS or make
private business source public for the classroom example. Work remains on
`feature/ci-cd-implementation`. Review the exact diff before the authorized
feature-branch commit/push. Do not merge into main for this activity without
separate explicit approval.

After approval to publish source, selectively stage only the lab files and
README, commit locally, and push the feature branch. Opening a PR against main
triggers CI, including the preserved independent Playwright workflow. A feature
branch push alone does not match the new workflow's push filter.

## Demonstration secret

Repository → Settings → Secrets and variables → Actions → New repository secret.
Name: `DEMO_API_KEY`. Value: `classroom-demo-12345` (the document's dummy value).
Never substitute an actual API credential. Do not store even the demo value in
a tracked env file. Photograph only the secret NAME and existence, not its value.

The workflow tests whether the environment value is nonempty and prints only
success/missing status. It does not echo the value. Same-repository PRs, pushes,
and manual runs require the secret. Fork PRs cannot receive repository secrets;
the workflow skips only that verification and continues code checks without
privileged token access. It never uses `pull_request_target` to run PR code.

## GHCR configuration

Enable Actions as allowed by repository/organization policy. Allow the workflow
to use `GITHUB_TOKEN` for package publication; only the Docker job declares
`packages: write`. No PAT is used by the workflow. Existing package permissions
must permit this repository's Actions access. The package source label links it
to the repository. Package visibility may be private initially: explicitly check
the intended visibility instead of making it public automatically.

The image name is normalized to lowercase:
`ghcr.io/reneerosetuason/invoiz-integrated-system`.

| Event/reference | CI | Publishing | Tags |
| --- | --- | --- | --- |
| Push main | Yes | Only after successful CI | `main`, `sha-<short commit>` |
| PR to main | Yes | Never | None |
| Push v1.0.0 | Yes | Only after successful CI | `v1.0.0`, `1.0.0`, `sha-<short commit>` |
| Manual main run | Yes | Only after successful CI | Main/commit tags |
| Manual feature run | Yes | No | None |

Non-semantic `v*` tags trigger CI but the publish job rejects tags that do not
match `vMAJOR.MINOR.PATCH`. No `latest` tag is generated. Version tags must point
to reviewed commits; no existing tag is overwritten. This setup does not enforce
tag ancestry; protect tags/releases in GitHub and restrict who can create them.

The selected suite now passes on PHP 8.4 and PHP 8.5: 44 tests, 277 assertions,
zero failures/errors. Keep all tests gating. DEMO_API_KEY existence has been
verified without reading its value. Do not mark the live pipeline successful
until its actual PR execution is inspected. Image publication remains unauthorized.

## Verify after an authorized publication

1. Record the successful CI run and subsequent publish job URL.
2. In the repository/profile Packages page, verify the package, expected tags,
   source repository, and digest.
3. The workflow inspects the manifest by the digest returned by the push.
4. Pull and run that same digest locally when Docker is installed.

```powershell
docker pull ghcr.io/reneerosetuason/invoiz-integrated-system:main
docker buildx imagetools inspect ghcr.io/reneerosetuason/invoiz-integrated-system:main
# Replace <verified-digest> with the digest from the completed publish job:
docker pull ghcr.io/reneerosetuason/invoiz-integrated-system@sha256:<verified-digest>
docker run -d --name invoiz-lab-published --env-file devops/lab-act-3/.runtime/container.env -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -p 127.0.0.1:8086:80 ghcr.io/reneerosetuason/invoiz-integrated-system@sha256:<verified-digest>
Invoke-WebRequest http://127.0.0.1:8086/up
docker exec invoiz-lab-published cat /usr/local/share/invoiz-lab-version.txt
docker stop invoiz-lab-published
docker rm invoiz-lab-published
```

The SQLite override here supports a boot/health demonstration only, with no
persistent marketplace database. Use the independent Compose lab environment
for any separately approved synthetic database demo. Never use a host database.
For a private package, authenticate Docker locally with an appropriately scoped
read-only package credential using `--password-stdin`; do not paste credentials
in commands or screenshots. Local authentication is separate from the workflow's
built-in token. Never claim a pull or running published image until verified.

References: [GitHub image publication](https://docs.github.com/en/actions/tutorials/publish-packages/publish-docker-images),
[Secrets](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets),
[GHCR](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry).
