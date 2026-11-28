# SurveyJS Forms Add-on: License

SurveyJS Forms Add-on\
Copyright (C) 2015-2026 Devsoft Baltic OÜ (https://surveyjs.io/)

SurveyJS Forms Add-on (the "Add-on") consists of parts under different licenses:

| Part | Files | License |
|---|---|---|
| Add-on code | Everything in the Add-on except the folders listed in the next two rows: PHP, JavaScript and CSS files in the plugin root, `includes/` and `assets/`, translations in `languages/`, `readme.txt` | GNU General Public License, version 2 or later, with an additional permission (Part 1) |
| SurveyJS Libraries | `libs/survey-creator-core/`, `libs/survey-creator-js/` (SurveyJS Creator), `libs/survey-pdf/` (SurveyJS PDF Generator), `libs/survey-analytics/` (SurveyJS Dashboard) | Proprietary: SurveyJS Libraries License for SurveyJS Forms Add-on (Part 2) |
| Third-party components | `libs/jspdf/`, `libs/chart.js/`, `libs/chartjs-plugin-datalabels/`, `assets/fonts/` | MIT License, SIL Open Font License 1.1 (Part 3) |

The "SurveyJS" name and logo are covered separately (Part 4).

## Part 1. Add-on code: GNU General Public License, version 2 or later

The Add-on code is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

The Add-on code is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

The full text of the GNU General Public License, version 2, is in [`license.txt`](license.txt).

**Additional permission.** As an additional permission under the GNU General Public License, Devsoft Baltic OÜ, the copyright holder of the Add-on code, gives you permission to combine the Add-on code with the SurveyJS Libraries (Part 2) and to convey the resulting combination, without the GNU General Public License applying to the SurveyJS Libraries. You must follow the GNU General Public License for the Add-on code and Part 2 for the SurveyJS Libraries. If you modify the Add-on code, you may extend this permission to your version, but you are not obligated to do so. If you do not wish to do so, delete this permission statement from your version.

Nothing in Part 2 limits the rights that the GNU General Public License gives you to the Add-on code.

## Part 2. SurveyJS Libraries License for SurveyJS Forms Add-on

**2.1. The SurveyJS Libraries.** "SurveyJS Libraries" means all files in the folders `libs/survey-creator-core/`, `libs/survey-creator-js/`, `libs/survey-pdf/` and `libs/survey-analytics/` of the Add-on, including the versions of these files delivered with updates of the Add-on. The SurveyJS Libraries are commercial software of Devsoft Baltic OÜ ("DEVSOFTBALTIC"). They are licensed, not sold. They are not licensed under the GNU General Public License, and the additional permission in Part 1 does not make them so.

**2.2. Agreement.** This Part 2 is a legally binding agreement between DEVSOFTBALTIC and you ("Licensee"): the individual, business or other entity that installs the Add-on on a WordPress site, or on whose behalf it is installed. By installing, copying or otherwise using the SurveyJS Libraries, you agree to this Part 2. If you represent a business or other entity, you confirm that you have the authority to bind it. If you do not agree, do not install, copy or use the SurveyJS Libraries.

**2.3. Grant of license.** Subject to this Part 2, DEVSOFTBALTIC grants you a worldwide, non-exclusive, non-transferable (except as stated in section 2.5), free-of-charge license to install, copy and use the SurveyJS Libraries, unmodified, only as part of the Add-on running in WordPress, as follows:

1. On any number of WordPress sites, including multisite networks, that you own, operate or administer, including sites that you build, host or maintain for your clients.
2. In development, staging, testing and backup copies of these sites.
3. In private source code repositories and deployment pipelines of these sites, including repositories on WordPress VIP and other managed hosting platforms.
4. Your employees, contractors and hosting partners (such as Automattic for WordPress VIP) may access and use the SurveyJS Libraries on your behalf, solely for the purposes of this Part 2 and subject to the same restrictions. You are responsible for their compliance with this Part 2.
5. There is no limit on the number of users of these sites (administrators, editors, respondents and other visitors) who use the features that the SurveyJS Libraries provide.
6. Configuring and extending the Add-on on these sites through the settings, hooks, filters and JavaScript extension points of SurveyJS Forms and the Add-on counts as use as part of the Add-on.

**2.4. Updates.** Updates of the SurveyJS Libraries are delivered with updates of the Add-on, free of charge, through the Add-on's built-in updater or as a new download of the Add-on. Updates are covered by this Part 2. DEVSOFTBALTIC is not obliged to provide updates or support and may discontinue the Add-on at any time; the licenses granted for the copies you already have remain valid.

**2.5. Copies for others.** You may give others a copy of the SurveyJS Libraries only inside a copy of the Add-on (including a copy whose Add-on code you modified under Part 1), with the SurveyJS Libraries unmodified and with this file and the `LICENSE` files in `libs/` included; for example, when you hand over a site to your client. Each recipient receives the license in this Part 2 directly from DEVSOFTBALTIC. You may not sublicense the SurveyJS Libraries or offer them under any other terms. You may not publish the SurveyJS Libraries separately from the Add-on, and you may not charge a fee for the SurveyJS Libraries or for a copy of the Add-on that contains them. Fees for your own services, such as building, hosting or maintaining sites, are allowed.

**2.6. Restrictions.** You may not:

1. Extract the SurveyJS Libraries or any part of them from the Add-on, or use them separately from the Add-on: in another plugin, theme or application, or outside WordPress.
2. Modify, adapt, translate, decompile, disassemble or reverse engineer the SurveyJS Libraries, except to the extent that applicable law expressly permits it despite this limitation.
3. Rent, lease, lend, sell or sublicense the SurveyJS Libraries.
4. Remove or change the copyright and license notices in the SurveyJS Libraries, or hide or remove the notices that the SurveyJS Libraries display (such as "Powered by SurveyJS"), unless DEVSOFTBALTIC has given you a white-label option for that.
5. Use the value `embedded-wordpress` that the Add-on passes to `Survey.setLicenseKey`, or any other means by which the Add-on enables the SurveyJS Libraries, to run the SurveyJS Libraries anywhere other than as part of the Add-on.
6. Create any tool, redistributable or product, including a plugin for WordPress or another content management system and a hosted service, that directly or indirectly competes with the SurveyJS Libraries or the Add-on by utilizing all or any portion of the SurveyJS Libraries.

**2.7. Other use requires a SurveyJS developer license.** Any use of the SurveyJS Libraries that this Part 2 does not permit, including use in your own applications, in other plugins or themes, or outside WordPress, requires a SurveyJS developer license: https://surveyjs.io/licensing.

**2.8. The `LICENSE` files in `libs/`.** Each of the folders listed in section 2.1 contains a `LICENSE` file: the SurveyJS commercial developer license that is shipped with the library's npm package. That license governs developers who purchase SurveyJS developer licenses. It does not govern the use of the SurveyJS Libraries as part of the Add-on: for this use, you need no developer license, and this Part 2 applies instead of those `LICENSE` files. The files are part of the SurveyJS Libraries and must stay unchanged.

**2.9. Copyright.** All title and copyrights in and to the SurveyJS Libraries (including, but not limited to, any images, text and code incorporated into them) are owned by DEVSOFTBALTIC. The SurveyJS Libraries are protected by copyright laws and international treaty provisions; you must treat them like any other copyrighted material, except that you may install and use them as described in this Part 2. DEVSOFTBALTIC reserves all rights not expressly granted in this Part 2.

**2.10. Open-source components.** The SurveyJS Libraries include open-source components licensed under the MIT License. Their notices are in the files of the SurveyJS Libraries.

**2.11. Disclaimer of warranty.** The SurveyJS Libraries are provided free of charge. DEVSOFTBALTIC expressly disclaims any warranty for the SurveyJS Libraries. THE SURVEYJS LIBRARIES AND ANY RELATED DOCUMENTATION ARE PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESS OR IMPLIED, INCLUDING, WITHOUT LIMITATION, THE IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE OR NONINFRINGEMENT. DEVSOFTBALTIC DOES NOT WARRANT, GUARANTEE OR MAKE ANY REPRESENTATIONS REGARDING THE USE, OR THE RESULTS OF THE USE, OF THE SURVEYJS LIBRARIES IN TERMS OF CORRECTNESS, ACCURACY, RELIABILITY OR OTHERWISE. THE ENTIRE RISK ARISING OUT OF THE USE OR PERFORMANCE OF THE SURVEYJS LIBRARIES REMAINS WITH YOU. No oral or written information or advice given by DEVSOFTBALTIC or its employees creates a warranty.

**2.12. Limitation of liability.** TO THE MAXIMUM EXTENT PERMITTED BY APPLICABLE LAW, IN NO EVENT SHALL DEVSOFTBALTIC BE LIABLE FOR ANY SPECIAL, INCIDENTAL, INDIRECT OR CONSEQUENTIAL DAMAGES WHATSOEVER (INCLUDING, WITHOUT LIMITATION, DAMAGES FOR LOSS OF BUSINESS PROFITS, BUSINESS INTERRUPTION, LOSS OF DATA OR BUSINESS INFORMATION, OR ANY OTHER PECUNIARY LOSS) ARISING OUT OF THE USE OF OR INABILITY TO USE THE SURVEYJS LIBRARIES, EVEN IF DEVSOFTBALTIC HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES. You understand that the SurveyJS Libraries may produce inaccurate results because of a fault within them or because they are not used or deployed properly; you are solely responsible for your use of the SurveyJS Libraries and for the forms and the data that your sites collect with them. Nothing in this Part 2 limits any liability that cannot be limited under applicable law, including liability for willful misconduct or fraud.

**2.13. Termination.** Your license under this Part 2 terminates automatically if you fail to comply with it. If you stop the violation within 30 days after you become aware of it, your license is reinstated as of that date. When your license terminates, you must stop using the SurveyJS Libraries and delete all copies of them from your sites, repositories and other places. Termination does not affect your rights to the Add-on code under Part 1, nor your forms and entries, which are stored by SurveyJS Forms. Sections 2.6, 2.9 and 2.11 to 2.15 survive termination.

**2.14. Personal data.** The SurveyJS Libraries run in the browser and send no form data or personal data to DEVSOFTBALTIC. The connections that the Add-on makes to surveyjs.io (downloads of the Add-on and its updates) are described in the "External services" section of `readme.txt`. DEVSOFTBALTIC processes personal data according to its Privacy Policy: https://surveyjs.io/privacy-policy.

**2.15. Miscellaneous.** This Part 2 is governed by and construed in accordance with the laws of Estonia, without giving effect to any choice of law or conflict of law provisions. Any dispute, controversy or claim arising out of or relating to this Part 2, or the breach, termination or validity thereof, shall be finally settled by arbitration in accordance with the Arbitration Rules of the Arbitration Court of the Estonian Chamber of Commerce and Industry. The language of the arbitration shall be English. If you are a consumer, this does not deprive you of the protection of the mandatory laws of your country of residence. If any provision of this Part 2 is found void or unenforceable, the remainder stays valid and enforceable. A new version of the Add-on may come with a new version of this Part 2, which applies to that version of the Add-on.

## Part 3. Third-party components

The Add-on includes the following third-party components. Each keeps its own license; the license texts are in their folders.

| Component | Folder | License | License text |
|---|---|---|---|
| jsPDF 4.2.1, © 2010-2025 James Hall, © 2015-2025 yWorks GmbH; the file also contains components under their own permissive licenses (such as pako: MIT and Zlib), listed in its comments | `libs/jspdf/` | MIT | `libs/jspdf/LICENSE` |
| Chart.js 4.6.1, © 2014-2024 Chart.js Contributors | `libs/chart.js/` | MIT | `libs/chart.js/LICENSE.md` |
| chartjs-plugin-datalabels 2.2.0, © 2017-2021 chartjs-plugin-datalabels contributors | `libs/chartjs-plugin-datalabels/` | MIT | `libs/chartjs-plugin-datalabels/LICENSE.md` |
| IBM Plex Sans Hebrew, © 2017 IBM Corp., Reserved Font Name "Plex" | `assets/fonts/IBMPlexSansHebrew-*.ttf` | SIL Open Font License 1.1 | `assets/fonts/OFL.txt` |
| Noto Sans, a subset merged from Noto Sans, Noto Sans Arabic and Noto Sans Math, © 2022 The Noto Project Authors | `assets/fonts/noto-sans/` | SIL Open Font License 1.1 | `assets/fonts/noto-sans/OFL.txt` |

## Part 4. Trademarks

"SurveyJS" and the SurveyJS logo are trademarks of Devsoft Baltic OÜ. Neither the GNU General Public License nor Part 2 grants any right to use them, except to truthfully refer to the Add-on and the SurveyJS Libraries.
