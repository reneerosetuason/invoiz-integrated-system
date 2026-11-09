# Buyer acceptance display and installed-app update

Return #4's buyer acceptance was already saved. Its return-fee payment list was empty. The connected TECNO phone had buyer app **1.0.3 (build 4)** installed, explaining the repeated Accept button, legacy Quoted services heading and Request admin review control.

Buyer app **1.0.5 (build 6)** now:

- Hides Accept agreement whenever buyer acceptance is recorded, including historical agreements.
- Shows **Agreement accepted** separately from **Return fee unpaid: ₱160.00**.
- Keeps successful acceptance visible if the immediate refresh fails, using the server-confirmed result; failed acceptance requests do not set it.
- Explains that buyer/seller proposal agreement needs no admin approval.
- Labels the exceptional paid-case help action **Report payment or parcel issue**, while direct negotiation uses suggestions and declines.

Four agreement widget tests passed, covering normal acceptance, refresh failure, direct payment/feedback, and historical deduction display. Changed Dart files passed the analyzer. The release APK built successfully and was published with signature-continuity and version checks. A full public download from invoiz.store matched SHA-256 `f8250d27a301f8cda14d0aba281075aa991ffd65c3ee9ef5726e0abd80e3e0f7`; the public landing page showed the new version and download hash.

Updated the connected phone in place using package replacement, preserving its login. Android reported versionName 1.0.5 / versionCode 6. Read-only navigation to Return #4 confirmed Agreement accepted and Return fee unpaid, with neither Accept agreement nor Request admin review displayed. The payment button was visible further down the screen. No agreement was accepted again and no payment, refund or parcel event was submitted during verification.

Admin still verifies actual incoming platform-account transfers and records outgoing refunds/remittances. This is separate from proposal acceptance: the system has no bank callback that could safely treat an uploaded screenshot as verified payment.
