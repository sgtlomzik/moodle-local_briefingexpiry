<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Russian strings for local_briefingexpiry.
 *
 * @package    local_briefingexpiry
 * @copyright  2026 SgtLomzik <lomzike@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Срок действия инструктажей';
$string['task_check_expiry'] = 'Проверка сроков действия инструктажей';
$string['warningdays'] = 'Дней до предупреждения';
$string['warningdays_desc'] = 'За сколько дней до истечения срока действия инструктажа отправлять предупреждение.';
$string['notifyexpired'] = 'Уведомлять об истекших';
$string['notifyexpired_desc'] = 'Отправлять уведомление, когда срок действия инструктажа истёк, а нового прохождения нет.';
$string['recipients'] = 'Получатели уведомлений';
$string['recipients_desc'] = 'Пользователи, которые будут получать уведомления об истекающих/истекших инструктажах.';
$string['includeunenrolled'] = 'Включать отчисленных';
$string['includeunenrolled_desc'] = 'Включать ли отчисленных с курса-инструктажа сотрудников отдельным блоком в дайджест.';
$string['enableautoreset'] = 'Глобальный автосброс';
$string['enableautoreset_desc'] = 'Разрешить автоматический сброс завершения курсов-инструктажей по истечении срока действия (требуется также включить автосброс в настройках конкретного курса).';
$string['resetquizattempts'] = 'Сбрасывать попытки тестов';
$string['resetquizattempts_desc'] = 'При сбросе завершения курса также удалять все попытки прохождения тестов пользователя в этом курсе.';
$string['notifystudent'] = 'Уведомлять студента';
$string['notifystudent_desc'] = 'После автоматического сброса завершения курса отправлять студенту уведомление с напоминанием пройти инструктаж повторно.';

// Notification digest strings
$string['digest_subject'] = 'Сводный отчет по истекающим и просроченным инструктажам';
$string['digest_intro'] = 'Здравствуйте!<br><br>Представляем ежедневный сводный отчет по статусу инструктажей сотрудников.';
$string['digest_expiring_title'] = 'Истекающие инструктажи (срок действия заканчивается)';
$string['digest_expired_title'] = 'Просроченные инструктажи (срок действия истёк)';
$string['digest_unenrolled_title'] = 'Отчисленные сотрудники с истекшим сроком инструктажа';
$string['digest_header_fullname'] = 'ФИО';
$string['digest_header_course'] = 'Курс';
$string['digest_header_completed'] = 'Дата прохождения';
$string['digest_header_expires'] = 'Дата истечения';
$string['digest_header_daysleft'] = 'Осталось дней';
$string['digest_header_daysago'] = 'Просрочено дней';
$string['digest_no_data'] = 'Нет данных.';

// Student notification strings
$string['student_notification_subject'] = 'Необходимо повторно пройти инструктаж: {$a->coursename}';
$string['student_notification_body'] = 'Здравствуйте, {$a->fullname}!<br><br>Срок действия вашего предыдущего инструктажа по курсу «{$a->coursename}» истёк.<br>Предыдущее прохождение: {$a->completeddate}<br>Дата истечения: {$a->expirydate}<br><br>Пожалуйста, пройдите инструктаж повторно по ссылке: <a href="{$a->courseurl}">{$a->coursename}</a>';

// Archive report
$string['archivereport'] = 'Архив сбросов инструктажей';
$string['report_header_reset'] = 'Дата сброса';
$string['report_header_grade'] = 'Оценка';

// Capabilities
$string['briefingexpiry:receivenotifications'] = 'Получать уведомления об истечении сроков инструктажей';
$string['briefingexpiry:viewreport'] = 'Просматривать архив сбросов инструктажей';

// Message providers
$string['messageprovider:expirynotice'] = 'Уведомления об истечении сроков инструктажей (для ответственных)';
$string['messageprovider:resetnotice'] = 'Уведомления о сбросе прохождения (для студентов)';

// Privacy strings
$string['privacy:metadata:log'] = 'Журнал уведомлений об истечении сроков инструктажей для исключения дубликатов.';
$string['privacy:metadata:log:userid'] = 'Идентификатор пользователя.';
$string['privacy:metadata:log:courseid'] = 'Идентификатор курса.';
$string['privacy:metadata:log:timecompleted'] = 'Время завершения инструктажа.';
$string['privacy:metadata:log:timeexpires'] = 'Время истечения срока действия инструктажа.';
$string['privacy:metadata:log:notificationtype'] = 'Тип отправленного уведомления (warning или expired).';
$string['privacy:metadata:log:timecreated'] = 'Время отправки уведомления.';
$string['privacy:metadata:arch'] = 'Архив сбросов завершения курсов-инструктажей.';
$string['privacy:metadata:arch:userid'] = 'Идентификатор пользователя.';
$string['privacy:metadata:arch:courseid'] = 'Идентификатор курса.';
$string['privacy:metadata:arch:timecompleted'] = 'Время предыдущего завершения курса.';
$string['privacy:metadata:arch:timeexpires'] = 'Время истечения срока действия.';
$string['privacy:metadata:arch:timereset'] = 'Время сброса завершения курса.';
$string['privacy:metadata:arch:finalgrade'] = 'Оценка пользователя перед сбросом.';
$string['privacy:logpath'] = 'Журнал уведомлений инструктажей';
$string['privacy:archpath'] = 'Архив сбросов инструктажей';

// Course custom fields created at install time.
$string['customfieldcategory'] = 'Инструктажи';
$string['field_autoreset'] = 'Автоматически сбрасывать прохождение по истечении срока';
$string['field_enabled'] = 'Является инструктажем';
$string['field_period'] = 'Срок действия инструктажа';
$string['period_1year'] = '1 год';
$string['period_2years'] = '2 года';
$string['period_3months'] = '3 месяца';
$string['period_3years'] = '3 года';
$string['period_6months'] = '6 месяцев';
