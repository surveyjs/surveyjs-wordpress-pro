=== SurveyJS Forms Pro ===
Contributors: devsoftbaltic
Tags: form builder, survey, pdf, analytics
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 3.2.4
Requires PHP: 8.2
License: See LICENSE.md

Adds Survey Creator, PDF export and analytics to SurveyJS Forms.

== Description ==

SurveyJS Forms Pro is an add-on for the free SurveyJS Forms plugin. SurveyJS Forms keeps your forms and entries; Pro adds features on top of them.

Survey Creator: build forms visually right in the form editor — drag & drop designer, logic, translations, JSON editor and preview, with a full-screen mode. A GDPR Agreement question in the toolbox. Images come from your Media Library.

* Charts: charts for every question of a form, with a date range filter, on the form's Results page.

**Dashboard** (SurveyJS Forms > Dashboard): entries across all forms for the last 7, 30 or 90 days or 12 months, charts of entries over time and by form, the latest entries and entry counts per form.

= PDF export =

* Download any entry as a PDF from the Results page, or select several entries and choose Export PDF in Bulk actions to get a ZIP with one PDF per entry.
* Download the blank form as a fillable PDF that respondents can fill in a PDF viewer, or as a printable form.
* Set the page size, font size, layout, colors and header and footer for each form on the PDF tab of the form editor, and see a live preview next to the settings before saving. By default the page size (A4 or Letter) follows the site language; a font that covers the form's language is picked automatically.
* Add your logo, a header text and a watermark such as DRAFT or CONFIDENTIAL to every page, and leave unanswered questions out of entry PDFs.
* Per form, on the PDF tab: show respondents a Download PDF button after they submit the form, attach the PDF of the entry to the email notification and/or the auto-reply, and add a link to the entry's PDF to emails with the {pdf_link} or {pdf_url} tag (signed, expires after the days you set).
* Per form, on the PDF tab: protect PDFs with a password (AES-256). Entry PDFs, ZIP exports and blank forms you download, and the PDF attached to the email notification, then open only with the password.
* PDFs are generated in your browser. Password protection is added by your own site. No form data is sent to any external service.

Pro needs SurveyJS Forms 3.2.4 or later. When SurveyJS Forms is missing or too old, Pro shows a notice and does not load.

== Installation ==

1. Install and activate SurveyJS Forms from the WordPress.org plugin directory.
2. Upload the SurveyJS Forms Pro zip in Plugins > Add New > Upload Plugin and activate it.
3. Enter your SurveyJS license key in SurveyJS Forms > Settings > SurveyJS Forms Pro.

On multisite, network-activate Pro so it can follow SurveyJS Forms updates.

== Updates ==

SurveyJS Forms and SurveyJS Forms Pro are released together with the same SurveyJS library version. When SurveyJS Forms is updated (manually, in bulk or automatically), Pro installs its latest version from surveyjs.io in the same request and stays active. When the two versions don't match, an admin notice offers to update the older one.

Turn this off with `define( 'SURVEYJS_PRO_DISABLE_UPDATES', true );` in wp-config.php. On WordPress VIP and on sites where `DISALLOW_FILE_MODS` is set, Pro does not update itself.

== External services ==

= Pro downloads from surveyjs.io =

After SurveyJS Forms is updated, or when you click "Update SurveyJS Forms Pro" in the version notice, Pro downloads its latest zip from https://surveyjs.io/Content/Downloads/wordpress/surveyjs-pro.zip. The request carries no site address or other data about your site; surveyjs.io sees only the IP address of your web server.

This service is provided by Devsoft Baltic OÜ: [Terms of Use](https://surveyjs.io/terms-use), [Privacy Policy](https://surveyjs.io/privacy-policy).

== Changelog ==

= 3.2.4 =
* Plugin skeleton.
* Survey Creator in the form editor.
* Charts on the Results page.
* Dashboard across all forms.
* PDF export of entries and blank forms.
* PDF logo, header text, watermark, the option to leave out unanswered questions and a live preview on the PDF tab.
* Password-protected PDFs (AES-256).
* Pro updates itself from surveyjs.io whenever SurveyJS Forms is updated; a notice shows when the versions don't match.
