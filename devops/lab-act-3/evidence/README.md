# Manual evidence checklist

Use descriptive filenames here after sanitizing screenshots. Do not include
environment contents, tokens, customer records, payment proofs, keys, or passwords.

| Filename | Capture required |
| --- | --- |
| 01-repository.png | Existing INVOIZ structure and implementation branch |
| 02-local-checks.png | Actual lint/test/build output, including existing failures |
| 03-ci-success.png | Successful complete CI run; currently pending |
| 04-ci-failure.png | Controlled failed lab test and skipped dependent Docker job |
| 05-test-recovery.png | Corrected lab test; identify other existing CI failures |
| 06-pull-request.png | PR checks and Docker job skipped |
| 07-secret-name.png | DEMO_API_KEY name/existence only, never its value |
| 08-local-container.png | Docker build, container status, /up response |
| 09-ghcr.png | Actual GHCR image name, tags and digest |
| 10-published-container.png | Pull by digest, /up health and image-version file |
| 11-version-tag.png | v1.0.0 workflow and associated release image tags |
| 12-republished-image.png | Changed lab version file and new published digest |

Record run URLs, commit SHAs, image digests, date/time and observer in the report.
Do not fabricate green screenshots. A draft checklist is not execution evidence.

Each member must personally complete the 15 guide-question answers, contribution
statement and individual reflection in the report. Suggested reflection prompts:
What did you implement? What actually failed? How did you protect existing data?
What did you learn about CI versus delivery versus deployment? What would you
improve? Do not submit the group's sample answers as individual experience.
