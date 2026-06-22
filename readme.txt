=== KD EarlyBird Notify ===
Contributors: KhvichaDev
Tags: waitlist, lead generation, coming soon, product launch, signup form
Requires at least: 5.6
Tested up to: 7.0
Stable tag: 1.0
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress Waitlist Plugin for Lead Generation, Early Access Programs, Pre-Registrations, and Launch Notifications.

== Description ==

**Turn website visitors into launch-ready customers before your product or service is even available.**

**KD EarlyBird Notify** is a flexible **WordPress waitlist plugin** that helps you collect leads, build email lists, and notify subscribers when your product, service, app, or event launches.

Perfect for SaaS startups, mobile app launches, new product releases, service launches, online courses, memberships, local businesses, and coming soon campaigns. Whether you need a high-converting **lead capture form** to launch your website, a mobile app **pre-registration plugin**, or an automated **waiting list form** to kickstart your **email list building**, this tool makes lead capture effortless.

With a built-in **launch notification system**, you can collect subscribers through customizable waitlist and **notify me forms**, then broadcast bulk updates when your launch date arrives. The plugin supports multiple delivery channels, functioning as a reliable **email and SMS notifications** broadcaster via standard WordPress mailers, custom HTTP gateways, free manual routes, or optional Twilio integrations.

To maximize your conversion rates, the plugin offers built-in **social proof indicators**. You can display the total number of joined subscribers directly inside the registration form, or showcase your subscriber count anywhere on your site using a dedicated widget or stylish standalone badge shortcode. This helps build credibility by displaying how many users are already on your waitlist.

=== Common Use Cases ===
*   Build a waiting list before launching a new SaaS product.
*   Collect pre-registrations for mobile apps.
*   Capture leads for upcoming online courses or digital downloads.
*   Build hype and generate leads around an e-commerce product launch.
*   Create an early access program for software or membership sites.
*   Notify subscribers instantly when registrations or sales open.

=== Key Features ===
*   **Create Unlimited Waitlists & Signup Forms**: Launch separate waiting lists for upcoming products, mobile apps, SaaS platforms, events, online courses, memberships, or service launches.
*   **Multi-Channel Broadcasting**: Send automated campaign notifications to subscribers via Email (using native wp_mail/SMTP), custom third-party/local HTTP Gateways, or optional Twilio integrations (SMS and WhatsApp).
*   **Manual & Free Channels**: Send notifications manually using device-level SMS links, WhatsApp Web, or native WhatsApp Desktop App protocols without paying for external API credits.
*   **Clipboard Export Utilities**: Copy subscriber contact lists directly to your clipboard in a comma-separated format with a single click.
*   **Modern & Responsive Design**: Features a modern, clean, and fully responsive frontend registration form template.
*   **Social Proof & Badge Widgets**: Show the current subscriber count directly inside your signup form or display it anywhere on the site using a dedicated widget or a stylish standalone badge shortcode.
*   **GDPR & Security Compliant**: Bundled locally with zero external CDN dependencies. Includes honeypot spam protection, CSRF nonce validation, and automatic cache-busting nonces.
*   **Flexible Schema**: Fully customized field configuration (Name, Email, Phone, WhatsApp) with E.164 normalization logic.
*   **Data Safeguards**: Optional automated cleanup of custom tables and system configurations upon plugin uninstall.

=== Perfect For ===
*   **Products & Services**: Build anticipation and collect registrations before releasing new physical/digital products or launching new services.
*   **SaaS Startups**: Build a waiting list and gauge interest before launching your product.
*   **Mobile App Launches**: Capture phone/WhatsApp contacts for pre-registration and notify them directly when your iOS or Android app hits the app stores.
*   **Product Launch Campaigns**: Manage subscribers for e-commerce, crowdfunding (Kickstarter/Indiegogo), or retail product launches.
*   **Online Courses & Memberships**: Build early access programs and collect emails for upcoming masterclasses or digital products.
*   **Marketing Agencies**: Quickly deploy high-converting coming soon signup forms for clients using simple shortcodes.
*   **Local Businesses**: Collect pre-registrations for upcoming events, openings, or service launches.

== Installation ==

1. Upload the entire `kd-earlybird-notify` directory to your `/wp-content/plugins/` directory, or install it directly via the WordPress Admin Plugins uploader.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the new **Early Bird Notify** dashboard menu in your admin screen to configure your fields, custom texts, and API gateways.
4. Copy the registration shortcode (e.g. `[kd_early_bird_signup service="Default Service"]`) and paste it on any page or widget.

== Frequently Asked Questions ==

= Can I create a waiting list for my upcoming product? =
Yes, absolutely. You can set up as many separate waiting lists as you need, configure custom fields for each, and customize the form texts to match your product's branding.

= Can I use it for free without paying for API gateways? =
Yes! You can use the manual sending channels (Manual SMS, Manual WhatsApp Web, or Manual WhatsApp Desktop) which open native messaging protocols on your device or browser for free. You can also export and copy contact lists directly using the Clipboard Copier to use with any software or system.

= How do I display the subscriber count? =
You can display the number of registered users as a badge using the `[kd_early_bird_subscriber_count service="Default Service"]` shortcode. To output only the raw number, use `[kd_early_bird_subscriber_count service="Default Service" format="raw"]`.

= Is it compatible with caching plugins? =
Yes, the plugin contains an AJAX action that automatically regenerates security nonces on page load to bypass caching plugins.

== Changelog ==

= 1.0 =
* First public release. Full waitlist functionality with multi-channel batch sending and local assets.
