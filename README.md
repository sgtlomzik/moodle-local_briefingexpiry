# Briefing expiry management for Moodle

[![Moodle plugin CI](https://github.com/sgtlomzik/moodle-local_briefingexpiry/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/sgtlomzik/moodle-local_briefingexpiry/actions/workflows/moodle-ci.yml)

Tracks how long a completed course stays valid, tells managers what is about to lapse, and can
reset the completion of individual learners so they retake it.

Compliance training — fire safety, occupational health, data protection — is not "done" once;
it is done *for a year*. Moodle records a completion date and stops there. This plugin marks
selected courses as briefings with a validity period, emails a daily digest of what is expiring
and what has already lapsed, and optionally clears the completion of the affected learners so
the course reappears as outstanding.

## Requirements

- Moodle 4.5 (LTS) or later.
- Moodle cron running daily — the check is a scheduled task.
- Course completion enabled on the briefing courses.

## Installation

### From the ZIP file

1. Download the ZIP of this repository.
2. Go to **Site administration → Plugins → Install plugins** and upload the ZIP.
3. Follow the on-screen upgrade steps.

### From Git

```bash
cd /path/to/moodle
git clone https://github.com/sgtlomzik/moodle-local_briefingexpiry.git local/briefingexpiry
```

Then visit **Site administration → Notifications** (or run `php admin/cli/upgrade.php`) to
complete the installation.

Installing creates a course custom field category named **Briefings** containing three fields:

| Shortname | Type | Purpose |
| --- | --- | --- |
| `briefing_enabled` | Checkbox | Marks the course as a briefing. |
| `briefing_period` | Menu | How long a completion stays valid: 6 months, 1 year, 2 years, 3 years or 3 months. |
| `briefing_autoreset` | Checkbox | Reset completion for this course when a learner's briefing expires. |

The options of `briefing_period` are stored by position, so new options may only ever be appended.

## Setting up a briefing course

1. Edit the course settings and, under **Briefings**, tick **This course is a briefing**.
2. Choose the validity period.
3. Tick **Automatically reset completion when the briefing expires** if learners should have to
   retake it. This has no effect until the global auto-reset setting below is also on.

## Configuration

**Site administration → Plugins → Local plugins → Briefing expiry management**

| Setting | Description |
| --- | --- |
| Warning days | How many days before expiry the warning appears in the digest. |
| Notify expired | Include already expired briefings in the digest. Auto-reset still runs when this is off. |
| Notification recipients | Who receives the daily digest. Users need `local/briefingexpiry:receivenotifications`. |
| Include unenrolled | List expired briefings of users who are no longer enrolled, in their own section. |
| Global auto-reset | Master switch for resetting completions. Both this and the per-course field must be on. |
| Reset quiz attempts | Delete the learner's quiz attempts in the course as part of a reset. |
| Notify student | Message the learner after their completion is reset. |

The scheduled task **Check briefing expiry** runs daily at 06:00. To run it by hand:

```bash
php admin/cli/scheduled_task.php --execute='\local_briefingexpiry\task\check_expiry'
```

## What a reset does, and does not, do

> **Warning**
> A reset is irreversible. It deletes course completion records, activity completions and the
> learner's grades in that course, and — if **Reset quiz attempts** is on — their quiz attempts.
> The previous completion date and final grade are archived first, but the underlying activity
> data is gone.

The reset is targeted at one learner rather than using Moodle's course reset, so other people's
data and the course configuration are untouched. It covers course completion,
`course_modules_completion`, `course_modules_viewed`, the gradebook entries for that learner and
quiz attempts.

It does **not** delete the internal data of SCORM packages, assignments (`mod_assign`) or other
third party graded activities. Build briefing courses around quizzes or manual completion; a
SCORM package will keep its own record of the learner's previous attempt.

## Reset archive report

Every automatic reset is archived with the previous completion date, the expiry date, the reset
time and the final grade before the reset. Browse it under
**Site administration → Reports → Briefing reset archive**
(capability `local/briefingexpiry:viewreport`).

## Privacy

The plugin stores two tables of personal data:

- `local_briefingexpiry_log` — which notifications have been sent, so nobody is told twice.
- `local_briefingexpiry_arch` — the archive of completion resets.

Both are declared through the Privacy API and are exported and deleted by Moodle's standard
data requests. The plugin sends no data outside the site.

## Bug tracker

Please report issues at
<https://github.com/sgtlomzik/moodle-local_briefingexpiry/issues>.

## License

2026 SgtLomzik <lomzike@gmail.com>

This program is free software: you can redistribute it and/or modify it under the terms of the
GNU General Public License as published by the Free Software Foundation, either version 3 of the
License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not,
see <https://www.gnu.org/licenses/>.
