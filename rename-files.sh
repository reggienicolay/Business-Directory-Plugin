#!/bin/bash

# Navigate to src directory
cd src

# Rename all files
mv Importer/CSV.php Importer/class-csv.php 2>/dev/null
mv Forms/BusinessSubmission.php Forms/class-businesssubmission.php 2>/dev/null
mv Forms/ClaimRequest.php Forms/class-claimrequest.php 2>/dev/null
mv Forms/ReviewSubmission.php Forms/class-reviewsubmission.php 2>/dev/null
mv PostTypes/Business.php PostTypes/class-business.php 2>/dev/null
mv Frontend/RegistrationHandler.php Frontend/class-registrationhandler.php 2>/dev/null
mv Frontend/Shortcodes.php Frontend/class-shortcodes.php 2>/dev/null
mv Frontend/BadgeDisplay.php Frontend/class-badgedisplay.php 2>/dev/null
mv Frontend/Filters.php Frontend/class-filters.php 2>/dev/null
mv Frontend/ProfileShortcode.php Frontend/class-profileshortcode.php 2>/dev/null
mv Frontend/RegistrationShortcode.php Frontend/class-registrationshortcode.php 2>/dev/null
mv Security/Captcha.php Security/class-captcha.php 2>/dev/null
mv Security/RateLimit.php Security/class-ratelimit.php 2>/dev/null
mv Gamification/ActivityTracker.php Gamification/class-activitytracker.php 2>/dev/null
mv Gamification/BadgeSystem.php Gamification/class-badgesystem.php 2>/dev/null
mv Gamification/GamificationHooks.php Gamification/class-gamificationhooks.php 2>/dev/null
mv Plugin.php class-plugin.php 2>/dev/null
mv Admin/ImporterPage.php Admin/class-importerpage.php 2>/dev/null
mv Admin/ClaimsQueue.php Admin/class-claimsqueue.php 2>/dev/null
mv Admin/Settings.php Admin/class-settings.php 2>/dev/null
mv Admin/BadgeAdmin.php Admin/class-badgeadmin.php 2>/dev/null
mv Admin/MetaBoxes.php Admin/class-metaboxes.php 2>/dev/null
mv Roles/Manager.php Roles/class-manager.php 2>/dev/null
mv Utils/Cache.php Utils/class-cache.php 2>/dev/null
mv Utils/Validation.php Utils/class-validation.php 2>/dev/null
mv Search/FilterHandler.php Search/class-filterhandler.php 2>/dev/null
mv Search/Geocoder.php Search/class-geocoder.php 2>/dev/null
mv Search/QueryBuilder.php Search/class-querybuilder.php 2>/dev/null
mv DB/SubmissionsTable.php DB/class-submissionstable.php 2>/dev/null
mv DB/ReviewsTable.php DB/class-reviewstable.php 2>/dev/null
mv DB/ClaimRequestsTable.php DB/class-claimrequeststable.php 2>/dev/null
mv DB/LocationsTable.php DB/class-locationstable.php 2>/dev/null
mv DB/Installer.php DB/class-installer.php 2>/dev/null
mv API/GeocodeEndpoint.php API/class-geocodeendpoint.php 2>/dev/null
mv API/SubmissionEndpoint.php API/class-submissionendpoint.php 2>/dev/null
mv API/BusinessEndpoint.php API/class-businessendpoint.php 2>/dev/null
mv Taxonomies/Area.php Taxonomies/class-area.php 2>/dev/null
mv Taxonomies/Category.php Taxonomies/class-category.php 2>/dev/null
mv Taxonomies/Tag.php Taxonomies/class-tag.php 2>/dev/null
mv Notifications/Email.php Notifications/class-email.php 2>/dev/null
mv REST/ClaimController.php REST/class-claimcontroller.php 2>/dev/null
mv REST/SubmitBusinessController.php REST/class-submitbusinesscontroller.php 2>/dev/null
mv REST/BusinessesController.php REST/class-businessescontroller.php 2>/dev/null
mv REST/SubmitReviewController.php REST/class-submitreviewcontroller.php 2>/dev/null
mv Moderation/ReviewsQueue.php Moderation/class-reviewsqueue.php 2>/dev/null
mv Moderation/SubmissionsQueue.php Moderation/class-submissionsqueue.php 2>/dev/null

echo "Files renamed successfully!"
