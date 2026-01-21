
=== Hotel Room Reservation (Lite) ===
Contributors: m365copilot
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A simple, secure hotel room reservation plugin: Rooms & Bookings custom post types, availability check (conflict detection), and shortcodes.

== Description ==
This plugin lets you create rooms (with price per night & capacity) and accept reservation requests without a payment gateway. Availability is computed by checking overlapping bookings (pending/confirmed) for the same room.

**Shortcodes**
* `[hotel_search]` – List rooms with a simple date search & availability checker.
* `[hotel_booking room_id="123"]` – Booking form for a specific room (or place it on a Room single page).

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/` or use **Plugins → Add New → Upload**.
2. Activate **Hotel Room Reservation (Lite)**.
3. Go to **Rooms → Add New** to create rooms and set price/capacity.
4. Add pages with `[hotel_search]` and (optionally) `[hotel_booking room_id="ID"]`.

== Frequently Asked Questions ==
= Does it process payments? =
No. This is a no-payment (request/confirm) flow. You can confirm bookings in the admin.

= How is availability calculated? =
We find any booking for the same room with status pending/confirmed that overlaps the requested dates; if none, the room is available.

= Can I add Stripe/PayPal? =
Yes. A gateway can be added in a Pro version or via custom code; hook into `hr_create_booking` and update status to `confirmed` on successful payment.

== Changelog ==
= 1.0.0 =
* Initial release.
