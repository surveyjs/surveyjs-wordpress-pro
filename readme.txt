=== SurveyJS Forms Add-on ===
Contributors: devsoftbaltic
Tags: form builder, survey, pdf, analytics
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 3.2.4
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Survey Creator with the AI assistant, PDF export and analytics to SurveyJS Forms.

== Description ==

SurveyJS Forms Add-on is an add-on for the free SurveyJS Forms plugin. SurveyJS Forms keeps your forms and entries; the add-on adds features on top of them.

Survey Creator: build forms visually right in the form editor — drag & drop designer, logic, translations, JSON editor and preview, with a full-screen mode. A GDPR Agreement question in the toolbox. Images come from your Media Library. Survey Creator matches the WordPress admin style — admin colors, font size, input fields and compact sizes; turn off "Match the WordPress admin style" in SurveyJS Forms > Settings > Add-on to use the default SurveyJS look.

* Charts: charts for every question of a form, with a date range filter, on the form's Entries page.

**Dashboard** (SurveyJS Forms > Dashboard): entries across all forms for the last 7, 30 or 90 days or 12 months, charts of entries over time and by form, the latest entries and entry counts per form.

= PDF export =

* Download any entry as a PDF from the Entries page, or select several entries and choose Export PDF in Bulk actions to get a ZIP with one PDF per entry.
* Download the blank form as a fillable PDF that respondents can fill in a PDF viewer, or as a printable form.
* Set the page size and orientation, font size, layout, colors and header and footer for each form on the PDF tab of the form editor, and see a live preview next to the settings before saving. By default the page size (A4 or Letter) follows the site language; a font that covers the form's language is picked automatically.
* Add your logo, a header text and a watermark such as DRAFT or CONFIDENTIAL to every page, and leave unanswered questions out of entry PDFs.
* Per form, on the PDF tab: show respondents a Download PDF button after they submit the form, attach the PDF of the entry to the email notification and/or the auto-reply, and add a link to the entry's PDF to emails with the {pdf_link} or {pdf_url} tag (signed, expires after the days you set).
* Per form, on the PDF tab: protect PDFs with a password (AES-256). Entry PDFs, ZIP exports and blank forms you download, and the PDF attached to the email notification, then open only with the password.
* PDFs are generated in your browser. Password protection is added by your own site. No form data is sent to any external service.

= AI assistants (MCP) =

When the AI connection of SurveyJS Forms is on (SurveyJS Forms > Settings > AI MCP), the add-on adds to it:

* PDF settings of each form (page size, fonts, colors, header, footer, watermark, Download PDF button, PDF in emails and others): the AI app changes them like other form settings, as a draft that you review and save in the form editor. The form editor warns when the AI changed PDF attachments or PDF links in emails.
* The add-on's admin settings: Survey Creator on or off, its color mode and style. They change only the WordPress admin and apply right away.

The add-on sends nothing to external services for this.

The add-on needs SurveyJS Forms 3.2.4 or later. When SurveyJS Forms is missing or too old, the add-on shows a notice and does not load.

== Installation ==

1. Install and activate SurveyJS Forms from the WordPress.org plugin directory.
2. Upload the SurveyJS Forms Add-on zip in Plugins > Add New > Upload Plugin and activate it.

On multisite, network-activate the add-on so it can follow SurveyJS Forms updates.

== Updates ==

SurveyJS Forms and SurveyJS Forms Add-on are released together with the same SurveyJS library version. When SurveyJS Forms is updated (manually, in bulk or automatically), the add-on installs its latest version from surveyjs.io in the same request and stays active. When the two versions don't match, an admin notice offers to update the older one.

Turn this off with `define( 'SURVEYJS_ADDON_DISABLE_UPDATES', true );` in wp-config.php. On WordPress VIP and on sites where `DISALLOW_FILE_MODS` is set, the add-on does not update itself. When the add-on is installed with Composer, it does not update itself either: update both plugins with `composer update surveyjs/surveyjs-forms-addon --with-dependencies`.

== External services ==

= Add-on downloads from surveyjs.io =

After SurveyJS Forms is updated, or when you click "Update SurveyJS Forms Add-on" in the version notice, the add-on downloads its latest zip from https://cdn.surveyjs.io/wordpress/surveyjs-forms-addon.zip. The request carries no site address or other data about your site; surveyjs.io sees only the IP address of your web server.

This service is provided by Devsoft Baltic OÜ: [Terms of Use](https://surveyjs.io/terms-use), [Privacy Policy](https://surveyjs.io/privacy-policy).

== Licensing ==

The add-on's own code (PHP, JavaScript, CSS) is licensed under the GPLv2 or later.

The SurveyJS libraries it includes, Survey Creator, PDF Generator and Dashboard (the folders libs/survey-creator-core, libs/survey-creator-js, libs/survey-pdf and libs/survey-analytics), are commercial software of Devsoft Baltic OÜ under a proprietary license. They are free to use only as part of this add-on in WordPress, on any number of sites you own or manage, including client sites, staging copies and private repositories. You may not extract them from the add-on or use them separately. To use these libraries in your own applications, get a SurveyJS developer license: https://surveyjs.io/licensing.

The third-party libraries and fonts keep their own licenses (MIT, SIL Open Font License 1.1). See LICENSE.md in the add-on folder for all license terms.

== Changelog ==

= 3.2.4 =
* Plugin skeleton.
* Survey Creator in the form editor.
* Charts on the Entries page.
* Dashboard across all forms.
* PDF export of entries and blank forms.
* PDF logo, header text, watermark, the option to leave out unanswered questions and a live preview on the PDF tab.
* Password-protected PDFs (AES-256).
* The add-on updates itself from surveyjs.io whenever SurveyJS Forms is updated; a notice shows when the versions don't match.
