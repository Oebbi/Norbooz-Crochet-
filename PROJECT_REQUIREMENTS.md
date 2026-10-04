# Norbooz Crochet: Requirements and Competitor Analysis

## Project overview

Norbooz Crochet is a Canberra-based online shop for handmade crochet products. Customers can browse ready-made items, place and track orders, and request custom work. The shop owner manages the catalogue, stock, orders, customer accounts, and custom-request quotes through an administrator area.

This document describes the system represented by the current project files. It records existing behavior as functional requirements; it does not claim that this documentation-only change implements or verifies those features. Currency, delivery, privacy, and payment configuration must be confirmed before production use.

## Users and needs

| User | Goals and needs |
|---|---|
| Visitor | Discover products, understand price and availability, and find shop and policy information before registering or buying. |
| Customer | Register and securely sign in; manage personal details; order available products; choose delivery; track or cancel an eligible order; request, revise, and follow up on custom work; manage privacy choices and account data. |
| Shop administrator | Maintain products and stock; process orders and payments; respond to custom requests; find customer order history; protect customer data; monitor shop activity. |

## Functional requirements

### Product discovery

- **FR-01:** The system shall present a home page with navigation to the shop and customer-facing information pages.
- **FR-02:** The system shall list active products with their name, image, price, and availability.
- **FR-03:** The system shall allow visitors to search the catalogue by product text.
- **FR-04:** The system shall allow visitors to filter products by product family/category.
- **FR-05:** The system shall allow visitors to sort the product listing using the available sort options.
- **FR-06:** The system shall allow visitors to restrict the listing to in-stock products.
- **FR-07:** The system shall paginate product listings when the result set exceeds the page size.
- **FR-08:** The system shall show a product detail page with an About this item bullet list, price, image, and stock availability.
- **FR-43:** The system shall show each product's brand, 5+ seller age guidance, colour, theme, and product-specific L x W x H dimensions.
- **FR-09:** The system shall show related products when related catalogue items are available.
- **FR-10:** The system shall prevent inactive products from being offered for purchase in the public catalogue.

### Accounts and privacy

- **FR-11:** The system shall allow a visitor to create a customer account using the required registration details.
- **FR-12:** The system shall authenticate customers and administrators using their account credentials.
- **FR-13:** The system shall allow a signed-in user to sign out, using a state-changing POST request.
- **FR-14:** The system shall provide a password-reset flow using expiring, single-use reset tokens.
- **FR-15:** The system shall allow a customer to view and update their account details.
- **FR-16:** The system shall allow a signed-in user to change their password.
- **FR-17:** The system shall record the customer's explicit marketing opt-in choice.
- **FR-18:** The system shall let a customer download their personal data.
- **FR-19:** The system shall let a customer request account deletion; deletion shall de-identify personal details while retaining necessary transaction records.
- **FR-20:** The system shall restrict customer order and private-request information to that customer and authorized administrators.

### Cart, checkout, and orders

- **FR-21:** The system shall let a customer add an available product to their cart.
- **FR-22:** The system shall let a customer change cart quantities and remove cart items.
- **FR-23:** The system shall let a customer select pickup or postal delivery at checkout.
- **FR-24:** The system shall calculate prices, delivery fees, and order totals on the server from current product and delivery data.
- **FR-25:** The system shall prevent accidental duplicate order submission using a duplicate-submit token.
- **FR-26:** The system shall support manual payment instructions and, when configured, a hosted PayPal payment flow; on localhost it shall offer a clearly labelled payment simulator that takes no money.
- **FR-27:** The system shall update online payment state from validated provider callbacks/webhooks rather than trusting a browser return alone.
- **FR-28:** The system shall validate stock and create an order within a database transaction so concurrent checkouts cannot oversell stock.
- **FR-29:** The system shall let a customer view their order history and the status/progress of an individual order.
- **FR-30:** The system shall let a customer cancel an order while its status is pending, unless it has already been paid online.
- **FR-44:** The system shall let a customer finish paying or cancel an online order that is still unpaid, and shall cancel unpaid online orders automatically after a configurable time so reserved stock is released.
- **FR-45:** The system shall let an administrator cancel an unpaid online order and record a refund for a paid online order, with an audit-history entry and a customer email.

### Custom requests

- **FR-31:** The system shall let a signed-in customer submit a custom-work request with type, title, description, colour preferences, dimensions, quantity, and optional budget and needed-by date.
- **FR-32:** The system shall allow a customer to attach an inspiration image to a custom request, with private storage and upload validation.
- **FR-33:** The system shall let a customer edit or withdraw a request while it is new.
- **FR-34:** The system shall let a customer view the status, quote, and administrator response for their own request.
- **FR-35:** The system shall let an administrator review a request and record a response and quoted price.

### Administration

