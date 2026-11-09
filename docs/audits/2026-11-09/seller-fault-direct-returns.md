# Seller-fault physical returns: full direct refund

Buyer and seller agree to a full refund without an admin responsibility decision. The seller pays the new reverse-delivery budget separately into platform escrow and sends the buyer's refund directly to their verified receiving account after physical return receipt.

For a single returned order of PHP 4,599 merchandise plus PHP 50 original paid shipping:

| Payment | Amount | Recipient / purpose |
| --- | ---: | --- |
| Full customer refund | PHP 4,649 | Seller sends directly to buyer; buyer confirms actual receipt |
| New reverse delivery | PHP 160 when this is the configured route total | Seller deposits to platform; admin releases completed services through normal remittances |
| Seller compensation / refund deductions | PHP 0 | Seller bears original and new costs |

Return fees still use Commission settings and the actual route/quantity. They are not hardcoded to PHP 160. Original paid shipping is allocated only to the returned quantity and seller, capped against other accepted returns; waived shipping is not reimbursed again. Item value remains the existing eligible paid value after voucher allocation.

## Buttons and responsibilities

1. **Seller: Send resolution proposal.** Select Seller responsibility and Return and refund. The system includes original paid shipping automatically and records the seller's agreement. The buyer can accept, decline or suggest changes before funding; revised proposals require fresh acceptance.
2. **Buyer: Accept agreement / Save refund account.** Review the full refund and physical return. Share bank/e-wallet receiving details with this seller after mutual agreement.
3. **Seller: Submit delivery deposit receipt.** Pay only the displayed new delivery budget to the platform account. The submitted receipt remains pending bank verification; collection authorization follows the same rules as the existing buyer-fault flow.
4. **Seller: Schedule return pickup.** Existing online rider eligibility, collection, hub intake, inter-hub transfer, receiving hub and final delivery functions remain in place. The seller confirms actual physical receipt through the existing Return center action.
5. **Seller: Record direct refund sent.** Send the exact full displayed refund to the buyer's verified account and attach its actual reference and receipt. This never deposits the buyer refund into escrow or records an admin customer-refund approval.
6. **Buyer: View refund receipt / Confirm refund received.** A receipt alone does not complete the refund. Only buyer confirmation books the refund once. The existing payment/parcel issue conversation pauses disputed customer steps until the reporting party resolves it.
7. **Admin: Manage Payments.** Review and verify the actual delivery deposit, then release only completed service allocations. Recipients confirm their shares in normal remittances. Admin cannot send or confirm this customer refund on their behalf.

Original completed order earnings remain recorded. External seller refunds are distinguished from platform cash so they do not reduce original escrow twice. The existing buyer-fault deduction calculation, keep-item full/partial refunds and already funded legacy refund sources remain intact. No schema change was needed.

## Current Return #9

The only current seller-fault physical case was audited and switched from the older unfunded platform-refund policy. Its unchanged agreed PHP 4,649 refund, PHP 160 delivery budget, proposal version and both acceptances were retained. Its existing delivery receipt remains **submitted**, with no verification or refund fabricated. Collection is authorized and awaits the normal seller scheduling step. A private maintenance backup was made before the policy change. No paid or completed case was converted.

## Validation

- Settlement/custody/seller-visibility regression selection: **153 tests passed, 1,209 assertions**.
- Final seller-fault selection after funding-preview cleanup: **8 passed, 65 assertions**. Covers full refund, automatic service fees, no admin customer approval, direct-refund authorization, physical receipt, buyer confirmation, original earnings, normal recipient acceptance, account privacy, multi-seller shipping and free shipping.
- Buyer app: **15 focused tests passed** across seller-fault, buyer-fault and keep-item full/partial refunds. Targeted analysis found no issues.
- Browser: buyer, seller and admin views checked at **320, 390, 768, 1280 and 1920 px**, with no horizontal overflow, no deducted seller-fault costs and no admin customer-refund controls. Seller desktop screenshot inspected.
- PHP syntax, Blade compilation and whitespace checks passed.
- Buyer APK **1.0.9 (build 10)** contains the new policy and full-refund instructions. Published using signature continuity, increasing build number and private installer backups. Rider installer preserved.
- The public landing page advertises the new version; the served APK matches the manifest SHA256. The connected TECNO KI7 was updated in place, with installed version **1.0.9 / 10** confirmed and application data retained. No real refund or payout was performed during verification.

UI instructions: [Return/refund guide](../../flows/return-refund-ui-guide.html#seller-fault).
