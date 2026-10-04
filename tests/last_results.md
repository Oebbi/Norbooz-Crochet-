# Norbooz Crochet test results

Run: 2026-10-04 22:44 against http://127.0.0.1:8101/norbooz_crochet

Result: **147 / 147 passed**

| ID | Area | Test | Result |
|---|---|---|---|
| P0 | Public pages | Home page loads (200) | PASS |
| P1 | Public pages | Shop page loads (200) | PASS |
| P2 | Public pages | Product detail page loads (200) | PASS |
| P3 | Public pages | Custom orders page loads (200) | PASS |
| P4 | Public pages | About page loads (200) | PASS |
| P5 | Public pages | Privacy page loads (200) | PASS |
| P6 | Public pages | Contact page loads (200) | PASS |
| P7 | Public pages | Cart page loads (200) | PASS |
| P-spec-all | Public pages | Every active product has colour, theme, and L x W x H details | PASS |
| P-spec | Public pages | Product page shows 5+ guidance and product dimensions | PASS |
| P-about | Public pages | Product description is presented as About this item bullets | PASS |
| P-filter | Public pages | Category filter and sort return products | PASS |
| P-empty | Public pages | Search with no match shows the empty state | PASS |
| P-404 | Public pages | Unknown product returns 404 | PASS |
| P-img | Public pages | Every product has a photo file on disk | PASS |
| P-img2 | Public pages | Product photo is served and under 400 KB | PASS |
| S1 | Security controls | Content-Security-Policy header sent | PASS |
| S2 | Security controls | X-Frame-Options DENY and nosniff headers sent | PASS |
| S3 | Security controls | Session cookie is HttpOnly and SameSite | PASS |
| S4 | Security controls | SQL injection in search is treated as text | PASS |
| S5 | Security controls | Script in search is HTML-encoded (XSS) | PASS |
| S-guest-checkout.php | Security controls | Guest is sent to login from checkout.php | PASS |
| S-guest-my_orders.php | Security controls | Guest is sent to login from my_orders.php | PASS |
| S-guest-account.php | Security controls | Guest is sent to login from account.php | PASS |
| S-guest-admin.php | Security controls | Guest is sent to login from admin.php | PASS |
| S-guest-admin_orders.php | Security controls | Guest is sent to login from admin_orders.php | PASS |
| S6 | Security controls | POST without CSRF token is rejected (403) | PASS |
| S7 | Security controls | Logout only accepts POST (405 on GET) | PASS |
| S8 | Security controls | Login ignores an external "next" address (open redirect) | PASS |
| S-file-config.php | Security controls | Direct access to config/config.php does not reveal contents | PASS |
| S-file-norbooz_crochet_db.sql | Security controls | Direct access to database/norbooz_crochet_db.sql does not reveal contents | PASS |
| S-file-mail.log | Security controls | Direct access to storage/mail.log does not reveal contents | PASS |
| A1 | Registration and login | Weak password is rejected | PASS |
| A2 | Registration and login | Valid registration signs the customer in | PASS |
| A3 | Registration and login | Password stored as bcrypt hash, not plain text | PASS |
| A4 | Registration and login | Duplicate email is rejected | PASS |
| A5 | Registration and login | Account locks after 5 wrong passwords, even with the right one | PASS |
| A6 | Registration and login | Lockout is stored server-side (new cookies do not bypass it) | PASS |
| A7 | Registration and login | Correct password works once the lockout ends | PASS |
| C1 | Cart and checkout | Items appear in the cart | PASS |
| C2 | Cart and checkout | Quantity above stock is capped to available stock | PASS |
| C3 | Cart and checkout | Checkout page shows the order summary | PASS |
| C4 | Cart and checkout | Invalid phone and missing address are rejected | PASS |
| C5 | Cart and checkout | Order is placed and customer is sent to the order page | PASS |
| C6 | Cart and checkout | Stock is reduced by the ordered quantities | PASS |
| C7 | Cart and checkout | Server calculates subtotal, postage and total correctly | PASS |
| C8 | Cart and checkout | Submitting the same checkout twice does not create a duplicate order | PASS |
| C9 | Cart and checkout | Cart is emptied after checkout | PASS |
| C10 | Cart and checkout | Customer can view their order and its history | PASS |
| C11 | Cart and checkout | Order note is HTML-encoded on output | PASS |
| C12 | Cart and checkout | Confirmation email is generated for the customer | PASS |
| X1 | Access control | Another customer cannot open someone else's order (IDOR) | PASS |
| X2 | Access control | Customer cannot open the admin panel (403) | PASS |
| X3 | Access control | Another customer cannot cancel someone else's order | PASS |
| K1 | Customer cancellation | Pickup order has no delivery fee | PASS |
| K2 | Customer cancellation | Customer can cancel a pending order | PASS |
| K3 | Customer cancellation | Cancelled items are returned to stock | PASS |
| D1 | Administrator | Administrator can log in and reach the dashboard | PASS |
| D2 | Administrator | Dashboard loads | PASS |
| D-admin_orders.php | Administrator | Admin page admin_orders.php loads | PASS |
| D-admin_products.php | Administrator | Admin page admin_products.php loads | PASS |
| D-admin_requests.php | Administrator | Admin page admin_requests.php loads | PASS |
| D-admin_customers.php | Administrator | Admin page admin_customers.php loads | PASS |
| D-admin_order.php?id=4 | Administrator | Admin page admin_order.php?id=4 loads | PASS |
| D-admin_product_edit.php?id=1 | Administrator | Admin page admin_product_edit.php?id=1 loads | PASS |
| D3 | Administrator | Orders export as CSV | PASS |
| D4 | Administrator | Admin moves an order Pending > In progress > Ready > Completed | PASS |
| D5 | Administrator | Invalid transition (Completed > Pending) is refused | PASS |
| D6 | Administrator | Every status change is recorded in the audit history | PASS |
| D7 | Administrator | Customer is emailed about status changes | PASS |
| D8 | Administrator | Admin can re-open a cancelled order, which reserves stock again | PASS |
| D9 | Administrator | Admin cancellation returns stock | PASS |
| D10 | Administrator | Admin adds a product with an uploaded photo | PASS |
| D10-spec | Administrator | Admin saves product specifications | PASS |
| D10-public-spec | Administrator | Customer can read saved product specifications and description | PASS |
| D10-about | Administrator | Admin-entered description appears as a customer-facing bullet | PASS |
| D11 | Administrator | Uploaded photo is resized to 1200px and a thumbnail is created | PASS |
| D12 | Administrator | A PHP file disguised as an image is rejected | PASS |
| D13 | Administrator | Invalid product data is rejected with messages | PASS |
| D14 | Administrator | A product that was never ordered can be deleted | PASS |
| D15 | Administrator | A product that appears in orders cannot be deleted | PASS |
| R1 | Custom requests | Customer sends a custom request with a photo | PASS |
| R2 | Custom requests | Customer can see their own inspiration photo | PASS |
| R3 | Custom requests | Other customers cannot see the photo (private storage) | PASS |
| R4 | Custom requests | Customer sees the quote and reply under My orders | PASS |
| R5 | Custom requests | Invalid custom request is rejected with messages | PASS |
| U1 | CRUD: update and delete | Customer sees Edit/Withdraw for a new custom request | PASS |
| U2 | CRUD: update and delete | Customer can update their own new custom request | PASS |
| U3 | CRUD: update and delete | Invalid edit is rejected and nothing changes | PASS |
| U4 | CRUD: update and delete | Another customer cannot open or edit the request (owner check) | PASS |
| U5 | CRUD: update and delete | Customer cannot edit once the maker is reviewing it | PASS |
| U6 | CRUD: update and delete | Customer cannot withdraw a request that is under review | PASS |
| U7 | CRUD: update and delete | Admin cannot delete a request that is still in progress | PASS |
| U8 | CRUD: update and delete | Admin can delete a declined request | PASS |
| U9 | CRUD: update and delete | Customer can withdraw (delete) their own new request | PASS |
| U10 | CRUD: update and delete | Admin can update contact and delivery details on an open order | PASS |
| U11 | CRUD: update and delete | Invalid order details are rejected | PASS |
| U12 | CRUD: update and delete | An open order cannot be deleted | PASS |
| U13 | CRUD: update and delete | A customer cannot use the admin order actions | PASS |
| U14 | CRUD: update and delete | A cancelled order can no longer be edited | PASS |
| U15 | CRUD: update and delete | Admin can delete a cancelled order (items cascade) | PASS |
| U16 | CRUD: update and delete | Admin cannot delete a customer with an open request | PASS |
| U17 | CRUD: update and delete | Admin can delete a finished customer (personal details removed) | PASS |
| U18 | CRUD: update and delete | A customer cannot open the admin customer list | PASS |
| Y1 | Online payments | Checkout offers PayID/bank transfer and, on localhost, the payment simulator | PASS |
| Y2 | Online payments | Security policy lets the checkout form hand over to PayPal and nowhere else | PASS |
| Y3 | Online payments | An unknown payment method is rejected and no order is created | PASS |
| Y4 | Online payments | Online checkout creates an unpaid order and sends the customer to the payment page | PASS |
| Y5 | Online payments | Stock is reserved while payment is pending | PASS |
| Y6 | Online payments | Payment page shows the amount and states that no money is taken | PASS |
| Y7 | Online payments | Another customer cannot open someone else's payment page | PASS |
| Y8 | Online payments | The shop cannot start work on an order that is not paid | PASS |
| Y9 | Online payments | A forged payment approval (no CSRF token) is rejected | PASS |
| Y10 | Online payments | Unpaid order page offers Pay now and Cancel | PASS |
| Y11 | Online payments | Approving the payment marks the order paid with a timestamp | PASS |
| Y12 | Online payments | Customer and shop are emailed that payment was received | PASS |
| Y13 | Online payments | A paid order cannot be paid twice (replay is ignored) | PASS |
| Y14 | Online payments | A customer cannot cancel an order that has been paid online | PASS |
| Y15 | Online payments | The shop cannot cancel a paid online order without recording a refund | PASS |
| Y16 | Online payments | Once paid, the shop can move the order to In Progress | PASS |
| Y17 | Online payments | A refund must be confirmed before it is recorded | PASS |
| Y18 | Online payments | Recording a refund cancels the order, marks it refunded and returns the stock | PASS |
| Y19 | Online payments | A customer cannot use the admin payment actions | PASS |
| Y20 | Online payments | Cancelling at the payment page cancels the order and releases the stock | PASS |
| Y21 | Online payments | A customer can cancel their own abandoned (unpaid) online order | PASS |
| Y22 | Online payments | The shop can cancel an unpaid online order | PASS |
| Y23 | Online payments | A cancelled online order cannot be re-opened without payment | PASS |
| Y24 | Online payments | Unpaid online orders expire automatically and their stock returns | PASS |
| Y25 | Online payments | A recent unpaid order is not expired early | PASS |
| Y26 | Online payments | The PayPal cancel link does nothing without its one-time token (CSRF) | PASS |
| Y27 | Online payments | A guessed cancel token is rejected | PASS |
| Y28 | Online payments | A PayPal return with the wrong reference does not mark the order paid | PASS |
| Y29 | Online payments | Another customer cannot complete someone else's payment | PASS |
| Y30 | Online payments | Payment webhook only accepts POST | PASS |
| Y31 | Online payments | Webhook for an unknown provider is rejected | PASS |
| Y32 | Online payments | A forged (unsigned) PayPal webhook cannot mark an order paid | PASS |
| Y33 | Online payments | Unpaid PayPal order links back to PayPal to finish paying | PASS |
| Y34 | Online payments | Data export includes how each order was paid | PASS |
| Z1 | Password reset and privacy | Reset request shows the same message for any email | PASS |
| Z2 | Password reset and privacy | Unknown email gets the same message (no account discovery) | PASS |
| Z3 | Password reset and privacy | Emailed reset link is absolute and opens the reset form | PASS |
| Z4 | Password reset and privacy | New password works after reset | PASS |
| Z5 | Password reset and privacy | Reset link cannot be used twice | PASS |
| Z6 | Password reset and privacy | Customer can download their data (APP 12) without the password hash | PASS |
| Z7 | Password reset and privacy | Account with an open custom request cannot be deleted yet | PASS |
| Z8 | Password reset and privacy | Deleting an account removes personal details but keeps order totals (APP 11.2) | PASS |
| Z9 | Password reset and privacy | Deleted account can no longer log in | PASS |
