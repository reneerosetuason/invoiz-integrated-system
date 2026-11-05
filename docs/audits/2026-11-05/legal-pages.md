# Dedicated Terms of Service and Privacy Policy

Public routes:
- `/terms-of-service`, named `legal.terms`
- `/privacy-policy`, named `legal.privacy`

Each route renders its own document through the shared legal layout. The standalone pages do not require an account or authenticated buyer shell. Styling uses the existing local Inter/Fraunces assets, neutral surfaces, section navigation and responsive widths.

Updated registration, seller application, logistics application/staff consent and buyer-footer links. Consent form links open in a separate tab. Existing checkbox names, requirements, registration validation and account/payment behavior are unchanged. The buyer footer stylesheet is cache-busted to deliver its new policy-link styling.

The initial policy copy describes the existing COD/GCash receipt and full-deposit process, order and delivery responsibilities, consumer remedies, data categories, role-based sharing, essential browser storage/external services, privacy requests and 30-day account-deletion grace/archival behavior. Retention language reuses the existing account-deletion configuration. It does not invent a corporate identity, office address, privacy officer, licensed escrow status, fixed statutory retention period or security certification. The business operator should review and complete its organizational/contact and retention details before relying on the copy as final legal documentation.

Primary references checked while drafting:
- National Privacy Commission: https://privacy.gov.ph/data-subject-rights/
- National Privacy Commission: https://privacy.gov.ph/the-right-to-be-informed/
- National Privacy Commission, Data Privacy Act: https://privacy.gov.ph/data-privacy-act-/
- DTI consumer-remedy guidance: https://fairtrade.dti.gov.ph/faq/is-no-return-no-exchange-policy-allowed/

Verification:
- Both public policy routes, registration and seller application return HTTP 200 in the read-only check.
- Every Terms/Privacy link on those rendered pages points to its own canonical document rather than Help.
- Every policy contents anchor resolves to a section in the same document.
- Browser checks at 320, 390, 768 and 1440 px pass: headings, contents links, document cross-navigation, no horizontal overflow and no script errors. Desktop and phone screenshots were inspected.
- PHP syntax, route registration, Blade compilation and whitespace checks passed. No live form submission, database write or environment-file change was made.

Ignored verification scripts and screenshots: `.tools/check-legal-pages.php`, `.tools/check-legal-browser.mjs`, `.tools/legal-preview/`.
