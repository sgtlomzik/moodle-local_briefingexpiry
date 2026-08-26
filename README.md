# Briefing Expiry Management (local_briefingexpiry)

Moodle plugin for managing the validity period of briefing courses (e.g., fire safety, occupational health, etc.).

## Key Features

1. **Course Flagging**: Mark courses as briefings using Moodle's native custom fields API.
2. **Custom Expiry Periods**: Set the briefing validity period to 3 months, 6 months, 1 year, 2 years, or 3 years.
3. **Daily Digest**: Send a daily digest report to managers/administrators outlining expiring briefings, expired briefings, and expired briefings of unenrolled users.
4. **Targeted Reset (Double Opt-In)**: Automatically and selectively reset user completion and gradebook scores for expired briefing courses, allowing users to retake them without clearing course-wide settings or other users' data.
5. **Student Notifications**: Notify students after their completion has been reset, prompting them to retake the briefing.
6. **Data Archiving**: Maintain historical logs of resets, including previous completion dates and final grades.

---

## Installation & Setup

1. Place the plugin files in your Moodle directory under `local/briefingexpiry/`.
2. Run the Moodle upgrade script (via CLI `php admin/cli/upgrade.php` or by visiting the Site Administration page).
3. The installation automatically creates a custom field category named **"Инструктажи" (Briefings)** containing three fields:
   - **`briefing_enabled`** (Checkbox): Enable briefing tracking for the course.
   - **`briefing_period`** (Dropdown): Select the validity period (6 months, 1 year, 2 years, 3 years, 3 months).
   - **`briefing_autoreset`** (Checkbox): Enable auto-resetting completion when the briefing expires.

---

## Configuration

Navigate to **Site Administration > Plugins > Local Plugins > Briefing Expiry Management**:
- **Warning Days**: Number of days prior to expiry when a warning notice is sent.
- **Notify Expired**: Toggle digest alerts for already expired briefings. When disabled, expired briefings are excluded from the digest, but auto-reset (if enabled) still runs.
- **Notification Recipients**: Choose managers/users who will receive the daily digest (users must have the capability `local/briefingexpiry:receivenotifications`).
- **Include Unenrolled**: Include unenrolled users in a separate section of the digest.
- **Global Auto-Reset**: Global switch to enable/disable completion resets.
- **Reset Quiz Attempts**: If enabled, all quiz attempts in the course are deleted when completion is reset.
- **Notify Student**: If enabled, students receive an automated message when their completion is reset.

---

## Important Constraints & Technical Design

> [!WARNING]
> **Limited Module Reset Support:**
> This plugin performs targeted resets of course completion status, activity completions (`course_modules_completion`), course grades, and quiz attempts (if configured). 
> SCORM packages, Assignments (`mod_assign`), and other third-party graded activities do not have their internal attempts/data deleted by this custom targeted reset mechanism. 
> To ensure compatibility, make sure briefing courses use Moodle Quizzes or standard manual completion rather than SCORM packages or Assignments for marking compliance.

---

## CLI & Scheduled Tasks

The daily check runs as a scheduled task at **06:00 AM** daily. You can run the task manually via CLI for testing:
```bash
php admin/cli/scheduled_task.php --execute="\local_briefingexpiry\task\check_expiry"
```

## Reset Archive Report

Every automatic completion reset is archived (previous completion date, expiry date, reset time, final grade before reset), so the history of previous briefing completions is preserved. Administrators and managers can browse it under **Site Administration > Reports > Briefing reset archive** (capability `local/briefingexpiry:viewreport`).

## Data Privacy & GDPR

The plugin complies with Moodle's privacy API, registering:
- `local_briefingexpiry_log` (notification logs)
- `local_briefingexpiry_arch` (completion reset archives)

Users can export or delete their briefing logs and archives through Moodle's standard Data Privacy tools.