- **FR-36:** The system shall provide an administrator dashboard with operational summaries such as new orders, unanswered requests, low stock, sales, and best sellers.
- **FR-37:** The system shall let an administrator create, view, edit, hide, and delete catalogue products, including stock quantities, product photos, and specifications.
- **FR-38:** The system shall let an administrator search and filter orders and export order results as CSV.
- **FR-39:** The system shall let an administrator update order status, record status history, and send relevant customer email notifications.
- **FR-40:** The system shall let an administrator review custom-request details and uploaded images, then manage the quote/reply workflow.
- **FR-41:** The system shall let an administrator search customer accounts, view associated order history, and de-identify an account only when there is no open order or custom request.
- **FR-42:** The system shall provide installation diagnostics and a database-upgrade workflow restricted to an authorized/local context.

## CRUD and data lifecycle

| Resource | Create | Read | Update | Delete/retention behavior |
|---|---|---|---|---|
| Customer account | Customer registration | Customer sees own account; administrator can search customer records | Customer edits details/password and marketing preference | Customer or administrator deletion request de-identifies personal details. Related transaction records are retained; administrator deletion is blocked while open work exists. |
| Product | Administrator adds product | Public catalogue shows active products; administrator views catalogue | Administrator edits details, image, visibility, stock, and specifications | Administrator may delete; hiding/deactivating is available when the record should not be publicly sold. Order references are protected by database constraints. |
| Cart | Customer adds a product | Customer views current cart | Customer changes quantity | Customer removes a line or clears the cart; cart is temporary session state. |
| Order | Created by checkout after server-side validation | Customer reads own orders; administrator reads and searches orders | Administrator changes workflow status; customer may cancel while pending | No normal hard-delete workflow is specified. Order and status-history records support audit and business record retention. |
| Order item | Created with its order at checkout | Read as part of order details | No independent editing is specified after purchase; captured unit price remains stable | No independent deletion is specified; items follow order lifecycle and database constraints. |
| Custom request | Customer submits a request | Customer reads own requests; administrator reads requests and permitted uploaded image | Customer edits while new; administrator updates status, quote, and response | Customer may withdraw while new. No general hard-delete behavior is specified. Retain or remove under the applicable privacy/retention policy. |
| Order status history | Created for a status change | Customer/admin can view relevant progress history | Append-only in normal workflows | No ordinary delete operation is specified; retain for auditability. |

CRUD is not identical for every resource: purchases and audit records are intentionally retained rather than exposed to unrestricted hard deletion. The relevant delete action for user data is de-identification, subject to open-work and legal record-keeping constraints.

## W3Schools-inspired presentation and implementation guidance

The brief's W3Schools reference is treated as a request for a familiar, straightforward learning-site visual language, not as a claim that the site uses W3Schools code or branding. If applied to the interface, use:

- A clear, compact top navigation and predictable page headings.
- Restrained green as an accent for navigation and primary actions, with high-contrast neutral content surfaces.
- Readable forms, labels, tables, and status messages with consistent spacing.
- Semantic HTML, responsive CSS, accessible labels and keyboard focus, and small progressively enhanced JavaScript interactions.
- Existing project conventions and security controls; do not copy W3Schools assets, text, or branding.

The current website has not been restyled as part of this documentation deliverable.

## Competitor comparison

This comparison uses public-facing competitor information reviewed on 2026-10-01. Etsy returned HTTP 403 during page retrieval, so its summary is high-level and should be independently verified before formal submission. These are broader craft/handmade services, not exact one-to-one local-shop replacements.

| Competitor | Relevant strengths | Difference and opportunity for Norbooz Crochet |
|---|---|---|
| Etsy | Large multi-seller marketplace with broad handmade and craft discovery; buyers can compare many independent sellers and listings. | Norbooz is a single-maker shop with direct contact, local pickup/post options, and a custom-request workflow tied to the maker rather than a marketplace seller directory. |
| Ravelry | A free community site for knitters, crocheters, and fiber artists, combining craft-specific discovery with a dedicated community audience. | Norbooz focuses on buying finished handmade crochet pieces and requesting custom pieces, rather than serving primarily as a broad craft community. |
| LoveCrafts | Craft-focused retail discovery for yarn, crochet/knitting products, patterns, tutorials, and community content; offers category browsing and inspiration. | Norbooz differentiates through finished handmade inventory, owner-managed stock, order tracking, and custom quotes for one small business. |

Competitor feature pages: [Etsy crochet marketplace](https://www.etsy.com/market/crochet), [Ravelry](https://www.ravelry.com/), [LoveCrafts](https://www.lovecrafts.com/en-gb/).

## Traceability and verification

FR-01 through FR-45 are derived from the current README, database schema, and customer administration page. Verify each requirement against the named workflow before using this list as a formal acceptance-test baseline. Payment-provider features require valid live configuration; localhost demo payment screens do not charge money or create paid orders. Accessibility, privacy, and security are cross-cutting constraints described in the project README and are not duplicated as separate functional requirements here.
